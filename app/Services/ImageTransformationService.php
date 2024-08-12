<?php

namespace App\Services;

use App\Exceptions\ImageDimensionException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Config;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class ImageTransformationService
{
    private const CACHE_PREFIX = 'image_last_access:';

    public function __construct(
        private readonly ImageManager $manager = new ImageManager(new Driver),
        private readonly string $diskName = 'public'
    ) {}

    /**
     * Transform an image based on the given parameters.
     *
     * @param  string  $imagePath
     * @param  array  $params
     * @return string
     */
    public function transformImage(string $imagePath, array $params): string
    {
        $image = $this->manager->read($imagePath);

        $this->applyTransformations($image, $params);

        return $this->encodeAndOptimize($image, $params, $imagePath);
    }

    /**
     * Apply transformations to the image.
     *
     * @param  \Intervention\Image\Image  $image
     * @param  array  $params
     */
    private function applyTransformations(\Intervention\Image\Image $image, array $params): void
    {
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        ['requestedWidth' => $requestedWidth, 'requestedHeight' => $requestedHeight] = $this->getRequestedDimensions($params);

        $this->validateDimensions($originalWidth, $originalHeight, $requestedWidth, $requestedHeight);

        match (true) {
            $requestedWidth && $requestedHeight => $image->cover($requestedWidth, $requestedHeight),
            $requestedWidth => $image->scale(width: $requestedWidth),
            $requestedHeight => $image->scale(height: $requestedHeight),
            default => null,
        };
    }

    /**
     * Encode and optimize the image.
     *
     * @param  \Intervention\Image\Image  $image
     * @param  array  $params
     * @param  string  $imagePath
     * @return string
     */
    private function encodeAndOptimize(\Intervention\Image\Image $image, array $params, string $imagePath): string
    {
        $quality = (int) ($params['q'] ?? 90);
        $format = $params['fm'] ?? pathinfo($imagePath, PATHINFO_EXTENSION);

        $encoded = $this->encodeImage($image, $format, $quality);

        return $this->optimizeImage($encoded);
    }

    /**
     * Encode the image in the specified format.
     *
     * @param  \Intervention\Image\Image  $image
     * @param  string  $format
     * @param  int  $quality
     * @return string
     */
    private function encodeImage(\Intervention\Image\Image $image, string $format, int $quality): string
    {
        return match ($format) {
            'jpg', 'jpeg' => $image->toJpeg($quality),
            'png' => $image->toPng(),
            'webp' => $image->toWebp($quality),
            'gif' => $image->toGif(),
            default => $image->encode(),
        };
    }

    /**
     * Optimize the encoded image.
     *
     * @param  string  $encoded
     * @return string
     */
    private function optimizeImage(string $encoded): string
    {
        $optimizer = OptimizerChainFactory::create();
        $tempFile = tempnam(sys_get_temp_dir(), 'optimized_image');
        file_put_contents($tempFile, $encoded);
        $optimizer->optimize($tempFile);
        $optimizedImage = file_get_contents($tempFile);
        unlink($tempFile);

        return $optimizedImage;
    }

    /**
     * Get requested dimensions from params.
     *
     * @param  array  $params
     * @return array
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
     * @param  int  $originalWidth
     * @param  int  $originalHeight
     * @param  int|null  $requestedWidth
     * @param  int|null  $requestedHeight
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
     *
     * @param  int  $originalWidth
     * @param  int  $originalHeight
     * @param  int|null  $requestedWidth
     * @param  int|null  $requestedHeight
     * @return array
     */
    private function calculateOutputDimensions(int $originalWidth, int $originalHeight, ?int $requestedWidth, ?int $requestedHeight): array
    {
        // Return original dimensions if no resize requested
        if ($requestedWidth === null && $requestedHeight === null) {
            return ['width' => $originalWidth, 'height' => $originalHeight];
        }

        $aspectRatio = $originalWidth / $originalHeight;

        // Calculate new dimensions
        $width = $requestedWidth ?? ($requestedHeight ? round($requestedHeight * $aspectRatio) : $originalWidth);
        $height = $requestedHeight ?? ($requestedWidth ? round($requestedWidth / $aspectRatio) : $originalHeight);

        return ['width' => (int) $width, 'height' => (int) $height];
    }

    /**
     * Generate a cache key for the given image ID and parameters.
     *
     * @param  mixed  $id
     * @param  array  $params
     * @return string
     */
    public function generateCacheKey(mixed $id, array $params): string
    {
        ksort($params);
        $params = array_map(fn ($value) => is_numeric($value) ? (int) $value : $value, $params);

        return md5($id . serialize($params));
    }

    /**
     * Get the cache path for a given cache key and format.
     *
     * @param  string  $cacheKey
     * @param  string  $format
     * @return string
     */
    public function getCachePath(string $cacheKey, string $format): string
    {
        return "cache/images/{$cacheKey}.{$format}";
    }

    /**
     * Cache an image and update its last access time.
     *
     * @param  string  $cachePath
     * @param  string  $imageData
     */
    public function cacheImage(string $cachePath, string $imageData): void
    {
        Storage::disk($this->diskName)->put($cachePath, $imageData);
        $this->updateLastAccessTime($cachePath);
    }

    /**
     * Get a cached image if it exists and update its last access time.
     *
     * @param  string  $cachePath
     * @return string|null
     */
    public function getCachedImage(string $cachePath): ?string
    {
        if (Storage::disk($this->diskName)->exists($cachePath)) {
            $this->updateLastAccessTime($cachePath);
            return Storage::disk($this->diskName)->get($cachePath);
        }

        return null;
    }

    /**
     * Create an HTTP response for an image.
     *
     * @param  string  $imageData
     * @param  string  $format
     * @return \Illuminate\Http\Response
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
     * Get the content type for a given image format.
     *
     * @param  string  $format
     * @return string
     */
    private function getContentType(string $format): string
    {
        return match ($format) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    /**
     * Update the last access time for a cached image.
     *
     * @param  string  $cachePath
     */
    private function updateLastAccessTime(string $cachePath): void
    {
        Cache::put(self::CACHE_PREFIX . $cachePath, now()->timestamp);
    }
}

