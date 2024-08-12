<?php

namespace App\Services;

use App\Exceptions\ImageDimensionException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageTransformationService
{
    public function __construct(
        private readonly ImageManager $manager = new ImageManager(new Driver)
    ) {}

    /**
     * Transform an image based on the given parameters.
     */
    public function transformImage(string $imagePath, array $params): string
    {
        $image = $this->manager->read($imagePath);

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

    public function getImageMetadata(string $imagePath): array
    {
        $image = $this->manager->read($imagePath);
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
     * @param string $extension
     * @return string
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
}
