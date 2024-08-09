<?php

namespace App\Traits;

use App\Models\Image;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

/**
 * Trait HasImages
 *
 * This trait provides image management functionality to models.
 * It allows adding, retrieving, syncing, and managing images associated with a model.
 */
trait HasImages
{
    /**
     * Get all images associated with the model.
     */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Add an image to the model.
     *
     * @param string $path The path to the image file
     * @param string $collectionName The name of the image collection
     * @return Image The created Image instance
     */
    public function addImage(string $path, string $collectionName = 'default'): Image
    {
        $service = app(ImageService::class);
        return $service->addImageToModel($this, $path, $collectionName);
    }

    /**
     * Get images from a specific collection.
     *
     * @param string $collectionName The name of the image collection
     * @return Collection Collection of Image instances
     */
    public function getImages(string $collectionName = 'default'): Collection
    {
        return $this->images()->where('collection', $collectionName)->get();
    }

    /**
     * Clear all images from a specific collection.
     *
     * @param string $collectionName The name of the image collection
     */
    public function clearImageCollection(string $collectionName = 'default'): self
    {
        $this->images()->where('collection', $collectionName)->delete();
        return $this;
    }

    /**
     * Sync images for a specific collection.
     *
     * @param array $filenames Array of image filenames to sync
     * @param string $collectionName The name of the image collection
     */
    public function syncImages(array $filenames, string $collectionName = 'default'): void
    {
        $service = app(ImageService::class);
        $service->syncImages($this, $filenames, $collectionName);
    }

    /**
     * Update an existing image or create a new one.
     *
     * @param string $fullPath The full path to the image file
     * @param string $collectionName The name of the image collection
     * @return Image The updated or created Image instance
     */
    public function updateOrCreateImage(string $fullPath, string $collectionName = 'default'): Image
    {
        $service = app(ImageService::class);
        return $service->updateOrCreateImage($this, $fullPath, $collectionName);
    }
}
