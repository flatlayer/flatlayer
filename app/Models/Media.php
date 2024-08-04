<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Media extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'collection_name',
        'path',
        'mime_type',
        'size',
        'dimensions',
        'custom_properties',
    ];

    protected $casts = [
        'size' => 'integer',
        'dimensions' => 'array',
        'custom_properties' => 'array',
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
        $dimensions = self::getImageDimensions($path);

        return $model->media()->create([
            'collection_name' => $collectionName,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
            'dimensions' => $dimensions,
        ]);
    }

    public static function syncMedia($model, array $filenames, string $collectionName = 'default'): void
    {
        $existingMedia = $model->getMedia($collectionName)->keyBy('path');
        $newFilenames = collect($filenames);

        // Remove media that no longer exists in the new filenames
        $existingMedia->whereNotIn('path', $newFilenames)->each->delete();

        // Add or update media
        foreach ($newFilenames as $fullPath) {
            $size = filesize($fullPath);
            $dimensions = self::getImageDimensions($fullPath);

            if ($existingMedia->has($fullPath)) {
                $media = $existingMedia->get($fullPath);
                if ($media->size !== $size || $media->dimensions !== $dimensions) {
                    $media->update([
                        'size' => $size,
                        'dimensions' => $dimensions,
                    ]);
                }
            } else {
                self::addMediaToModel($model, $fullPath, $collectionName);
            }
        }
    }

    public static function updateOrCreateMedia($model, string $fullPath, string $collectionName = 'default'): self
    {
        $size = filesize($fullPath);
        $dimensions = self::getImageDimensions($fullPath);
        $existingMedia = $model->getMedia($collectionName)->where('path', $fullPath)->first();

        if ($existingMedia) {
            $existingMedia->update([
                'size' => $size,
                'dimensions' => $dimensions,
            ]);
            return $existingMedia;
        }

        return self::addMediaToModel($model, $fullPath, $collectionName);
    }

    public function getSignedUrl(array $transforms = []): string
    {
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        $route = route('media.transform', ['id' => $this->id, 'extension' => $extension]);

        if (!empty($transforms)) {
            $queryString = http_build_query($transforms);
            $route .= '?' . $queryString;
        }

        return URL::signedRoute('media.transform', array_merge(
            ['id' => $this->id, 'extension' => $extension],
            $transforms
        ));
    }

    protected static function getImageDimensions(string $path): array
    {
        $imageSize = getimagesize($path);
        return [
            'width' => $imageSize[0] ?? null,
            'height' => $imageSize[1] ?? null,
        ];
    }

    public function getWidth(): ?int
    {
        return $this->dimensions['width'] ?? null;
    }

    public function getHeight(): ?int
    {
        return $this->dimensions['height'] ?? null;
    }

    public function getAspectRatio(): ?float
    {
        if ($this->getWidth() && $this->getHeight()) {
            return $this->getWidth() / $this->getHeight();
        }
        return null;
    }
}
