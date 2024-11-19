<?php

namespace App\Services;

use App\Models\Entry;
use App\Support\Path;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EntrySyncService
{
    /**
     * The chunk size for bulk operations.
     */
    private const CHUNK_SIZE = 100;

    public function __construct(
        protected readonly Git $git,
        protected readonly FileDiscoveryService $fileDiscovery,
        protected readonly DiskResolver $diskResolver
    ) {}

    /**
     * Perform content synchronization.
     *
     * @param  string  $type  Content type to sync
     * @param  string|array|Filesystem|null  $disk  The disk to use:
     *                                              - string: Name of an existing disk
     *                                              - array: Configuration for Storage::build()
     *                                              - Filesystem: Use directly
     *                                              - null: Get using type
     * @param  bool  $shouldPull  Whether to pull latest changes from Git
     * @param  bool  $skipIfNoChanges  Whether to skip processing if no changes detected
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
        bool $skipIfNoChanges = false
    ): array {
        Log::info("Starting content sync for type: {$type}");

        // Get the disk using the resolver
        $disk = $this->diskResolver->resolve($disk, $type);

        // Handle Git operations if needed and disk is local
        $changesDetected = true;
        if ($shouldPull && $this->isLocalDisk($disk)) {
            $changesDetected = $this->handleGitPull($disk);
            if (! $changesDetected && $skipIfNoChanges) {
                Log::info('No changes detected and skipIfNoChanges is true. Skipping sync.');

                return [
                    'files_processed' => 0,
                    'entries_updated' => 0,
                    'entries_created' => 0,
                    'entries_deleted' => 0,
                    'skipped' => true,
                ];
            }
        }

        return $this->processContent($type, $disk);
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
            $timeout = config('flatlayer.git.timeout', 60);

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
    protected function processContent(string $type, Filesystem $disk): array
    {
        Log::info('Starting content file processing');

        // Find all markdown files using the disk
        $files = $this->fileDiscovery->findFiles($disk);
        Log::info('Found '.$files->count().' files to process');

        $existingSlugs = Entry::where('type', $type)->pluck('slug')->flip();
        $processedSlugs = [];
        $updatedCount = 0;
        $createdCount = 0;

        foreach ($files as $relativePath => $file) {
            try {
                $slug = Path::toSlug($relativePath);
                $processedSlugs[] = $slug;

                // Process the file using the disk
                $item = Entry::syncFromMarkdown($disk, $relativePath, $type, true);

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

        $deletedCount = $this->deleteRemovedEntries($type, $existingSlugs, $processedSlugs);
        Log::info("Content sync completed for type: {$type}");

        return [
            'files_processed' => $files->count(),
            'entries_updated' => $updatedCount,
            'entries_created' => $createdCount,
            'entries_deleted' => $deletedCount,
            'skipped' => false,
        ];
    }

    /**
     * Delete entries that no longer have corresponding files.
     *
     * @return int The number of deleted entries
     */
    private function deleteRemovedEntries(string $type, Collection $existingSlugs, array $processedSlugs): int
    {
        $slugsToDelete = $existingSlugs->diffKeys(array_flip($processedSlugs));
        $deleteCount = $slugsToDelete->count();
        Log::info("Deleting {$deleteCount} content items that no longer have corresponding files");

        $totalDeleted = 0;
        $batchSize = config('flatlayer.sync.batch_size', self::CHUNK_SIZE);

        $slugsToDelete->chunk($batchSize)->each(function ($chunk) use (&$totalDeleted, $type) {
            $deletedCount = Entry::where('type', $type)->whereIn('slug', $chunk->keys())->delete();
            $totalDeleted += $deletedCount;
            Log::info("Deleted {$deletedCount} content items");
        });

        return $totalDeleted;
    }
}
