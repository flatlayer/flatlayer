<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Thumbhash\Thumbhash;
use function Thumbhash\extract_size_and_pixels_with_gd;
use function Thumbhash\extract_size_and_pixels_with_imagick;

class MediaProcessingService
{
    public function addMediaToModel(Model $model, string $path, string $collectionName = 'default', array $fileInfo = null): Media
    {
        $fileInfo = $fileInfo ?? $this->getFileInfo($path);

        return $model->media()->castAndCreate([
            'collection' => $collectionName,
            'filename' => basename($path),
            'path' => $path,
            'mime_type' => $fileInfo['mime_type'],
            'size' => $fileInfo['size'],
            'dimensions' => $fileInfo['dimensions'],
            'thumbhash' => $fileInfo['thumbhash'],
        ]);
    }

    public function syncMedia(Model $model, array $filenames, string $collectionName = 'default'): void
    {
        $existingMedia = $model->getMedia($collectionName)->keyBy('path');
        $newFilenames = collect($filenames);

        // Remove media that no longer exists in the new filenames
        $existingMedia->whereNotIn('path', $newFilenames)->each->delete();

        // Add or update media
        foreach ($newFilenames as $fullPath) {
            $fileInfo = $this->getFileInfo($fullPath);

            if ($existingMedia->has($fullPath)) {
                $media = $existingMedia->get($fullPath);
                if ($media->size !== $fileInfo['size'] || $media->dimensions !== $fileInfo['dimensions'] || $media->thumbhash !== $fileInfo['thumbhash']) {
                    $media->castAndUpdate([
                        'size' => $fileInfo['size'],
                        'dimensions' => $fileInfo['dimensions'],
                        'thumbhash' => $fileInfo['thumbhash'],
                    ]);
                }
            } else {
                $this->addMediaToModel($model, $fullPath, $collectionName, $fileInfo);
            }
        }
    }

    public function updateOrCreateMedia(Model $model, string $fullPath, string $collectionName = 'default'): Media
    {
        $fileInfo = $this->getFileInfo($fullPath);
        $existingMedia = $model->media()
            ->where('collection', $collectionName)
            ->where('path', $fullPath)
            ->first();

        if ($existingMedia) {
            $existingMedia->castAndUpdate([
                'size' => $fileInfo['size'],
                'dimensions' => $fileInfo['dimensions'],
                'thumbhash' => $fileInfo['thumbhash'],
                'mime_type' => $fileInfo['mime_type'],
            ]);
            return $existingMedia;
        }

        return $this->addMediaToModel($model, $fullPath, $collectionName, $fileInfo);
    }

    protected function getFileInfo(string $path): array
    {
        $size = filesize($path);
        $mimeType = mime_content_type($path);
        $dimensions = $this->getImageDimensions($path);
        $thumbhash = $this->generateThumbhash($path);

        return [
            'size' => $size,
            'mime_type' => $mimeType,
            'dimensions' => $dimensions,
            'thumbhash' => $thumbhash,
        ];
    }

    protected function getImageDimensions(string $path): array
    {
        $imageSize = getimagesize($path);
        return [
            'width' => $imageSize[0] ?? null,
            'height' => $imageSize[1] ?? null,
        ];
    }

    protected function generateThumbhash(string $path): string
    {
        if (extension_loaded('imagick')) {
            return $this->generateThumbhashWithImagick($path);
        } else {
            return $this->generateThumbhashWithGd($path);
        }
    }

    protected function generateThumbhashWithImagick(string $path): string
    {
        $imagick = new \Imagick($path);
        $imagick->resizeImage(100, 0, \Imagick::FILTER_LANCZOS, 1);
        $imagick->setImageFormat('png');
        $blob = $imagick->getImageBlob();

        [$width, $height, $pixels] = extract_size_and_pixels_with_imagick($blob);
        $hash = Thumbhash::RGBAToHash($width, $height, $pixels);
        return Thumbhash::convertHashToString($hash);
    }

    protected function generateThumbhashWithGd(string $path): string
    {
        $imageManager = new ImageManager(new Driver());
        $image = $imageManager->read($path);
        $image->scale(width: 100);
        $resizedImage = $image->toJpeg(quality: 85);

        [$width, $height, $pixels] = extract_size_and_pixels_with_gd((string)$resizedImage);
        $hash = Thumbhash::RGBAToHash($width, $height, $pixels);
        return Thumbhash::convertHashToString($hash);
    }
}
