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
    public function __construct(
        private readonly ImageManager $imageManager = new ImageManager(new Driver())
    ) {}

    /**
     * Add an image to a model.
     */
    public function addImageToModel(Entry $model, string $path, string $collectionName = 'default', ?array $fileInfo = null): Image
    {
        try {
            $fileInfo ??= $this->getFileInfo($path);
        } catch (RuntimeException $e) {
            Log::error("Failed to get file info: {$e->getMessage()}");
            throw $e;
        }

        return $model->images()->create([
            'collection' => $collectionName,
            'filename' => basename($path),
            'path' => $path,
            ...$fileInfo,
        ]);
    }

    /**
     * Synchronize images for a model.
     */
    public function syncImages(Model $model, array $filenames, string $collectionName = 'default'): void
    {
        $existingMedia = $model->images()->where('collection', $collectionName)->get()->keyBy('filename');
        $newFilenames = collect($filenames)->keyBy(fn (string $path): string => basename($path));

        $existingMedia->diffKeys($newFilenames)->each->delete();

        $newFilenames->each(function (string $fullPath, string $filename) use ($model, $collectionName, $existingMedia) {
            $fileInfo = $this->getFileInfo($fullPath);

            if ($existingMedia->has($filename)) {
                $this->updateImageIfNeeded($existingMedia->get($filename), $fileInfo);
            } else {
                $this->addImageToModel($model, $fullPath, $collectionName, $fileInfo);
            }
        });
    }

    /**
     * Update or create an image for a model.
     */
    public function updateOrCreateImage(Model $model, string $fullPath, string $collectionName = 'default'): Image
    {
        $fileInfo = $this->getFileInfo($fullPath);
        $filename = basename($fullPath);

        $image = $model->images()
            ->where('collection', $collectionName)
            ->where('filename', $filename)
            ->first();

        if ($image) {
            $this->updateImageIfNeeded($image, $fileInfo);
            return $image;
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
        if (!is_readable($path)) {
            throw new RuntimeException("File does not exist or is not readable: $path");
        }

        try {
            return [
                'size' => File::size($path),
                'mime_type' => File::mimeType($path),
                'dimensions' => $this->getImageDimensions($path),
                'thumbhash' => $this->generateThumbhash($path),
            ];
        } catch (\Exception $e) {
            throw new RuntimeException("Error processing file $path: {$e->getMessage()}", previous: $e);
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
        return extension_loaded('imagick')
            ? $this->generateThumbhashWithImagick($path)
            : $this->generateThumbhashWithGd($path);
    }

    /**
     * Generate thumbhash using Imagick.
     */
    protected function generateThumbhashWithImagick(string $path): string
    {
        $imagick = new \Imagick($path);
        $imagick->resizeImage(100, 0, \Imagick::FILTER_LANCZOS, 1);
        $imagick->setImageFormat('png');

        [$width, $height, $pixels] = extract_size_and_pixels_with_imagick($imagick->getImageBlob());
        return Thumbhash::convertHashToString(Thumbhash::RGBAToHash($width, $height, $pixels));
    }

    /**
     * Generate thumbhash using GD.
     */
    protected function generateThumbhashWithGd(string $path): string
    {
        $image = $this->imageManager->read($path);
        $image->scale(width: 100);
        $resizedImage = $image->toJpeg(quality: 85);

        [$width, $height, $pixels] = extract_size_and_pixels_with_gd((string) $resizedImage);
        return Thumbhash::convertHashToString(Thumbhash::RGBAToHash($width, $height, $pixels));
    }
}
