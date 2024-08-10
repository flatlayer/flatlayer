<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Illuminate\Http\Response;
use Carbon\Carbon;

class ImageTransformationService
{
    protected ImageManager $manager;
    protected OptimizerChain $optimizer;
    protected string $diskName = 'public';
    protected string $cachePrefix = 'image_last_access:';

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
        $this->optimizer = OptimizerChainFactory::create();
    }

    /**
     * Transform an image based on the given parameters.
     *
     * @param string $imagePath The path to the original image
     * @param array $params Transformation parameters (w, h, q, fm)
     * @return string The transformed image data
     */
    public function transformImage(string $imagePath, array $params): string
    {
        $image = $this->manager->read($imagePath);

        $originalWidth = $image->width();
        $originalHeight = $image->height();

        $requestedWidth = isset($params['w']) ? (int)$params['w'] : null;
        $requestedHeight = isset($params['h']) ? (int)$params['h'] : null;

        $this->validateDimensions($originalWidth, $originalHeight, $requestedWidth, $requestedHeight);

        if ($requestedWidth && $requestedHeight) {
            $image->cover($requestedWidth, $requestedHeight);
        } elseif ($requestedWidth) {
            $image->scale(width: $requestedWidth);
        } elseif ($requestedHeight) {
            $image->scale(height: $requestedHeight);
        }

        $quality = (int) ($params['q'] ?? 90);
        $format = $params['fm'] ?? pathinfo($imagePath, PATHINFO_EXTENSION);

        $encoded = match ($format) {
            'jpg', 'jpeg' => $image->toJpeg($quality),
            'png' => $image->toPng(),
            'webp' => $image->toWebp($quality),
            'gif' => $image->toGif(),
            default => $image->encode(),
        };

        $tempFile = tempnam(sys_get_temp_dir(), 'optimized_image');
        file_put_contents($tempFile, $encoded);
        $this->optimizer->optimize($tempFile);
        $optimizedImage = file_get_contents($tempFile);
        unlink($tempFile);

        return $optimizedImage;
    }

    /**
     * Validate the dimensions of the image transformation request.
     *
     * @param int $originalWidth The width of the original image
     * @param int $originalHeight The height of the original image
     * @param int|null $requestedWidth The requested width for the transformed image (null if not specified)
     * @param int|null $requestedHeight The requested height for the transformed image (null if not specified)
     * @throws \Exception If the resulting width or height would exceed the maximum allowed dimensions
     */
    private function validateDimensions(int $originalWidth, int $originalHeight, ?int $requestedWidth, ?int $requestedHeight): void
    {
        $maxWidth = config('flatlayer.images.max_width', 8192);
        $maxHeight = config('flatlayer.images.max_height', 8192);

        $originalAspectRatio = $originalWidth / $originalHeight;

        // Calculate the output dimensions
        if ($requestedWidth && $requestedHeight) {
            $outputWidth = $requestedWidth;
            $outputHeight = $requestedHeight;
        } elseif ($requestedWidth) {
            $outputWidth = $requestedWidth;
            $outputHeight = (int)round($requestedWidth / $originalAspectRatio);
        } elseif ($requestedHeight) {
            $outputHeight = $requestedHeight;
            $outputWidth = (int)round($requestedHeight * $originalAspectRatio);
        } else {
            $outputWidth = $originalWidth;
            $outputHeight = $originalHeight;
        }

        // Check if output dimensions exceed limits
        if ($outputWidth > $maxWidth) {
            throw new \Exception("Resulting width ({$outputWidth}px) would exceed the maximum allowed width ({$maxWidth}px)");
        }
        if ($outputHeight > $maxHeight) {
            throw new \Exception("Resulting height ({$outputHeight}px) would exceed the maximum allowed height ({$maxHeight}px)");
        }
    }

    /**
     * Generate a cache key for the given image ID and parameters.
     *
     * @param mixed $id The image ID
     * @param array $params Transformation parameters
     * @return string The generated cache key
     */
    public function generateCacheKey(mixed $id, array $params): string
    {
        ksort($params);
        $params = array_map(fn($value) => is_numeric($value) ? (int) $value : $value, $params);
        return md5($id . serialize($params));
    }

    /**
     * Get the cache path for a given cache key and format.
     *
     * @param string $cacheKey The cache key
     * @param string $format The image format
     * @return string The cache path
     */
    public function getCachePath(string $cacheKey, string $format): string
    {
        return 'cache/images/' . $cacheKey . '.' . $format;
    }

    /**
     * Cache an image and update its last access time.
     *
     * @param string $cachePath The path to cache the image
     * @param string $imageData The image data to cache
     */
    public function cacheImage(string $cachePath, string $imageData): void
    {
        Storage::disk($this->diskName)->put($cachePath, $imageData);
        $this->updateLastAccessTime($cachePath);
    }

    /**
     * Get a cached image if it exists and update its last access time.
     *
     * @param string $cachePath The path of the cached image
     * @return string|null The cached image data or null if not found
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
     * @param string $imageData The image data
     * @param string $format The image format
     * @return Response The HTTP response
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
     * @param string $format The image format
     * @return string The corresponding content type
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
     * @param string $cachePath The path of the cached image
     */
    private function updateLastAccessTime(string $cachePath): void
    {
        Cache::put($this->cachePrefix . $cachePath, now()->timestamp);
    }

    /**
     * Clear old cached images.
     *
     * @param int $days The number of days to consider an image as old
     * @return int The number of cleared cache entries
     */
    public function clearOldCache(int $days): int
    {
        $count = 0;
        $files = Storage::disk($this->diskName)->files('cache/images');
        $cutoffTime = now()->subDays($days)->timestamp;

        foreach ($files as $file) {
            $lastAccessed = Cache::get($this->cachePrefix . $file);

            if (!$lastAccessed || $lastAccessed < $cutoffTime) {
                Storage::disk($this->diskName)->delete($file);
                Cache::forget($this->cachePrefix . $file);
                $count++;
            }
        }

        return $count;
    }
}
