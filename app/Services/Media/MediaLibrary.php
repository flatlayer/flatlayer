<?php

namespace App\Services\Media;

use App\Models\Entry;
use App\Models\Image;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Thumbhash\Thumbhash;
use function Thumbhash\extract_size_and_pixels_with_gd;
use function Thumbhash\extract_size_and_pixels_with_imagick;

class MediaLibrary
{
    protected readonly MediaStorage $storage;

    public function __construct(
        Filesystem $disk,
        private readonly ImageManager $imageManager = new ImageManager(new Driver),
    ) {
        $this->storage = new MediaStorage(
            resolver: app(StorageResolver::class),
            type: 'default',
            disk: $disk
        );
    }

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
     * Sync an image for a model.
     */
    public function syncImage(Entry $model, string $path, string $collectionName = 'default'): Image
    {
        $filename = basename($path);
        $image = $model->images()
            ->where('collection', $collectionName)
            ->where('filename', $filename)
            ->first();

        $fileInfo = $this->getFileInfo($path);

        if ($image) {
            $this->updateImageIfNeeded($image, $fileInfo);
        } else {
            $image = $this->addImageToModel($model, $path, $collectionName, $fileInfo);
        }

        return $image;
    }

    /**
     * Synchronize images for an entry.
     */
    public function syncImagesForEntry(Entry $entry, Arrayable|array $paths, string $collectionName): void
    {
        $imagePaths = collect($paths)->map(function ($path) use ($entry) {
            return $this->resolveMediaPath($path, $entry->filename);
        });

        $existingImages = $entry->getImages($collectionName);
        $existingPaths = $existingImages->pluck('path');

        $pathsToAdd = $imagePaths->diff($existingPaths);
        $pathsToRemove = $existingPaths->diff($imagePaths);

        $pathsToAdd->each(fn ($path) => $this->addImageToModel($entry, $path, $collectionName));
        $existingImages->whereIn('path', $pathsToRemove)->each->delete();
    }

    /**
     * Sync content images for an entry.
     */
    public function syncContentImages(
        Entry $entry,
        string $content,
        string $relativePath
    ): string {
        $pattern = '/!\[(.*?)\]\((.*?)(?:\s+"(.*?)")?\)/';
        $usedImages = [];

        return preg_replace_callback($pattern, function ($matches) use ($entry, $relativePath, &$usedImages) {
            [, $alt, $src, $title] = array_pad($matches, 4, null);

            if (filter_var($src, FILTER_VALIDATE_URL)) {
                return $matches[0];
            }

            try {
                $imagePath = $this->resolveMediaPath($src, $relativePath);

                if (!$this->storage->exists($imagePath)) {
                    return $matches[0];
                }

                $image = $this->syncImage($entry, $imagePath, 'content');
                $usedImages[] = $image->id;

                return $this->generateResponsiveImageTag($image, $alt, $title);
            } catch (RuntimeException $e) {
                return $matches[0]; // Keep original if image not found
            }
        }, $content);
    }

    /**
     * Update or create an image for a model.
     */
    public function updateOrCreateImage(Model $model, string $path, string $collectionName = 'default'): Image
    {
        $fileInfo = $this->getFileInfo($path);
        $filename = basename($path);

        $image = $model->images()
            ->where('collection', $collectionName)
            ->where('filename', $filename)
            ->first();

        if ($image) {
            $this->updateImageIfNeeded($image, $fileInfo);

            return $image;
        }

        return $this->addImageToModel($model, $path, $collectionName, $fileInfo);
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
        if (!$this->storage->exists($path)) {
            throw new RuntimeException("File does not exist or is not readable: $path");
        }

        try {
            $imageContents = $this->storage->get($path);
            $image = $this->imageManager->read($imageContents);

            return [
                'size' => $this->storage->size($path),
                'mime_type' => $this->storage->mimeType($path),
                'dimensions' => [
                    'width' => $image->width(),
                    'height' => $image->height(),
                ],
                'thumbhash' => $this->generateThumbhash($imageContents),
            ];
        } catch (\Exception $e) {
            throw new RuntimeException("Error processing file $path: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Generate thumbhash for image contents.
     */
    public function generateThumbhash(string $imageContents): string
    {
        return extension_loaded('imagick')
            ? $this->generateThumbhashWithImagick($imageContents)
            : $this->generateThumbhashWithGd($imageContents);
    }

    /**
     * Generate thumbhash using Imagick.
     */
    protected function generateThumbhashWithImagick(string $imageContents): string
    {
        $imagick = new \Imagick;
        $imagick->readImageBlob($imageContents);
        $imagick->resizeImage(100, 100, \Imagick::FILTER_LANCZOS, 1, true);
        $imagick->setImageFormat('png');

        [$width, $height, $pixels] = extract_size_and_pixels_with_imagick($imagick->getImageBlob());

        return Thumbhash::convertHashToString(Thumbhash::RGBAToHash($width, $height, $pixels));
    }

    /**
     * Generate thumbhash using GD.
     */
    protected function generateThumbhashWithGd(string $imageContents): string
    {
        $image = $this->imageManager->read($imageContents);
        $image->scale(width: 100);
        $resizedImage = $image->toJpeg(quality: 85);

        [$width, $height, $pixels] = extract_size_and_pixels_with_gd((string) $resizedImage);

        return Thumbhash::convertHashToString(Thumbhash::RGBAToHash($width, $height, $pixels));
    }

    /**
     * Resolve the media path relative to content file.
     */
    public function resolveMediaPath(string $mediaItem, string $contentPath): string
    {
        return $this->storage->resolveRelativePath($mediaItem, $contentPath);
    }

    /**
     * Generate a responsive image tag.
     */
    protected function generateResponsiveImageTag(Image $image, string $alt, ?string $title): string
    {
        $props = [
            'imageData' => json_encode($image->toArray()),
            'baseUrl' => json_encode(config('app.url')),
            'alt' => json_encode($alt),
        ];

        if ($title !== null) {
            $props['title'] = json_encode($title);
        }

        $encodedProps = implode(' ', array_map(
            fn ($key, $value) => $key.'={'.$value.'}',
            array_keys($props),
            $props
        ));

        return "<ResponsiveImage {$encodedProps} />";
    }
}
