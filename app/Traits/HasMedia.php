<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Collection;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function addMedia(string $path, string $collectionName = 'default'): Media
    {
        return Media::addMediaToModel($this, $path, $collectionName);
    }

    public function getMedia(string $collectionName = 'default'): Collection
    {
        return $this->media()->where('collection_name', $collectionName)->get();
    }

    public function getAllMedia(): Collection
    {
        return $this->media;
    }

    public function clearMediaCollection(string $collectionName = 'default'): self
    {
        $this->media()->where('collection_name', $collectionName)->delete();
        return $this;
    }
}
