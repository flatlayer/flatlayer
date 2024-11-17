<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Image;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Thumbhash\Thumbhash;

use function Thumbhash\extract_size_and_pixels_with_gd;
use function Thumbhash\extract_size_and_pixels_with_imagick;

class ImageService
{
    public function __construct(
        private readonly ImageManager $imageManager = new ImageManager(new Driver)
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
    public function syncImagesForEntry(Entry $entry, Arrayable|array $imagePaths, string $collectionName): void
    {
        $imagePaths = collect($imagePaths)->map(function ($path) use ($entry) {
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
     * Synchronize content images for an entry.
     */
    public function syncContentImages(Entry $entry, string $content, string $basePath): string
    {
        $pattern = '/!\[(.*?)\]\((.*?)(?:\s+"(.*?)")?\)/';
        $usedImages = [];

        return preg_replace_callback($pattern, function ($matches) use ($entry, $basePath, &$usedImages) {
            [, $alt, $src, $title] = array_pad($matches, 4, null);

            if (filter_var($src, FILTER_VALIDATE_URL)) {
                return $matches[0];
            }

            $fullPath = $this->resolveMediaPath($src, $basePath);
            if (!Storage::exists($this->getRelativePath($fullPath))) {
                return $matches[0];
            }

            $image = $this->syncImage($entry, $fullPath, 'content');
            $usedImages[] = $image->id;

            return $this->generateResponsiveImageTag($image, $alt, $title);
        }, $content);
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
        $relativePath = $this->getRelativePath($path);

        if (!Storage::exists($relativePath)) {
            throw new RuntimeException("File does not exist or is not readable: $path");
        }

        try {
            return [
                'size' => Storage::size($relativePath),
                'mime_type' => Storage::mimeType($relativePath),
                'dimensions' => $this->getImageDimensions(Storage::path($relativePath)),
                'thumbhash' => $this->generateThumbhash(Storage::path($relativePath)),
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

    /**
     * Resolve the full path of a media item relative to the content file.
     */
    public function resolveMediaPath(string $mediaItem, string $contentPath): string
    {
        // Convert paths to relative format
        $mediaItem = $this->getRelativePath($mediaItem);
        $contentPath = $this->getRelativePath($contentPath);

        // If the media item exists directly, return its full path
        if (Storage::exists($mediaItem)) {
            return Storage::path($mediaItem);
        }

        // Get the directory containing the content file
        $contentDir = dirname($contentPath);

        // Build relative path
        $relativePath = trim($contentDir . '/' . $mediaItem, '/');

        if (!Storage::exists($relativePath)) {
            throw new RuntimeException(sprintf(
                "Media file not found: %s (resolved from %s relative to %s)",
                $mediaItem,
                $relativePath,
                $contentPath
            ));
        }

        return Storage::path($relativePath);
    }

    /**
     * Find the root directory of the content repository.
     */
    protected function findContentRoot(string $startDir): string
    {
        $currentDir = $this->getRelativePath($startDir);
        while ($currentDir !== '') {
            if (Storage::exists($currentDir . '/.git')) {
                return Storage::path($currentDir);
            }
            $currentDir = dirname($currentDir);
            if ($currentDir === '.') {
                $currentDir = '';
            }
        }

        return Storage::path($startDir);
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

    /**
     * Convert a path to a relative path for storage operations.
     */
    protected function getRelativePath(string $path): string
    {
        // Remove storage path prefix if present
        $storagePath = Storage::path('');
        if (str_starts_with($path, $storagePath)) {
            return substr($path, strlen($storagePath));
        }

        // Normalize path
        return trim($path, '/');
    }
}
