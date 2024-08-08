<?php

namespace App\Jobs;

use App\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use CzProject\GitPhp\Git;

class EntrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const CHUNK_SIZE = 100;

    public function __construct(
        protected string $path,
        protected string $type,
        protected string $pattern = '*.md',
        protected bool $shouldPull = false,
        protected bool $skipIfNoChanges = false
    ) {}

    public function handle(Git $git)
    {
        Log::info("Starting content sync for type: {$this->type}");

        $changesDetected = true;
        if ($this->shouldPull) {
            $changesDetected = $this->pullLatestChanges($git);
            if (!$changesDetected && $this->skipIfNoChanges) {
                Log::info("No changes detected and skipIfNoChanges is true. Skipping sync.");
                return;
            }
        }

        $fullPattern = $this->path . '/' . $this->pattern;

        Log::info("Scanning directory: {$fullPattern}");
        $files = File::glob($fullPattern, GLOB_BRACE);
        Log::info("Found " . count($files) . " files to process");

        $existingSlugs = Entry::where('type', $this->type)->pluck('slug')->flip();
        $processedSlugs = [];

        foreach ($files as $file) {
            $slug = $this->getSlugFromFilename($file);
            $processedSlugs[] = $slug;

            try {
                $item = Entry::syncFromMarkdown($file, $this->type, true);
                Log::info($existingSlugs->has($slug) ? "Updated content item: {$slug}" : "Created new content item: {$slug}");
            } catch (\Exception $e) {
                Log::error("Error processing file {$file}: " . $e->getMessage());
            }
        }

        $slugsToDelete = $existingSlugs->diffKeys(array_flip($processedSlugs));
        $deleteCount = $slugsToDelete->count();
        Log::info("Deleting {$deleteCount} content items that no longer have corresponding files");

        $slugsToDelete->chunk(self::CHUNK_SIZE)->each(function ($chunk) {
            $deletedCount = Entry::where('type', $this->type)->whereIn('slug', $chunk->keys())->delete();
            Log::info("Deleted {$deletedCount} content items");
        });

        Log::info("Content sync completed for type: {$this->type}");
    }

    protected function pullLatestChanges(Git $git): bool
    {
        try {
            $repo = $git->open($this->path);
            Log::info("Opened Git repository at: {$this->path}");

            $beforeHash = $repo->getLastCommitId()->toString();
            Log::info("Current commit hash before pull: {$beforeHash}");

            $repo->pull();
            Log::info("Pull completed successfully");

            $afterHash = $repo->getLastCommitId()->toString();
            Log::info("Current commit hash after pull: {$afterHash}");

            $changesDetected = $beforeHash !== $afterHash;
            Log::info($changesDetected ? "Changes detected during pull" : "No changes detected during pull");

            return $changesDetected;
        } catch (\Exception $e) {
            Log::error("Error during Git pull: " . $e->getMessage());
            return false;
        }
    }

    private function getSlugFromFilename($filename)
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
    }

    public function getJobConfig(): array
    {
        return [
            'type' => $this->type,
            'path' => $this->path,
            'pattern' => $this->pattern,
            'shouldPull' => $this->shouldPull,
            'skipIfNoChanges' => $this->skipIfNoChanges,
        ];
    }
}
