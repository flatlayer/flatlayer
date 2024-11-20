<?php

namespace App\Services\Media;

use App\Exceptions\ImageDimensionException;
use App\Services\Storage\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageTransformer
{
    protected readonly MediaStorage $storage;

    public function __construct(
        Filesystem $disk,
        private readonly ImageManager $manager = new ImageManager(new Driver)
    ) {
        $this->storage = new MediaStorage(
            resolver: app(StorageResolver::class),
            type: 'default',
            disk: $disk
        );
    }

    /**
     * Transform an image based on the given parameters.
     *
     * @param  string  $path  Relative path within the disk
     * @param  array  $params  Transformation parameters
     *
     * @throws ImageDimensionException
     */
    public function transformImage(string $path, array $params): string
    {
        if (! $this->storage->exists($path)) {
            throw new \RuntimeException("Image not found: {$path}");
        }

        // Read the image content from storage
        $image = $this->manager->read($this->storage->get($path));

        $this->applyTransformations($image, $params);

        return $this->encodeImage($image, $params);
    }

    /**
     * Apply transformations to the image.
     */
    private function applyTransformations(\Intervention\Image\Image $image, array $params): void
    {
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        ['requestedWidth' => $requestedWidth, 'requestedHeight' => $requestedHeight] = $this->getRequestedDimensions($params);

        $this->validateDimensions($originalWidth, $originalHeight, $requestedWidth, $requestedHeight);

        if ($requestedWidth && $requestedHeight) {
            $image->cover($requestedWidth, $requestedHeight);
        } elseif ($requestedWidth) {
            $image->scale(width: $requestedWidth);
        } elseif ($requestedHeight) {
            $image->scale(height: $requestedHeight);
        }
    }

    /**
     * Encode and optimize the image.
     */
    private function encodeImage(\Intervention\Image\Image $image, array $params): string
    {
        $format = $params['fm'] ?? 'jpg';
        $quality = (int) ($params['q'] ?? 90);

        return match ($format) {
            'png' => $image->toPng(),
            'webp' => $image->toWebp($quality),
            'gif' => $image->toGif(),
            default => $image->toJpeg($quality),
        };
    }

    /**
     * Get requested dimensions from params.
     */
    private function getRequestedDimensions(array $params): array
    {
        return [
            'requestedWidth' => isset($params['w']) ? (int) $params['w'] : null,
            'requestedHeight' => isset($params['h']) ? (int) $params['h'] : null,
        ];
    }

    /**
     * Validate the dimensions of the image transformation request.
     *
     * @throws ImageDimensionException
     */
    private function validateDimensions(int $originalWidth, int $originalHeight, ?int $requestedWidth, ?int $requestedHeight): void
    {
        $maxWidth = Config::get('flatlayer.images.max_width', 8192);
        $maxHeight = Config::get('flatlayer.images.max_height', 8192);

        $outputDimensions = $this->calculateOutputDimensions($originalWidth, $originalHeight, $requestedWidth, $requestedHeight);

        if ($outputDimensions['width'] > $maxWidth) {
            throw new ImageDimensionException("Resulting width ({$outputDimensions['width']}px) would exceed the maximum allowed width ({$maxWidth}px)");
        }

        if ($outputDimensions['height'] > $maxHeight) {
            throw new ImageDimensionException("Resulting height ({$outputDimensions['height']}px) would exceed the maximum allowed height ({$maxHeight}px)");
        }
    }

    /**
     * Calculate output dimensions.
     */
    private function calculateOutputDimensions(int $originalWidth, int $originalHeight, ?int $requestedWidth, ?int $requestedHeight): array
    {
        if ($requestedWidth === null && $requestedHeight === null) {
            return ['width' => $originalWidth, 'height' => $originalHeight];
        }

        $aspectRatio = $originalWidth / $originalHeight;

        $width = $requestedWidth ?? ($requestedHeight ? round($requestedHeight * $aspectRatio) : $originalWidth);
        $height = $requestedHeight ?? ($requestedWidth ? round($requestedWidth / $aspectRatio) : $originalHeight);

        return ['width' => (int) $width, 'height' => (int) $height];
    }

    /**
     * Get image metadata.
     *
     * @param  string  $path  Relative path within the disk
     *
     * @throws \RuntimeException If the image cannot be read
     */
    public function getImageMetadata(string $path): array
    {
        if (! $this->storage->exists($path)) {
            throw new \RuntimeException("Image not found: {$path}");
        }

        $image = $this->manager->read($this->storage->get($path));

        return [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /**
     * Create an HTTP response for an image.
     */
    public function createImageResponse(string $imageData, string $format): Response
    {
        $contentType = $this->getContentType($format);

        return new Response($imageData, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($imageData),
            'Cache-Control' => 'public, max-age=31536000',
            'Etag' => md5($imageData),
        ]);
    }

    /**
     * Get the content type for a given file extension.
     */
    private function getContentType(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    /**
     * Check if a path exists in the storage.
     */
    public function exists(string $path): bool
    {
        return $this->storage->exists($path);
    }

    /**
     * Get the size of an image file.
     */
    public function getSize(string $path): ?int
    {
        return $this->storage->exists($path) ? $this->storage->size($path) : null;
    }

    /**
     * Get the mime type of an image file.
     */
    public function getMimeType(string $path): ?string
    {
        return $this->storage->exists($path) ? $this->storage->mimeType($path) : null;
    }
}
