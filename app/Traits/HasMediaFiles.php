<?php

namespace App\Traits;

use App\Models\MediaFile;
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
        return MediaFile::addMediaToModel($this, $path, $collectionName);
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
        MediaFile::syncMedia($this, $filenames, $collectionName);
    }

    public function updateOrCreateMedia(string $fullPath, string $collectionName = 'default'): MediaFile
    {
        return MediaFile::updateOrCreateMedia($this, $fullPath, $collectionName);
    }
}
