<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'collection_name',
        'path',
        'mime_type',
        'size',
        'custom_properties',
        'filename',
    ];

    protected $casts = [
        'custom_properties' => 'array',
        'size' => 'integer',
    ];

    public function model()
    {
        return $this->morphTo();
    }

    public static function addMediaToModel($model, string $path, string $collectionName = 'default'): self
    {
        $filename = basename($path);
        $size = filesize($path);
        $mimeType = mime_content_type($path);

        return $model->media()->create([
            'collection_name' => $collectionName,
            'path' => $path,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
        ]);
    }

    public static function syncMedia($model, array $filenames, string $collectionName = 'default'): void
    {
        $existingMedia = $model->getMedia($collectionName)->keyBy('filename');
        $newFilenames = collect($filenames)->keyBy(function ($path) {
            return basename($path);
        });

        // Remove media that no longer exists in the new filenames
        $existingMedia->whereNotIn('filename', $newFilenames->keys())->each->delete();

        // Add or update media
        foreach ($newFilenames as $filename => $fullPath) {
            $size = filesize($fullPath);

            if ($existingMedia->has($filename)) {
                $media = $existingMedia->get($filename);
                if ($media->size !== $size || $media->path !== $fullPath) {
                    $media->delete();
                    self::addMediaToModel($model, $fullPath, $collectionName);
                }
            } else {
                self::addMediaToModel($model, $fullPath, $collectionName);
            }
        }
    }

    public static function updateOrCreateMedia($model, string $fullPath, string $collectionName = 'default'): self
    {
        $filename = basename($fullPath);
        $size = filesize($fullPath);
        $existingMedia = $model->getMedia($collectionName)->where('filename', $filename)->first();

        if ($existingMedia && $existingMedia->size === $size && $existingMedia->path === $fullPath) {
            return $existingMedia;
        }

        if ($existingMedia) {
            $existingMedia->delete();
        }

        return self::addMediaToModel($model, $fullPath, $collectionName);
    }
}
