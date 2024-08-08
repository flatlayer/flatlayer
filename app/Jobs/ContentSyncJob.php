<?php

namespace App\Jobs;

use App\Models\ContentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webuni\FrontMatter\FrontMatter;
use CzProject\GitPhp\Git;

class ContentSyncJob implements ShouldQueue
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

        $existingSlugs = ContentItem::withUnpublished()->where('type', $this->type)->pluck('slug')->flip();
        $processedSlugs = [];

        $frontMatter = new FrontMatter();

        foreach ($files as $file) {
            $slug = $this->getSlugFromFilename($file);
            $processedSlugs[] = $slug;

            $content = file_get_contents($file);
            $document = $frontMatter->parse($content);
            $data = $this->processFrontMatter($document->getData());
            $markdownContent = $document->getContent();

            if ($existingSlugs->has($slug)) {
                Log::info("Updating existing content item: {$slug}");
                $item = ContentItem::withUnpublished()->where('type', $this->type)->where('slug', $slug)->first();
                $this->updateContentItem($item, $data, $markdownContent, $file);
            } else {
                Log::info("Creating new content item: {$slug}");
                $this->createContentItem($slug, $data, $markdownContent, $file);
            }
        }

        $slugsToDelete = $existingSlugs->diffKeys(array_flip($processedSlugs));
        $deleteCount = $slugsToDelete->count();
        Log::info("Deleting {$deleteCount} content items that no longer have corresponding files");

        $slugsToDelete->chunk(self::CHUNK_SIZE)->each(function ($chunk) {
            $deletedCount = ContentItem::withUnpublished()->where('type', $this->type)->whereIn('slug', $chunk->keys())->delete();
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

    private function processFrontMatter(array $data)
    {
        $processed = [];
        foreach ($data as $key => $value) {
            $keys = explode('.', $key);
            $this->arraySet($processed, $keys, $value);
        }
        return $processed;
    }

    private function arraySet(&$array, $keys, $value)
    {
        $key = array_shift($keys);
        if (empty($keys)) {
            if (isset($array[$key]) && is_array($array[$key])) {
                $array[$key][] = $value;
            } else {
                $array[$key] = $value;
            }
        } else {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $this->arraySet($array[$key], $keys, $value);
        }
    }

    private function updateContentItem(ContentItem $item, array $data, string $markdownContent, string $file)
    {
        $item->title = $data['title'] ?? $item->title;
        $item->content = $markdownContent;
        $item->meta = array_merge($item->meta ?? [], $data);
        $item->save();

        $this->processImages($item, $data['images'] ?? [], $file);
    }

    private function createContentItem(string $slug, array $data, string $markdownContent, string $file)
    {
        $item = new ContentItem();
        $item->type = $this->type;
        $item->slug = $slug;
        $item->title = $data['title'] ?? '';
        $item->content = $markdownContent;
        $item->meta = $data;
        $item->save();

        $this->processImages($item, $data['images'] ?? [], $file);
    }

    private function processImages(ContentItem $item, array $images, string $file)
    {
        foreach ($images as $collection => $paths) {
            $paths = is_array($paths) ? $paths : [$paths];
            foreach ($paths as $path) {
                $fullPath = $this->resolveImagePath($path, $file);
                if (File::exists($fullPath)) {
                    $item->addMedia($fullPath)->toMediaCollection($collection);
                }
            }
        }
    }

    private function resolveImagePath(string $path, string $markdownFile)
    {
        return rtrim(dirname($markdownFile), '/') . '/' . ltrim($path, '/');
    }
}
