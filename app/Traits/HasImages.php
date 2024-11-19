<?php

namespace App\Traits;

use App\Models\Image;
use App\Services\ImageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Trait HasImages
 *
 * This trait provides image management functionality to models.
 * It allows adding, retrieving, syncing, and managing images associated with a model.
 */
trait HasImages
{
    protected ?ImageService $imageService = null;

    protected ?Filesystem $imageDisk = null;

    /**
     * Get all images associated with the model.
     */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Set the disk to use for image operations.
     */
    public function useImageDisk(Filesystem|string $disk): self
    {
        $this->imageDisk = is_string($disk) ? Storage::disk($disk) : $disk;

        return $this;
    }

    /**
     * Get the disk being used for image operations.
     */
    protected function getImageDisk(): Filesystem
    {
        return $this->imageDisk ?? Storage::disk(config('flatlayer.images.disk', 'local'));
    }

    /**
     * Get the image service instance.
     */
    protected function getImageService(): ImageService
    {
        if ($this->imageService === null) {
            $this->imageService = new ImageService($this->getImageDisk());
        }

        return $this->imageService;
    }

    /**
     * Add an image to the model.
     */
    public function addImage(string $path, string $collectionName = 'default'): Image
    {
        return $this->getImageService()->addImageToModel($this, $path, $collectionName);
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
    public function syncImages(array $paths, string $collectionName = 'default'): void
    {
        $this->getImageService()->syncImagesForEntry($this, $paths, $collectionName);
    }

    /**
     * Update an existing image or create a new one.
     */
    public function updateOrCreateImage(string $path, string $collectionName = 'default'): Image
    {
        return $this->getImageService()->updateOrCreateImage($this, $path, $collectionName);
    }
}
