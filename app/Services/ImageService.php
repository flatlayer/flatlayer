<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Thumbhash\Thumbhash;

use function Thumbhash\extract_size_and_pixels_with_gd;
use function Thumbhash\extract_size_and_pixels_with_imagick;

class ImageService
{
    /**
     * Add an image to a model.
     */
    public function addImageToModel(Entry $model, string $path, string $collectionName = 'default', ?array $fileInfo = null): Image
    {
        try {
            $fileInfo = $fileInfo ?? $this->getFileInfo($path);
        } catch (RuntimeException $e) {
            // Log the error
            Log::error('Failed to get file info: '.$e->getMessage());

            // Rethrow the exception or handle it as appropriate for your application
            throw $e;
        }

        return $model->images()->create([
            'collection' => $collectionName,
            'filename' => basename($path),
            'path' => $path,
            'mime_type' => $fileInfo['mime_type'],
            'size' => $fileInfo['size'],
            'dimensions' => $fileInfo['dimensions'],
            'thumbhash' => $fileInfo['thumbhash'],
        ]);
    }

    /**
     * Synchronize images for a model.
     */
    public function syncImages(Model $model, array $filenames, string $collectionName = 'default'): void
    {
        $existingMedia = $model->images()->where('collection', $collectionName)->get()->keyBy('filename');
        $newFilenames = collect($filenames)->keyBy(fn (string $path): string => basename($path));

        // Remove media that no longer exists in the new filenames
        $existingMedia->diffKeys($newFilenames)->each->delete();

        // Add or update media
        foreach ($newFilenames as $filename => $fullPath) {
            $fileInfo = $this->getFileInfo($fullPath);

            if ($existingMedia->has($filename)) {
                $media = $existingMedia->get($filename);
                $this->updateImageIfNeeded($media, $fileInfo);
            } else {
                $this->addImageToModel($model, $fullPath, $collectionName, $fileInfo);
            }
        }
    }

    /**
     * Update or create an image for a model.
     */
    public function updateOrCreateImage(Model $model, string $fullPath, string $collectionName = 'default'): Image
    {
        $fileInfo = $this->getFileInfo($fullPath);
        $filename = basename($fullPath);
        $existingMedia = $model->images()->where('collection', $collectionName)->where('filename', $filename)->first();

        if ($existingMedia) {
            $this->updateImageIfNeeded($existingMedia, $fileInfo);

            return $existingMedia;
        }

        return $this->addImageToModel($model, $fullPath, $collectionName, $fileInfo);
    }

    /**
     * Update image if needed based on file info.
     */
    protected function updateImageIfNeeded(Image $media, array $fileInfo): void
    {
        $needsUpdate = false;

        foreach (['size', 'dimensions', 'thumbhash', 'mime_type'] as $property) {
            if ($fileInfo[$property] !== $media->$property) {
                $media->$property = $fileInfo[$property];
                $needsUpdate = true;
            }
        }

        if ($needsUpdate) {
            $media->save();
        }
    }

    /**
     * Get file information.
     *
     * @param  string  $path  The path to the image file
     * @return array{size: int, mime_type: string, dimensions: array{width: int|null, height: int|null}, thumbhash: string}
     *
     * @throws \RuntimeException If there's an error processing the file
     */
    public function getFileInfo(string $path): array
    {
        if (! file_exists($path) || ! is_readable($path)) {
            throw new RuntimeException("File does not exist or is not readable: $path");
        }

        try {
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
        } catch (\Exception $e) {
            throw new RuntimeException("Error processing file $path: ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get image dimensions of an image file.
     *
     * @param  string  $path  The path to the image file
     * @return array{width: int|null, height: int|null}
     */
    protected function getImageDimensions(string $path): array
    {
        $imageSize = getimagesize($path);

        return [
            'width' => $imageSize[0] ?? null,
            'height' => $imageSize[1] ?? null,
        ];
    }

    /**
     * Generate thumbhash for an image file.
     *
     * @param  string  $path  The path to the image file
     */
    public function generateThumbhash(string $path): string
    {
        if (extension_loaded('imagick')) {
            return $this->generateThumbhashWithImagick($path);
        } else {
            return $this->generateThumbhashWithGd($path);
        }
    }

    /**
     * Generate thumbhash using Imagick.
     */
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

    /**
     * Generate thumbhash using GD.
     */
    protected function generateThumbhashWithGd(string $path): string
    {
        $imageManager = new ImageManager(new Driver);
        $image = $imageManager->read($path);
        $image->scale(width: 100);
        $resizedImage = $image->toJpeg(quality: 85);

        [$width, $height, $pixels] = extract_size_and_pixels_with_gd((string) $resizedImage);
        $hash = Thumbhash::RGBAToHash($width, $height, $pixels);

        return Thumbhash::convertHashToString($hash);
    }
}
