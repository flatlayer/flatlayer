<?php

namespace App\Jobs;

use App\Models\Entry;
use App\Services\FileDiscoveryService;
use App\Services\RepositoryDiskManager;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Class EntrySyncJob
 *
 * This job synchronizes Markdown files with database entries.
 * It supports both local Git repositories and Laravel disk-based storage,
 * with optional Git pulling and webhook triggers.
 */
class EntrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The chunk size for bulk operations.
     */
    private const CHUNK_SIZE = 100;

    /**
     * The filesystem disk instance.
     */
    protected ?Filesystem $disk = null;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    protected int $tries;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    protected array $backoff;

    /**
     * Create a new job instance.
     *
     * @param string $type The type of content being synced
     * @param string|null $diskName The name of the Laravel disk to use (optional)
     * @param bool $shouldPull Whether to pull latest changes from Git (default: false)
     * @param bool $skipIfNoChanges Whether to skip processing if no changes detected (default: false)
     * @param string|null $webhookUrl The URL to trigger after sync completion (default: null)
     */
    public function __construct(
        protected string $type,
        protected ?string $diskName = null,
        protected bool $shouldPull = false,
        protected bool $skipIfNoChanges = false,
        protected ?string $webhookUrl = null
    ) {
        // Set the number of retry attempts from config
        $this->tries = config('flatlayer.git.retry_attempts', 3);
        $this->backoff = [
            config('flatlayer.git.retry_delay', 5),
            config('flatlayer.git.retry_delay', 5) * 2,
            config('flatlayer.git.retry_delay', 5) * 4,
        ];
    }

    /**
     * Execute the job.
     *
     * @param Git $git The Git instance for repository operations
     * @param FileDiscoveryService $fileDiscovery The file discovery service
     * @param RepositoryDiskManager $diskManager The repository disk manager
     * @throws GitException
     */
    public function handle(Git $git, FileDiscoveryService $fileDiscovery, RepositoryDiskManager $diskManager): void
    {
        Log::info("Starting content sync for type: {$this->type}");

        try {
            // Get or create the appropriate disk
            $this->disk = $this->getDisk($diskManager);

            $changesDetected = true;
            if ($this->shouldPull) {
                $changesDetected = $this->handleGitPull($git, $diskManager);
                if (!$changesDetected && $this->skipIfNoChanges) {
                    Log::info('No changes detected and skipIfNoChanges is true. Skipping sync.');
                    return;
                }
            }

            $this->processContent($fileDiscovery);
        } catch (\Exception $e) {
            Log::error("Error during content sync: {$e->getMessage()}");
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get or create the appropriate disk.
     */
    protected function getDisk(RepositoryDiskManager $diskManager): Filesystem
    {
        if ($this->diskName) {
            return Storage::disk($this->diskName);
        }

        if (!$diskManager->hasRepository($this->type)) {
            throw new \RuntimeException("No repository configured for type: {$this->type}");
        }

        return $diskManager->getDisk($this->type);
    }

    /**
     * Handle Git pull operations if the disk is local.
     *
     * @return bool True if changes were detected, false otherwise
     */
    protected function handleGitPull(Git $git, RepositoryDiskManager $diskManager): bool
    {
        $diskConfig = $diskManager->getConfig($this->type);
        $diskRoot = $diskConfig['path'];

        // Verify this is a local repository
        if (!file_exists($diskRoot) || !is_dir($diskRoot)) {
            Log::warning("Cannot pull from Git: {$diskRoot} is not a local directory");
            return false;
        }

        try {
            $repo = $git->open($diskRoot);
            Log::info("Opened Git repository at: {$diskRoot}");

            // Configure authentication
            $this->configureGitAuth($repo);

            $beforeHash = $repo->getLastCommitId()->toString();
            Log::info("Current commit hash before pull: {$beforeHash}");

            // Set timeout for Git operations
            $timeout = config('flatlayer.git.timeout', 60);
            $repo->setTimeout($timeout);

            $repo->pull();
            Log::info('Pull completed successfully');

            $afterHash = $repo->getLastCommitId()->toString();
            Log::info("Current commit hash after pull: {$afterHash}");

            $changesDetected = $beforeHash !== $afterHash;
            Log::info($changesDetected ? 'Changes detected during pull' : 'No changes detected during pull');

            return $changesDetected;
        } catch (GitException $e) {
            Log::error("Error during Git pull: {$e->getMessage()}");
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Configure Git authentication based on settings.
     *
     * @throws GitException
     */
    protected function configureGitAuth(GitRepository $repo): void
    {
        $authMethod = config('flatlayer.git.auth_method', 'token');

        try {
            // Set commit identity
            $repo->setIdentity(
                config('flatlayer.git.commit_name', 'Flatlayer CMS'),
                config('flatlayer.git.commit_email', 'cms@flatlayer.io')
            );

            // Configure authentication
            switch ($authMethod) {
                case 'token':
                    $username = config('flatlayer.git.username');
                    $token = config('flatlayer.git.token');

                    if ($username && $token) {
                        $repo->setAuthentication($username, $token);
                        Log::info("Git authentication configured using token for user: {$username}");
                    }
                    break;

                case 'ssh':
                    $sshKeyPath = config('flatlayer.git.ssh_key_path');
                    if ($sshKeyPath && file_exists($sshKeyPath)) {
                        $repo->setSSHKey($sshKeyPath);
                        Log::info("Git authentication configured using SSH key");
                    }
                    break;

                default:
                    Log::warning("Unsupported Git authentication method: {$authMethod}");
            }
        } catch (GitException $e) {
            Log::error("Failed to configure Git authentication: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Process content files and sync with database.
     */
    protected function processContent(FileDiscoveryService $fileDiscovery): void
    {
        Log::info('Starting content file processing');

        // Find all markdown files using the disk
        $files = $fileDiscovery->findFiles($this->disk);
        Log::info('Found '.$files->count().' files to process');

        $existingSlugs = Entry::where('type', $this->type)->pluck('slug')->flip();
        $processedSlugs = [];
        $updatedCount = 0;
        $createdCount = 0;

        $batchSize = config('flatlayer.sync.batch_size', self::CHUNK_SIZE);
        foreach ($files->chunk($batchSize) as $batch) {
            foreach ($batch as $relativePath => $file) {
                try {
                    $slug = $fileDiscovery->generateSlug($relativePath);
                    $processedSlugs[] = $slug;

                    // Process the file using the disk
                    $item = Entry::syncFromMarkdown($this->disk, $relativePath, $this->type, true);

                    // Ensure the correct slug is set
                    if ($item->slug !== $slug) {
                        $item->slug = $slug;
                        $item->save();
                    }

                    if ($existingSlugs->has($slug)) {
                        $updatedCount++;
                        Log::info("Updated content item: {$slug}");
                    } else {
                        $createdCount++;
                        Log::info("Created new content item: {$slug}");
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing file {$relativePath}: ".$e->getMessage());
                    if (config('flatlayer.sync.log_level') === 'debug') {
                        Log::debug($e->getTraceAsString());
                    }
                }
            }
        }

        $deletedCount = $this->deleteRemovedEntries($existingSlugs, $processedSlugs);
        Log::info("Content sync completed for type: {$this->type}");

        $this->triggerWebhook($updatedCount, $createdCount, $deletedCount, $files->count());
    }

    /**
     * Delete entries that no longer have corresponding files.
     *
     * @return int The number of deleted entries
     */
    private function deleteRemovedEntries(\Illuminate\Support\Collection $existingSlugs, array $processedSlugs): int
    {
        $slugsToDelete = $existingSlugs->diffKeys(array_flip($processedSlugs));
        $deleteCount = $slugsToDelete->count();
        Log::info("Deleting {$deleteCount} content items that no longer have corresponding files");

        $totalDeleted = 0;
        $batchSize = config('flatlayer.sync.batch_size', self::CHUNK_SIZE);

        $slugsToDelete->chunk($batchSize)->each(function ($chunk) use (&$totalDeleted) {
            $deletedCount = Entry::where('type', $this->type)->whereIn('slug', $chunk->keys())->delete();
            $totalDeleted += $deletedCount;
            Log::info("Deleted {$deletedCount} content items");
        });

        return $totalDeleted;
    }

    /**
     * Trigger the webhook job if a webhook URL is set.
     */
    private function triggerWebhook(int $updatedCount, int $createdCount, int $deletedCount, int $totalFiles): void
    {
        if ($this->webhookUrl) {
            dispatch(new WebhookTriggerJob($this->webhookUrl, $this->type, [
                'files_processed' => $totalFiles,
                'entries_updated' => $updatedCount,
                'entries_created' => $createdCount,
                'entries_deleted' => $deletedCount,
            ]));
            Log::info("Webhook job dispatched for type: {$this->type}");
        }
    }

    /**
     * Get the job configuration.
     *
     * @return array The job configuration
     */
    public function getJobConfig(): array
    {
        return [
            'type' => $this->type,
            'diskName' => $this->diskName,
            'shouldPull' => $this->shouldPull,
            'skipIfNoChanges' => $this->skipIfNoChanges,
            'webhookUrl' => $this->webhookUrl,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("EntrySyncJob failed for type {$this->type}: {$exception->getMessage()}");
        Log::error($exception->getTraceAsString());

        if ($this->webhookUrl) {
            dispatch(new WebhookTriggerJob($this->webhookUrl, $this->type, [
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]));
        }
    }
}
