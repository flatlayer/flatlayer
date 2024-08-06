<?php

namespace App\Models;

use App\Services\ResponsiveImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Thumbhash\Thumbhash;
use function Thumbhash\extract_size_and_pixels_with_gd;

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
        $fileInfo = $fileInfo ?? self::getFileInfo($path);

        return $model->media()->create([
            'collection' => $collectionName,
            'filename' => basename($path),
            'path' => $path,
            'mime_type' => $fileInfo['mime_type'],
            'size' => $fileInfo['size'],
            'dimensions' => $fileInfo['dimensions'],
            'thumbhash' => $fileInfo['thumbhash'],
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
            $fileInfo = self::getFileInfo($fullPath);

            if ($existingMedia->has($fullPath)) {
                $media = $existingMedia->get($fullPath);
                if ($media->size !== $fileInfo['size'] || $media->dimensions !== $fileInfo['dimensions'] || $media->thumbhash !== $fileInfo['thumbhash']) {
                    $media->update([
                        'size' => $fileInfo['size'],
                        'dimensions' => $fileInfo['dimensions'],
                        'thumbhash' => $fileInfo['thumbhash'],
                    ]);
                }
            } else {
                self::addMediaToModel($model, $fullPath, $collectionName, $fileInfo);
            }
        }
    }

    public static function updateOrCreateMedia($model, string $fullPath, string $collectionName = 'default'): self
    {
        $fileInfo = self::getFileInfo($fullPath);
        $existingMedia = $model->media()
            ->where('collection', $collectionName)
            ->where('path', $fullPath)
            ->first();

        if ($existingMedia) {
            $existingMedia->update([
                'size' => $fileInfo['size'],
                'dimensions' => $fileInfo['dimensions'],
                'thumbhash' => $fileInfo['thumbhash'],
                'mime_type' => $fileInfo['mime_type'],
            ]);
            return $existingMedia;
        }

        return self::addMediaToModel($model, $fullPath, $collectionName, $fileInfo);
    }

    protected static function getFileInfo(string $path): array
    {
        $size = filesize($path);
        $mimeType = mime_content_type($path);
        $dimensions = self::getImageDimensions($path);
        $thumbhash = self::generateThumbhash($path);

        return [
            'size' => $size,
            'mime_type' => $mimeType,
            'dimensions' => $dimensions,
            'thumbhash' => $thumbhash,
        ];
    }

    protected static function getImageDimensions(string $path): array
    {
        $imageSize = getimagesize($path);
        return [
            'width' => $imageSize[0] ?? null,
            'height' => $imageSize[1] ?? null,
        ];
    }

    protected static function generateThumbhash(string $path): string
    {
        $imageManager = new ImageManager(new Driver());
        $image = $imageManager->read($path);
        $image->scale(width: 100);
        $resizedImage = $image->toJpeg(quality: 85);

        [$width, $height, $pixels] = extract_size_and_pixels_with_gd((string)$resizedImage);
        $hash = Thumbhash::RGBAToHash($width, $height, $pixels);
        return Thumbhash::convertHashToString($hash);
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

    public function getImgTag(string $sizes, array $attributes = []): string
    {
        $service = app(ResponsiveImageService::class);

        $defaultAttributes = [
            'alt' => $this->custom_properties['alt'] ?? '',
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
