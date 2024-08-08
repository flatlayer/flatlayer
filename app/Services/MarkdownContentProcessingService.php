<?php

namespace App\Services;

use App\Models\ContentItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class MarkdownContentProcessingService
{
    protected $imagePaths;

    public function __construct()
    {
        $this->imagePaths = new Collection();
    }

    public function handleMediaFromFrontMatter(ContentItem $contentItem, array $data, string $filename): void
    {
        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $collectionName => $imagePaths) {
                $imagePaths = is_array($imagePaths) ? $imagePaths : [$imagePaths];
                foreach ($imagePaths as $imagePath) {
                    $fullPath = $this->resolveMediaPath($imagePath, $filename);
                    $this->addMediaToContentItem($contentItem, $fullPath, $collectionName);
                }
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

        $this->syncImagesCollection($contentItem, $imagePaths, 'content');

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

    public function processFrontMatter(array $data): array
    {
        $processed = [];
        foreach ($data as $key => $value) {
            $keys = explode('.', $key);
            $this->arraySet($processed, $keys, $value);
        }
        return $processed;
    }

    private function arraySet(&$array, $keys, $value): void
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
}
