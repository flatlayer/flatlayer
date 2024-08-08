<?php

namespace App\Traits;

use App\Models\Asset;
use App\Services\AssetService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

trait HasAssets
{
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function addAsset(string $path, string $collectionName = 'default'): Asset
    {
        $service = app(AssetService::class);
        return $service->addAssetToModel($this, $path, $collectionName);
    }

    public function getAssets(string $collectionName = 'default'): Collection
    {
        return $this->assets()->where('collection', $collectionName)->get();
    }

    public function clearAssetCollection(string $collectionName = 'default'): self
    {
        $this->assets()->where('collection', $collectionName)->delete();
        return $this;
    }

    public function syncAssets(array $filenames, string $collectionName = 'default'): void
    {
        $service = app(AssetService::class);
        $service->syncAssets($this, $filenames, $collectionName);
    }

    public function updateOrCreateAsset(string $fullPath, string $collectionName = 'default'): Asset
    {
        $service = app(AssetService::class);
        return $service->updateOrCreateAsset($this, $fullPath, $collectionName);
    }
}
