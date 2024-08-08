<?php

namespace App\Services;

use App\Models\ContentItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Webuni\FrontMatter\FrontMatter;

class MarkdownContentProcessingService
{
    protected $imagePaths;

    public function __construct(protected MediaFileService $imageProcessingService)
    {
        $this->imagePaths = new Collection();
    }

    public function processMarkdownFile(string $filename, string $type, array $fillable = []): array
    {
        $content = file_get_contents($filename);

        $frontMatter = new FrontMatter();
        $document = $frontMatter->parse($content);

        $data = $document->getData();
        $markdownContent = $document->getContent();

        [$title, $markdownContent] = $this->extractTitleFromContent($markdownContent);
        $data = $this->processFrontMatter($data, array_merge($fillable, ['tags']));
        $data['attributes']['title'] = $data['attributes']['title'] ?? $title ?? null;

        return array_merge($data['attributes'], [
            'type' => $type,
            'slug' => $this->generateSlugFromFilename($filename),
            'content' => $markdownContent,
            'filename' => $filename,
            'meta' => $data['meta'] ?? [],
            'images' => $data['images'] ?? [],
        ]);
    }

    public function processFrontMatter(array $data, array $fillable = []): array
    {
        $processed = [
            'attributes' => [],
            'meta' => [],
            'images' => [],
        ];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'images.')) {
                $collectionName = Str::after($key, 'images.');
                $processed['images'][$collectionName] = $value;
            } elseif (in_array($key, $fillable)) {
                $processed['attributes'][$key] = $value;
            } else {
                $this->arraySet($processed['meta'], explode('.', $key), $value);
            }
        }

        return $processed;
    }

    public function handleMediaFromFrontMatter(ContentItem $contentItem, array $images, string $filename): void
    {
        foreach ($images as $collectionName => $imagePaths) {
            $imagePaths = Arr::wrap($imagePaths);
            foreach ($imagePaths as $imagePath) {
                $fullPath = $this->resolveMediaPath($imagePath, $filename);
                $this->imageProcessingService->addMediaToContentItem($contentItem, $fullPath, $collectionName);
            }
        }
    }

    public function processMarkdownImages(ContentItem $contentItem, string $markdownContent, string $filename): string
    {
        $pattern = '/!\[(.*?)\]\((.*?)\)/';
        $imagePaths = new Collection();

        $processedContent = preg_replace_callback($pattern, function ($matches) use ($filename, &$imagePaths) {
            $altText = $matches[1];
            $imagePath = $matches[2];

            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // Leave external URLs as they are
                return $matches[0];
            }

            $fullImagePath = $this->resolveMediaPath($imagePath, $filename);

            if (File::exists($fullImagePath)) {
                $imagePaths->push($fullImagePath);
                return "![{$altText}]({$fullImagePath})";
            }

            // If the file doesn't exist, leave the original markdown as is
            return $matches[0];
        }, $markdownContent);

        $this->imageProcessingService->syncMedia($contentItem, $imagePaths->toArray(), 'content');

        return $processedContent;
    }

    protected function resolveMediaPath(string $mediaItem, string $markdownFilename): string
    {
        $fullPath = dirname($markdownFilename) . '/' . $mediaItem;
        return File::exists($fullPath) ? $fullPath : $mediaItem;
    }

    protected function syncImagesCollection(ContentItem $contentItem, Collection $newImagePaths, string $collectionName): void
    {
        $existingMedia = $contentItem->getMedia($collectionName);
        $existingPaths = $existingMedia->pluck('path');

        // Determine which images to add, keep, and remove
        $pathsToAdd = $newImagePaths->diff($existingPaths);
        $pathsToRemove = $existingPaths->diff($newImagePaths);

        // Add new images
        foreach ($pathsToAdd as $path) {
            $this->addMediaToContentItem($contentItem, $path, $collectionName);
        }

        // Remove images that are no longer present
        $existingMedia->whereIn('path', $pathsToRemove)->each->delete();
    }

    protected function addMediaToContentItem(ContentItem $contentItem, string $path, string $collectionName): void
    {
        if (method_exists($contentItem, 'addMedia')) {
            $contentItem->addMedia($path)->toMediaCollection($collectionName);
        }
    }

    protected function arraySet(&$array, $keys, $value): void
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

    protected function extractTitleFromContent(string $content): array
    {
        $lines = explode("\n", $content);
        $firstLine = trim($lines[0]);

        if (str_starts_with($firstLine, '# ')) {
            $title = trim(substr($firstLine, 2));
            array_shift($lines);
            return [$title, trim(implode("\n", $lines))];
        }

        return [null, $content];
    }

    public function generateSlugFromFilename(string $filename): string
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
    }
}
