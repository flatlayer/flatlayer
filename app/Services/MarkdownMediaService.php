<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class MarkdownMediaService
{
    protected $imagePaths;

    public function __construct()
    {
        $this->imagePaths = new Collection();
    }

    public function handleMediaFromFrontMatter(Model $model, array $data, string $filename): void
    {
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'image_')) {
                $collectionName = Str::after($key, 'image_');
                $fullPath = $this->resolveMediaPath($value, $filename);
                $this->addMediaToModel($model, $fullPath, $collectionName);
            }
        }
    }

    public function processMarkdownImages(Model $model, string $markdownContent, string $filename, string $collectionName = 'images'): string
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

        $this->syncImagesCollection($model, $imagePaths, $collectionName);

        return $processedContent;
    }

    protected function resolveMediaPath(string $mediaItem, string $markdownFilename): string
    {
        $fullPath = dirname($markdownFilename) . '/' . $mediaItem;
        return File::exists($fullPath) ? $fullPath : $mediaItem;
    }

    protected function syncImagesCollection(Model $model, Collection $newImagePaths, string $collectionName): void
    {
        $existingMedia = $model->getMedia($collectionName);
        $existingPaths = $existingMedia->pluck('path');

        // Determine which images to add, keep, and remove
        $pathsToAdd = $newImagePaths->diff($existingPaths);
        $pathsToRemove = $existingPaths->diff($newImagePaths);

        // Add new images
        foreach ($pathsToAdd as $path) {
            $this->addMediaToModel($model, $path, $collectionName);
        }

        // Remove images that are no longer present
        $existingMedia->whereIn('path', $pathsToRemove)->each->delete();
    }

    protected function addMediaToModel(Model $model, string $path, string $collectionName): void
    {
        if (method_exists($model, 'addMedia')) {
            $model->addMedia($path, $collectionName);
        }
    }
}
