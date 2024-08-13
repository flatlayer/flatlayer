<?php

namespace App\Jobs;

use App\Models\Entry;
use CzProject\GitPhp\Git;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * @param  string  $pattern  The file pattern to match (default: '*.md')
     * @param  bool  $shouldPull  Whether to pull latest changes from Git (default: false)
     * @param  bool  $skipIfNoChanges  Whether to skip processing if no changes detected (default: false)
     * @param  string|null  $webhookUrl  The URL to trigger after sync completion (default: null)
     */
    public function __construct(
        protected string $path,
        protected string $type,
        protected string $pattern = '*.md',
        protected bool $shouldPull = false,
        protected bool $skipIfNoChanges = false,
        protected ?string $webhookUrl = null
    ) {}

    /**
     * Execute the job.
     *
     * @param  Git  $git  The Git instance for repository operations
     */
    public function handle(Git $git): void
    {
        Log::info("Starting content sync for type: {$this->type}");

        $changesDetected = true;
        if ($this->shouldPull) {
            $changesDetected = $this->pullLatestChanges($git);
            if (! $changesDetected && $this->skipIfNoChanges) {
                Log::info('No changes detected and skipIfNoChanges is true. Skipping sync.');
                return;
            }
        }

        $fullPattern = $this->path.'/'.$this->pattern;

        Log::info("Scanning directory: {$fullPattern}");
        $files = File::glob($fullPattern, GLOB_BRACE);
        Log::info('Found '.count($files).' files to process');

        $existingSlugs = Entry::where('type', $this->type)->pluck('slug')->flip();
        $processedSlugs = [];
        $updatedCount = 0;
        $createdCount = 0;

        foreach ($files as $file) {
            $slug = $this->getSlugFromFilename($file);
            $processedSlugs[] = $slug;

            try {
                $item = Entry::syncFromMarkdown($file, $this->type, true);
                if ($existingSlugs->has($slug)) {
                    $updatedCount++;
                    Log::info("Updated content item: {$slug}");
                } else {
                    $createdCount++;
                    Log::info("Created new content item: {$slug}");
                }
            } catch (\Exception $e) {
                Log::error("Error processing file {$file}: ".$e->getMessage());
            }
        }

        $deletedCount = $this->deleteRemovedEntries($existingSlugs, $processedSlugs);

        Log::info("Content sync completed for type: {$this->type}");

        $this->triggerWebhook($updatedCount, $createdCount, $deletedCount, count($files));
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

            $beforeHash = $repo->getLastCommitId()->toString();
            Log::info("Current commit hash before pull: {$beforeHash}");

            $repo->pull();
            Log::info('Pull completed successfully');

            $afterHash = $repo->getLastCommitId()->toString();
            Log::info("Current commit hash after pull: {$afterHash}");

            $changesDetected = $beforeHash !== $afterHash;
            Log::info($changesDetected ? 'Changes detected during pull' : 'No changes detected during pull');

            return $changesDetected;
        } catch (\Exception $e) {
            Log::error('Error during Git pull: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get a slug from a filename.
     *
     * @param  string  $filename  The filename to process
     * @return string The generated slug
     */
    private function getSlugFromFilename(string $filename): string
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
    }

    /**
     * Delete entries that no longer have corresponding files.
     *
     * @param  \Illuminate\Support\Collection  $existingSlugs
     * @param  array  $processedSlugs
     * @return int  The number of deleted entries
     */
    private function deleteRemovedEntries(\Illuminate\Support\Collection $existingSlugs, array $processedSlugs): int
    {
        $slugsToDelete = $existingSlugs->diffKeys(array_flip($processedSlugs));
        $deleteCount = $slugsToDelete->count();
        Log::info("Deleting {$deleteCount} content items that no longer have corresponding files");

        $totalDeleted = 0;
        $slugsToDelete->chunk(self::CHUNK_SIZE)->each(function ($chunk) use (&$totalDeleted) {
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
            'pattern' => $this->pattern,
            'shouldPull' => $this->shouldPull,
            'skipIfNoChanges' => $this->skipIfNoChanges,
            'webhookUrl' => $this->webhookUrl,
        ];
    }
}
