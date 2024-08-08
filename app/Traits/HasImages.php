<?php

namespace App\Traits;

use App\Models\Image;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

trait HasImages
{
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function addImage(string $path, string $collectionName = 'default'): Image
    {
        $service = app(ImageService::class);
        return $service->addImageToModel($this, $path, $collectionName);
    }

    public function getImages(string $collectionName = 'default'): Collection
    {
        return $this->images()->where('collection', $collectionName)->get();
    }

    public function clearImageCollection(string $collectionName = 'default'): self
    {
        $this->images()->where('collection', $collectionName)->delete();
        return $this;
    }

    public function syncImages(array $filenames, string $collectionName = 'default'): void
    {
        $service = app(ImageService::class);
        $service->syncImages($this, $filenames, $collectionName);
    }

    public function updateOrCreateImage(string $fullPath, string $collectionName = 'default'): Image
    {
        $service = app(ImageService::class);
        return $service->updateOrCreateImage($this, $fullPath, $collectionName);
    }
}
