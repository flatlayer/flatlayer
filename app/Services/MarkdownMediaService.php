<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\HasMedia;

class MarkdownMediaService
{
    public function handleMediaLibrary(HasMedia $model, array $data, string $filename): void
    {
        $mediaCollections = $model->getRegisteredMediaCollections();
        foreach ($mediaCollections as $collection) {
            $collectionName = $collection->name;
            if (isset($data[$collectionName])) {
                $mediaItems = is_array($data[$collectionName]) ? $data[$collectionName] : [$data[$collectionName]];

                $existingMedia = $model->getMedia($collectionName)->keyBy('file_name');
                $newMediaItems = [];

                foreach ($mediaItems as $mediaItem) {
                    $mediaPath = $this->resolveMediaPath($mediaItem, $filename);
                    $fileName = basename($mediaPath);

                    if (isset($existingMedia[$fileName])) {
                        // File already exists in the collection, keep it
                        $newMediaItems[] = $existingMedia[$fileName];
                        $existingMedia->forget($fileName);
                    } else {
                        // New file, add it to the collection
                        if (filter_var($mediaItem, FILTER_VALIDATE_URL)) {
                            $newMediaItems[] = $model->addMediaFromUrl($mediaItem)
                                ->toMediaCollection($collectionName);
                        } else {
                            $newMediaItems[] = $model->addMedia($mediaPath)
                                ->preservingOriginal()
                                ->toMediaCollection($collectionName);
                        }
                    }
                }

                // Remove any media items that are no longer in the markdown
                foreach ($existingMedia as $mediaItem) {
                    $mediaItem->delete();
                }

                // Update the media order if necessary
                $model->clearMediaCollection($collectionName);
                foreach ($newMediaItems as $mediaItem) {
                    $mediaItem->move($model, $collectionName);
                }
            }
        }
    }

    public function processMarkdownImages(HasMedia $model, string $markdownContent, string $filename): string
    {
        $pattern = '/!\[(.*?)\]\((.*?)\)/';
        return preg_replace_callback($pattern, function ($matches) use ($model, $filename) {
            $altText = $matches[1];
            $imagePath = $matches[2];

            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // Leave external URLs as they are
                return $matches[0];
            }

            $fullImagePath = $this->resolveMediaPath($imagePath, $filename);

            if (File::exists($fullImagePath)) {
                $media = $model->addMedia($fullImagePath)
                    ->preservingOriginal()
                    ->toMediaCollection('images');

                return "![{$altText}]({$media->getUrl()})";
            }

            // If the file doesn't exist, leave the original markdown as is
            return $matches[0];
        }, $markdownContent);
    }

    protected function resolveMediaPath(string $mediaItem, string $markdownFilename): string
    {
        if (filter_var($mediaItem, FILTER_VALIDATE_URL)) {
            return $mediaItem;
        }

        $fullPath = dirname($markdownFilename) . '/' . $mediaItem;
        return File::exists($fullPath) ? $fullPath : $mediaItem;
    }
}
