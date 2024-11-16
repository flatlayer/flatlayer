<?php

namespace App\Jobs;

use App\Models\Entry;
use App\Services\FileDiscoveryService;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Class EntrySyncJob
 *
 * This job synchronizes Markdown files with database entries.
 * It can pull latest changes from a Git repository, process files,
 * and optionally trigger a webhook after completion.
 */
class EntrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The chunk size for bulk operations.
     */
    private const CHUNK_SIZE = 100;

    /**
     * Create a new job instance.
     *
     * @param  string  $path  The path to the content directory
     * @param  string  $type  The type of content being synced
     * @param  bool  $shouldPull  Whether to pull latest changes from Git (default: false)
     * @param  bool  $skipIfNoChanges  Whether to skip processing if no changes detected (default: false)
     * @param  string|null  $webhookUrl  The URL to trigger after sync completion (default: null)
     */
    public function __construct(
        protected string $path,
        protected string $type,
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
     * @param  Git  $git  The Git instance for repository operations
     * @param  FileDiscoveryService  $fileDiscovery  The file discovery service
     */
    public function handle(Git $git, FileDiscoveryService $fileDiscovery): void
    {
        Log::info("Starting content sync for type: {$this->type}");

        try {
            $changesDetected = true;
            if ($this->shouldPull) {
                $changesDetected = $this->pullLatestChanges($git);
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
     * Configure Git authentication based on settings.
     *
     * @param  GitRepository  $repo  The Git repository instance
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
                    if ($sshKeyPath && File::exists($sshKeyPath)) {
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
     * Pull latest changes from the Git repository.
     *
     * @param  Git  $git  The Git instance
     * @return bool True if changes were detected, false otherwise
     */
    protected function pullLatestChanges(Git $git): bool
    {
        try {
            $repo = $git->open($this->path);
            Log::info("Opened Git repository at: {$this->path}");

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
     * Process content files and sync with database.
     */
    protected function processContent(FileDiscoveryService $fileDiscovery): void
    {
        Log::info("Scanning directory: {$this->path}");

        // Find all markdown files, sorted by directory depth
        $files = $fileDiscovery->findFiles($this->path);
        Log::info('Found '.$files->count().' files to process');

        $existingSlugs = Entry::where('type', $this->type)->pluck('slug')->flip();
        $processedSlugs = [];
        $updatedCount = 0;
        $createdCount = 0;

        // Create callback for slug existence checking
        $checkSlugExists = function (string $slug) use ($existingSlugs, $processedSlugs) {
            return $existingSlugs->has($slug) || in_array($slug, $processedSlugs);
        };

        $batchSize = config('flatlayer.sync.batch_size', self::CHUNK_SIZE);
        foreach ($files->chunk($batchSize) as $batch) {
            foreach ($batch as $relativePath => $file) {
                try {
                    // Generate the appropriate slug for this file
                    $desiredSlug = $fileDiscovery->generateSlug($relativePath, $checkSlugExists);

                    // If there's a conflict, resolve it
                    $slug = $fileDiscovery->resolveSlugConflict($desiredSlug, $checkSlugExists);
                    $processedSlugs[] = $slug;

                    // Process the file
                    $item = Entry::syncFromMarkdown($file->getPathname(), $this->type, true);

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
                    Log::error("Error processing file {$file->getPathname()}: ".$e->getMessage());
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
     *
     * @param  int  $updatedCount  Number of updated entries
     * @param  int  $createdCount  Number of created entries
     * @param  int  $deletedCount  Number of deleted entries
     * @param  int  $totalFiles  Total number of files processed
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
            'path' => $this->path,
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
