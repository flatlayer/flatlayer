<?php

namespace App\Traits;

use App\Models\MediaFile;
use App\Services\MediaFileService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Collection;

trait HasMediaFiles
{
    public function media(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'model');
    }

    public function addMedia(string $path, string $collectionName = 'default'): MediaFile
    {
        $service = app(MediaFileService::class);
        return $service->addMediaToModel($this, $path, $collectionName);
    }

    public function getMedia(string $collectionName = 'default'): Collection
    {
        return $this->media()->where('collection', $collectionName)->get();
    }

    public function getAllMedia(): Collection
    {
        return $this->media;
    }

    public function clearMediaCollection(string $collectionName = 'default'): self
    {
        $this->media()->where('collection', $collectionName)->delete();
        return $this;
    }

    public function syncMedia(array $filenames, string $collectionName = 'default'): void
    {
        $service = app(MediaFileService::class);
        $service->syncMedia($this, $filenames, $collectionName);
    }

    public function updateOrCreateMedia(string $fullPath, string $collectionName = 'default'): MediaFile
    {
        $service = app(MediaFileService::class);
        return $service->updateOrCreateMedia($this, $fullPath, $collectionName);
    }
}
