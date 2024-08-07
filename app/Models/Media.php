<?php

namespace App\Models;

use App\Services\ResponsiveImageService;
use App\Services\MediaProcessingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use WendellAdriel\Lift\Lift;
use WendellAdriel\Lift\Attributes\Cast;
use WendellAdriel\Lift\Attributes\Column;
use WendellAdriel\Lift\Attributes\PrimaryKey;
use WendellAdriel\Lift\Attributes\Fillable;
use WendellAdriel\Lift\Attributes\Rules;

class Media extends Model
{
    use HasFactory, Lift;

    #[PrimaryKey]
    public int $id;

    #[Fillable]
    #[Rules(['required', 'string'])]
    public string $model_type;

    #[Fillable]
    #[Rules(['required', 'integer'])]
    public int $model_id;

    #[Fillable]
    #[Rules(['required', 'string'])]
    public string $collection;

    #[Fillable]
    #[Rules(['required', 'string'])]
    public string $filename;

    #[Fillable]
    #[Rules(['required', 'string'])]
    public string $path;

    #[Fillable]
    #[Rules(['nullable', 'string'])]
    public ?string $mime_type;

    #[Fillable]
    #[Rules(['nullable', 'string'])]
    public ?string $thumbhash;

    #[Fillable]
    #[Rules(['required', 'integer'])]
    #[Cast('integer')]
    public int $size;

    #[Fillable]
    #[Rules(['required', 'array'])]
    #[Cast('array')]
    public array $dimensions;

    #[Fillable]
    #[Rules(['nullable', 'array'])]
    #[Cast('array')]
    public ?array $custom_properties;

    #[Cast('datetime')]
    public $created_at;

    #[Cast('datetime')]
    public $updated_at;

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
        $service = app(MediaProcessingService::class);
        return $service->addMediaToModel($model, $path, $collectionName, $fileInfo);
    }

    public static function syncMedia($model, array $filenames, string $collectionName = 'default'): void
    {
        $service = app(MediaProcessingService::class);
        $service->syncMedia($model, $filenames, $collectionName);
    }

    public static function updateOrCreateMedia($model, string $fullPath, string $collectionName = 'default'): self
    {
        $service = app(MediaProcessingService::class);
        return $service->updateOrCreateMedia($model, $fullPath, $collectionName);
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
