<?php

namespace App\Services\Content;

use App\Models\Entry;
use App\Services\Storage\StorageResolver;
use App\Support\Path;
use Closure;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ContentSyncManager
{
    /**
     * The chunk size for bulk operations.
     */
    private const CHUNK_SIZE = 100;

    public function __construct(
        protected readonly Git $git,
        protected readonly ContentFileSystem $fileDiscovery,
        protected readonly StorageResolver $diskResolver
    ) {}

    /**
     * Perform content synchronization with optional progress reporting.
     *
     * @param  string  $type  Content type to sync
     * @param  string|array|Filesystem|null  $disk  The disk to use:
     *                                              - string: Name of an existing disk
     *                                              - array: Configuration for Storage::build()
     *                                              - Filesystem: Use directly
     *                                              - null: Get using type
     * @param  bool  $shouldPull  Whether to pull latest changes from Git
     * @param  bool  $skipIfNoChanges  Whether to skip processing if no changes detected
     * @param  Closure|null  $progressCallback  Optional callback for progress updates:
     *                                          function(string $message, ?int $current = null, ?int $total = null): void
     * @return array{
     *     files_processed: int,
     *     entries_updated: int,
     *     entries_created: int,
     *     entries_deleted: int,
     *     skipped: bool
     * }
     *
     * @throws GitException|\RuntimeException|\InvalidArgumentException
     */
    public function sync(
        string $type,
        string|array|Filesystem|null $disk = null,
        bool $shouldPull = false,
        bool $skipIfNoChanges = false,
        ?Closure $progressCallback = null
    ): array {
        $this->logProgress("Starting content sync for type: {$type}", $progressCallback);

        // Get the disk using the resolver
        $disk = $this->diskResolver->resolve($disk, $type);

        // Handle Git operations if needed and disk is local
        $changesDetected = true;
        if ($shouldPull && $this->isLocalDisk($disk)) {
            $this->logProgress('Checking Git repository for changes...', $progressCallback);
            $changesDetected = $this->handleGitPull($disk);

            if (! $changesDetected && $skipIfNoChanges) {
                $this->logProgress('No changes detected and skipIfNoChanges is true. Skipping sync.', $progressCallback);

                return [
                    'files_processed' => 0,
                    'entries_updated' => 0,
                    'entries_created' => 0,
                    'entries_deleted' => 0,
                    'skipped' => true,
                ];
            }
        }

        return $this->processContent($type, $disk, $progressCallback);
    }

    /**
     * Check if a disk is local by checking if it has a local path.
     */
    protected function isLocalDisk(Filesystem $disk): bool
    {
        try {
            $root = $disk->path('');

            return file_exists($root) && is_dir($root);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Handle Git pull operations for local repositories.
     *
     * @return bool True if changes were detected, false otherwise
     *
     * @throws GitException
     */
    protected function handleGitPull(Filesystem $disk): bool
    {
        $diskRoot = $disk->path('');

        try {
            $repo = $this->git->open($diskRoot);
            Log::info("Opened Git repository at: {$diskRoot}");

            // Configure authentication
            $this->configureGitAuth($repo);

            $beforeHash = $repo->getLastCommitId()->toString();
            Log::info("Current commit hash before pull: {$beforeHash}");

            // Get timeout for Git operations
            $timeout = Config::get('flatlayer.git.timeout', 60);

            // Pass timeout to pull command
            $repo->pull(['timeout' => $timeout]);
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
        $authMethod = Config::get('flatlayer.git.auth_method');

        try {
            // Only set identity if specifically configured
            $commitName = Config::get('flatlayer.git.commit_name');
            $commitEmail = Config::get('flatlayer.git.commit_email');

            if ($commitName && $commitEmail) {
                $repo->execute([
                    'config', 'user.name', $commitName
                ]);
                $repo->execute([
                    'config', 'user.email', $commitEmail
                ]);
            }

            // If no auth method specified, skip authentication setup
            if (!$authMethod) {
                Log::info('No authentication method configured, assuming public repository');
                return;
            }

            // Configure authentication if specified
            switch ($authMethod) {
                case 'token':
                    $username = Config::get('flatlayer.git.username');
                    $token = Config::get('flatlayer.git.token');

                    if ($username && $token) {
                        $repo->execute([
                            'config', 'credential.username', $username
                        ]);
                        $repo->execute([
                            'config', 'credential.helper', 'store'
                        ]);
                        Log::info("Git authentication configured using token for user: {$username}");
                    }
                    break;

                case 'ssh':
                    $sshKeyPath = Config::get('flatlayer.git.ssh_key_path');
                    if ($sshKeyPath && file_exists($sshKeyPath)) {
                        $repo->execute([
                            'config', 'core.sshCommand', "ssh -i {$sshKeyPath}"
                        ]);
                        Log::info('Git authentication configured using SSH key');
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
     *
     * @return array{
     *     files_processed: int,
     *     entries_updated: int,
     *     entries_created: int,
     *     entries_deleted: int,
     *     skipped: bool
     * }
     */
    protected function processContent(string $type, Filesystem $disk, ?Closure $progressCallback): array
    {
        $this->logProgress('Starting content file processing', $progressCallback);

        // Find all markdown files using the disk
        $files = $this->fileDiscovery->findFiles($disk);
        $this->logProgress('Found '.$files->count().' files to process', $progressCallback, 0, $files->count());

        $existingSlugs = Entry::where('type', $type)->pluck('slug')->flip();
        $processedSlugs = [];
        $updatedCount = 0;
        $createdCount = 0;
        $unchangedCount = 0;
        $processed = 0;

        foreach ($files as $relativePath => $file) {
            try {
                $slug = Path::toSlug($relativePath);
                $processedSlugs[] = $slug;

                // First sync without auto-save to check for changes
                $item = Entry::syncFromMarkdown($disk, $relativePath, $type, false);
                $isNew = ! $existingSlugs->has($slug);

                if ($isNew) {
                    $item->save();
                    $createdCount++;
                    $this->logProgress("Created new content item: {$slug}", $progressCallback, ++$processed, $files->count());
                } else {
                    // For existing items, check if we need to save
                    $originalItem = Entry::where('type', $type)->where('slug', $slug)->first();
                    $needsUpdate = false;

                    // Compare relevant fields
                    $fieldsToCompare = ['title', 'content', 'excerpt', 'meta', 'published_at'];
                    foreach ($fieldsToCompare as $field) {
                        if ($item->$field != $originalItem->$field) {
                            $needsUpdate = true;
                            break;
                        }
                    }

                    if ($needsUpdate) {
                        $item->save();
                        $updatedCount++;
                        $this->logProgress("Updated content item: {$slug}", $progressCallback, ++$processed, $files->count());
                    } else {
                        $unchangedCount++;
                        $this->logProgress("Skipped unchanged content item: {$slug}", $progressCallback, ++$processed, $files->count());
                    }
                }

            } catch (\Exception $e) {
                Log::error("Error processing file {$relativePath}: ".$e->getMessage());
                $this->logProgress("Error processing file: {$relativePath}", $progressCallback, ++$processed, $files->count());

                if (Config::get('flatlayer.sync.log_level') === 'debug') {
                    Log::debug($e->getTraceAsString());
                }
            }
        }

        $this->logProgress('Processing deletions...', $progressCallback);
        $deletedCount = $this->deleteRemovedEntries($type, $existingSlugs, $processedSlugs, $progressCallback);

        $this->logProgress("Content sync completed for type: {$type}", $progressCallback);

        return [
            'files_processed' => $files->count(),
            'entries_updated' => $updatedCount,
            'entries_created' => $createdCount,
            'entries_deleted' => $deletedCount,
            'entries_unchanged' => $unchangedCount,
            'skipped' => false,
        ];
    }

    /**
     * Delete entries that no longer have corresponding files.
     *
     * @return int The number of deleted entries
     */
    private function deleteRemovedEntries(
        string $type,
        Collection $existingSlugs,
        array $processedSlugs,
        ?Closure $progressCallback
    ): int {
        $slugsToDelete = $existingSlugs->diffKeys(array_flip($processedSlugs));
        $deleteCount = $slugsToDelete->count();

        if ($deleteCount === 0) {
            return 0;
        }

        $this->logProgress("Deleting {$deleteCount} content items that no longer have corresponding files", $progressCallback);

        $totalDeleted = 0;
        $batchSize = Config::get('flatlayer.sync.batch_size', self::CHUNK_SIZE);

        $slugsToDelete->chunk($batchSize)->each(function ($chunk) use (&$totalDeleted, $type, $deleteCount, $progressCallback) {
            $deletedCount = Entry::where('type', $type)->whereIn('slug', $chunk->keys())->delete();
            $totalDeleted += $deletedCount;
            $this->logProgress("Deleted {$totalDeleted} of {$deleteCount} content items", $progressCallback, $totalDeleted, $deleteCount);
        });

        return $totalDeleted;
    }

    /**
     * Log progress both to the logger and through the callback if provided.
     */
    protected function logProgress(string $message, ?Closure $progressCallback = null, ?int $current = null, ?int $total = null): void
    {
        Log::info($message);

        if ($progressCallback) {
            $progressCallback($message, $current, $total);
        }
    }
}
