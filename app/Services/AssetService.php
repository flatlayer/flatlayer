<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Thumbhash\Thumbhash;
use function Thumbhash\extract_size_and_pixels_with_gd;
use function Thumbhash\extract_size_and_pixels_with_imagick;

class AssetService
{
    public function addAssetToModel(Entry $model, string $path, string $collectionName = 'default', array $fileInfo = null): Asset
    {
        $fileInfo = $fileInfo ?? $this->getFileInfo($path);

        return $model->assets()->create([
            'collection' => $collectionName,
            'filename' => basename($path),
            'path' => $path,
            'mime_type' => $fileInfo['mime_type'],
            'size' => $fileInfo['size'],
            'dimensions' => $fileInfo['dimensions'],
            'thumbhash' => $fileInfo['thumbhash'],
        ]);
    }

    public function syncAssets(Model $model, array $filenames, string $collectionName = 'default'): void
    {
        $existingMedia = $model->assets()->where('collection', $collectionName)->get()->keyBy('filename');
        $newFilenames = collect($filenames)->keyBy(function ($path) {
            return basename($path);
        });

        // Remove media that no longer exists in the new filenames
        $existingMedia->diffKeys($newFilenames)->each->delete();

        // Add or update media
        foreach ($newFilenames as $filename => $fullPath) {
            $fileInfo = $this->getFileInfo($fullPath);

            if ($existingMedia->has($filename)) {
                $media = $existingMedia->get($filename);
                $this->updateAssetIfNeeded($media, $fileInfo);
            } else {
                $this->addAssetToModel($model, $fullPath, $collectionName, $fileInfo);
            }
        }
    }

    public function updateOrCreateAsset(Model $model, string $fullPath, string $collectionName = 'default'): Asset
    {
        $fileInfo = $this->getFileInfo($fullPath);
        $filename = basename($fullPath);
        $existingMedia = $model->assets()->where('collection', $collectionName)->where('filename', $filename)->first();

        if ($existingMedia) {
            $this->updateAssetIfNeeded($existingMedia, $fileInfo);
            return $existingMedia;
        }

        return $this->addAssetToModel($model, $fullPath, $collectionName, $fileInfo);
    }

    protected function updateAssetIfNeeded(Asset $media, array $fileInfo): void
    {
        $needsUpdate = false;

        foreach (['size', 'dimensions', 'thumbhash', 'mime_type'] as $property) {
            if ($media->$property !== $fileInfo[$property]) {
                $media->$property = $fileInfo[$property];
                $needsUpdate = true;
            }
        }

        if ($needsUpdate) {
            $media->save();
        }
    }

    public function getFileInfo(string $path): array
    {
        $size = File::size($path);
        $mimeType = File::mimeType($path);
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

    public function generateThumbhash(string $path): string
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
