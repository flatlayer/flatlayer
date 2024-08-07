<?php

namespace App\Models;

use App\Services\MediaProcessingService;
use App\Services\ResponsiveImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_type',
        'model_id',
        'collection',
        'filename',
        'path',
        'mime_type',
        'size',
        'dimensions',
        'custom_properties',
        'thumbhash',
    ];

    protected $casts = [
        'size' => 'integer',
        'dimensions' => 'array',
        'custom_properties' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($media) {
            if (empty($media->filename) && !empty($media->path)) {
                $media->filename = basename($media->path);
            }
        });
    }

    public function model()
    {
        return $this->morphTo();
    }

    public static function addMediaToModel($model, string $path, string $collectionName = 'default', array $fileInfo = null): self
    {
        $mediaProcessingService = app(MediaProcessingService::class);
        return $mediaProcessingService->addMediaToModel($model, $path, $collectionName, $fileInfo);
    }

    public static function syncMedia($model, array $filenames, string $collectionName = 'default'): void
    {
        $mediaProcessingService = app(MediaProcessingService::class);
        $mediaProcessingService->syncMedia($model, $filenames, $collectionName);
    }

    public static function updateOrCreateMedia($model, string $fullPath, string $collectionName = 'default'): self
    {
        $mediaProcessingService = app(MediaProcessingService::class);
        return $mediaProcessingService->updateOrCreateMedia($model, $fullPath, $collectionName);
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

    public function getImgTag(array $sizes, array $attributes = []): string
    {
        $service = app(ResponsiveImageService::class);

        $defaultAttributes = [
            'alt' => $this->custom_properties['alt'] ?? '',
            'data-thumbhash' => $this->thumbhash,
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return $service->generateImgTag($this, $sizes, $attributes);
    }

    public function getUrl(array $transforms = []): string
    {
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        $route = route('media.transform', ['id' => $this->id, 'extension' => $extension]);

        if (!empty($transforms)) {
            $queryString = http_build_query($transforms);
            $route .= '?' . $queryString;
        }

        if (config('flatlayer.media.use_signatures', true)) {
            return URL::signedRoute('media.transform', array_merge(
                ['id' => $this->id, 'extension' => $extension],
                $transforms
            ));
        }

        return $route;
    }
}
