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
                if ($collectionName === 'images') {
                    $this->imagePaths->push($fullPath);
                } else {
                    $this->addMediaToModel($model, $fullPath, $collectionName);
                }
            }
        }
    }

    public function processMarkdownImages(Model $model, string $markdownContent, string $filename): string
    {
        $pattern = '/!\[(.*?)\]\((.*?)\)/';
        $processedContent = preg_replace_callback($pattern, function ($matches) use ($filename) {
            $altText = $matches[1];
            $imagePath = $matches[2];

            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // Leave external URLs as they are
                return $matches[0];
            }

            $fullImagePath = $this->resolveMediaPath($imagePath, $filename);

            if (File::exists($fullImagePath)) {
                $this->imagePaths->push($fullImagePath);
                return "![{$altText}]({$fullImagePath})";
            }

            // If the file doesn't exist, leave the original markdown as is
            return $matches[0];
        }, $markdownContent);

        $this->syncImagesCollection($model);

        return $processedContent;
    }

    protected function resolveMediaPath(string $mediaItem, string $markdownFilename): string
    {
        $fullPath = dirname($markdownFilename) . '/' . $mediaItem;
        return File::exists($fullPath) ? $fullPath : $mediaItem;
    }

    protected function syncImagesCollection(Model $model): void
    {
        foreach ($this->imagePaths as $imagePath) {
            $this->addMediaToModel($model, $imagePath, 'images');
        }
        $this->imagePaths = new Collection(); // Reset for next use
    }

    protected function addMediaToModel(Model $model, string $path, string $collectionName): void
    {
        if (method_exists($model, 'addMedia')) {
            $model->addMedia($path, $collectionName);
        }
    }
}
