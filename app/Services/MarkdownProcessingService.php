<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Webuni\FrontMatter\FrontMatter;

class MarkdownProcessingService
{
    public function __construct(
        protected readonly ImageService $imageProcessingService
    ) {}

    /**
     * Process a markdown file and extract its content and metadata.
     */
    public function processMarkdownFile(string $filename, string $type, array $fillable = []): array
    {
        $content = File::get($filename);
        $frontMatter = new FrontMatter;
        $document = $frontMatter->parse($content);

        $data = $document->getData();
        $markdownContent = $document->getContent();

        [$title, $markdownContent] = $this->extractTitleFromContent($markdownContent);
        $processedData = $this->processFrontMatter($data, [...$fillable, 'tags']);
        $processedData['attributes']['title'] ??= $title;

        return [
            ...$processedData['attributes'],
            'type' => $type,
            'slug' => $this->generateSlugFromFilename($filename),
            'content' => $markdownContent,
            'filename' => $filename,
            'meta' => $processedData['meta'] ?? [],
            'images' => $processedData['images'] ?? [],
        ];
    }

    /**
     * Process front matter data.
     */
    public function processFrontMatter(array $data, array $fillable = []): array
    {
        $processed = [
            'attributes' => [],
            'meta' => [],
            'images' => [],
        ];

        foreach ($data as $key => $value) {
            match (true) {
                str_starts_with($key, 'images.') => $processed['images'][Str::after($key, 'images.')] = $value,
                in_array($key, $fillable) => $processed['attributes'][$key] = $value,
                default => Arr::set($processed['meta'], $key, $value)
            };
        }

        return $processed;
    }

    /**
     * Handle media specified in front matter.
     */
    public function handleMediaFromFrontMatter(Entry $entry, array $images, string $filename): void
    {
        foreach ($images as $collectionName => $imagePaths) {
            $fullPaths = collect(Arr::wrap($imagePaths))
                ->map(fn ($path) => $this->resolveMediaPath($path, $filename))
                ->filter(fn ($path) => File::exists($path))
                ->values();

            $this->syncImagesCollection($entry, $fullPaths, $collectionName);
        }
    }

    /**
     * Process images in markdown content.
     */
    public function processMarkdownImages(Entry $entry, string $markdownContent, string $filename): string
    {
        $pattern = '/!\[(.*?)\]\((.*?)\)/';
        $imagePaths = new Collection;

        $processedContent = preg_replace_callback($pattern, function ($matches) use ($filename, &$imagePaths) {
            [$fullMatch, $altText, $imagePath] = $matches;

            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                return $fullMatch;
            }

            $fullImagePath = $this->resolveMediaPath($imagePath, $filename);

            if (File::exists($fullImagePath)) {
                $imagePaths->push($fullImagePath);

                return "![{$altText}]({$fullImagePath})";
            }

            return $fullMatch;
        }, $markdownContent);

        $this->imageProcessingService->syncImages($entry, $imagePaths->all(), 'content');

        return $processedContent;
    }

    /**
     * Resolve the full path of a media item.
     */
    protected function resolveMediaPath(string $mediaItem, string $markdownFilename): string
    {
        $fullPath = dirname($markdownFilename).'/'.$mediaItem;

        return File::exists($fullPath) ? $fullPath : $mediaItem;
    }

    /**
     * Sync images for a specific collection.
     *
     * @param  Entry  $entry  The entry to sync images for
     * @param  Arrayable|array  $newImagePaths  The new image paths to sync
     * @param  string  $collectionName  The name of the image collection
     */
    protected function syncImagesCollection(Entry $entry, Arrayable|array $newImagePaths, string $collectionName): void
    {
        $newImagePaths = Collection::make($newImagePaths);
        $existingImages = $entry->getImages($collectionName);
        $existingPaths = $existingImages->pluck('path');

        $pathsToAdd = $newImagePaths->diff($existingPaths);
        $pathsToRemove = $existingPaths->diff($newImagePaths);

        // Add new images
        $pathsToAdd->each(fn ($path) => $this->addImageToEntry($entry, $path, $collectionName));

        // Remove images that are no longer present
        $existingImages->whereIn('path', $pathsToRemove)->each->delete();
    }

    /**
     * Add an image to an entry.
     */
    protected function addImageToEntry(Entry $entry, string $path, string $collectionName): void
    {
        if (method_exists($entry, 'addImage')) {
            $entry->addImage($path, $collectionName);
        }
    }

    /**
     * Extract title from content.
     *
     * @return array{0: string|null, 1: string} An array containing the title and remaining content
     */
    protected function extractTitleFromContent(string $content): array
    {
        $lines = explode("\n", $content);
        $firstLine = trim($lines[0]);

        if (str_starts_with($firstLine, '# ')) {
            $title = trim(substr($firstLine, 2));

            return [$title, trim(implode("\n", array_slice($lines, 1)))];
        }

        return [null, $content];
    }

    /**
     * Generate a slug from a filename.
     */
    public function generateSlugFromFilename(string $filename): string
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
    }
}
