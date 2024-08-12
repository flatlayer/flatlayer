<?php

namespace App\Traits;

use App\Models\Image;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     */
    public function addImage(string $path, string $collectionName = 'default'): Image
    {
        return app(ImageService::class)->addImageToModel($this, $path, $collectionName);
    }

    /**
     * Get images from a specific collection.
     */
    public function getImages(string $collectionName = 'default'): Collection
    {
        return $this->images()->where('collection', $collectionName)->get();
    }

    /**
     * Clear all images from a specific collection.
     */
    public function clearImageCollection(string $collectionName = 'default'): self
    {
        $this->images()->where('collection', $collectionName)->delete();
        return $this;
    }

    /**
     * Sync images for a specific collection.
     */
    public function syncImages(array $filenames, string $collectionName = 'default'): void
    {
        app(ImageService::class)->syncImages($this, $filenames, $collectionName);
    }

    /**
     * Update an existing image or create a new one.
     */
    public function updateOrCreateImage(string $fullPath, string $collectionName = 'default'): Image
    {
        return app(ImageService::class)->updateOrCreateImage($this, $fullPath, $collectionName);
    }
}
