<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Str;

class ResponsiveImageService
{
    protected array $breakpoints = [
        'sm' => 640,
        'md' => 768,
        'lg' => 1024,
        'xl' => 1280,
        '2xl' => 1536,
    ];

    public function generateImgTag(Media $media, string $sizes, array $attributes = [], ?array $initialSize = null): string
    {
        $parsedSizes = $this->parseSizes($sizes);
        $srcset = $this->generateSrcset($media, $parsedSizes, $initialSize);

        $defaultAttributes = $this->generateAttributes($media, $initialSize);
        $defaultAttributes['sizes'] = $sizes;
        $defaultAttributes['srcset'] = $srcset;

        $mergedAttributes = array_merge($defaultAttributes, $attributes);

        return $this->buildImgTag($mergedAttributes);
    }

    protected function parseSizes(string $sizes): array
    {
        $parsed = [];
        $parts = explode(',', $sizes);
        foreach ($parts as $part) {
            $part = trim($part);
            if (Str::contains($part, '(')) {
                [$condition, $size] = explode(')', $part);
                $condition = trim($condition, '( ');
                $size = trim($size);
            } else {
                $condition = 'default';
                $size = $part;
            }
            $parsed[$condition] = $size;
        }
        return $parsed;
    }

    protected function generateSrcset(Media $media, array $parsedSizes, ?array $initialSize): string
    {
        $srcset = [];
        $originalWidth = $initialSize['width'] ?? $media->getWidth() ?? 1920; // Fallback to a default width

        // Generate srcset based on breakpoints and parsed sizes
        foreach ($this->breakpoints as $breakpoint => $width) {
            if ($width <= $originalWidth) {
                $relevantSize = $this->findRelevantSize($parsedSizes, $width);
                $calculatedWidth = $this->calculateWidth($relevantSize, $width);
                $srcset[] = $media->getSignedUrl(['w' => $calculatedWidth]) . " {$calculatedWidth}w";
            }
        }

        // Add the original size
        $srcset[] = $media->getSignedUrl() . " {$originalWidth}w";

        return implode(', ', array_unique($srcset));
    }

    protected function findRelevantSize(array $parsedSizes, int $breakpointWidth): string
    {
        foreach ($parsedSizes as $condition => $size) {
            if ($condition === 'default') continue;

            if (Str::contains($condition, 'min-width')) {
                $minWidth = (int) filter_var($condition, FILTER_SANITIZE_NUMBER_INT);
                if ($breakpointWidth >= $minWidth) {
                    return $size;
                }
            }
        }

        return $parsedSizes['default'] ?? '100vw';
    }

    protected function calculateWidth(string $size, int $breakpointWidth): int
    {
        if (Str::endsWith($size, 'vw')) {
            $percentage = (int) $size;
            return intval($breakpointWidth * $percentage / 100);
        } elseif (Str::endsWith($size, 'px')) {
            return (int) $size;
        }

        return $breakpointWidth;
    }

    protected function generateAttributes(Media $media, ?array $initialSize): array
    {
        $attributes = [
            'src' => $media->getSignedUrl(),
            'alt' => $media->custom_properties['alt'] ?? '',
        ];

        if ($initialSize) {
            $attributes['width'] = $initialSize['width'];
            if (isset($initialSize['height'])) {
                $attributes['height'] = $initialSize['height'];
            }
        } else {
            $width = $media->getWidth();
            $height = $media->getHeight();
            if ($width && $height) {
                $attributes['width'] = $width;
                $attributes['height'] = $height;
            }
        }

        return $attributes;
    }

    protected function buildImgTag(array $attributes): string
    {
        $attributeString = '';
        foreach ($attributes as $key => $value) {
            $attributeString .= " {$key}=\"" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\"";
        }
        return "<img{$attributeString}>";
    }
}
