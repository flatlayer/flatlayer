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

    protected int $minWidth = 240;
    protected float $increment = 0.1; // 10% increment

    public function generateImgTag(Media $media, array $sizes, array $attributes = []): string
    {
        $parsedSizes = $this->parseSizes($sizes);
        $srcset = $this->generateSrcset($media, $parsedSizes);
        $sizesAttribute = $this->generateSizesAttribute($parsedSizes);

        $defaultAttributes = [
            'src' => $media->getSignedUrl(),
            'alt' => $media->custom_properties['alt'] ?? '',
            'sizes' => $sizesAttribute,
            'srcset' => $srcset,
        ];

        $mergedAttributes = array_merge($defaultAttributes, $attributes);

        return $this->buildImgTag($mergedAttributes);
    }

    protected function parseSizes(array $sizes): array
    {
        $parsed = [];
        foreach ($sizes as $size) {
            if (Str::contains($size, ':')) {
                [$breakpoint, $value] = explode(':', $size);
                $breakpoint = trim($breakpoint);
                $value = trim($value);
                if (isset($this->breakpoints[$breakpoint])) {
                    $parsed[$this->breakpoints[$breakpoint]] = $this->parseSize($value);
                }
            } else {
                $parsed[0] = $this->parseSize($size);
            }
        }
        ksort($parsed);
        return $parsed;
    }

    protected function parseSize(string $size): array
    {
        if (Str::endsWith($size, 'px')) {
            return ['type' => 'px', 'value' => (int) $size];
        } elseif (Str::endsWith($size, 'vw')) {
            return ['type' => 'vw', 'value' => (int) $size];
        } elseif (Str::contains($size, '-')) {
            $parts = explode('-', $size);
            return [
                'type' => 'calc',
                'vw' => (int) $parts[0],
                'px' => (int) trim($parts[1])
            ];
        }
        throw new \InvalidArgumentException("Invalid size format: $size");
    }

    protected function generateSrcset(Media $media, array $parsedSizes): string
    {
        $srcset = [];
        $maxWidth = $media->getWidth();
        $breakpoints = array_keys($parsedSizes);

        for ($i = 0; $i < count($breakpoints) - 1; $i++) {
            $currentBreakpoint = $breakpoints[$i];
            $nextBreakpoint = $breakpoints[$i + 1];
            $currentSize = $parsedSizes[$currentBreakpoint];
            $nextSize = $parsedSizes[$nextBreakpoint];

            $srcset = array_merge($srcset, $this->generateSizesForRange(
                $currentBreakpoint,
                $nextBreakpoint,
                $currentSize,
                $nextSize,
                $maxWidth,
                $media
            ));
        }

        // Add the largest size
        $largestSize = $this->calculateSize(end($parsedSizes), max($this->breakpoints));
        if ($largestSize <= $maxWidth) {
            $srcset[] = $media->getSignedUrl(['w' => $largestSize]) . " {$largestSize}w";
        }

        // Add the original size if it's larger than the largest calculated size
        if ($maxWidth > $largestSize) {
            $srcset[] = $media->getSignedUrl() . " {$maxWidth}w";
        }

        return implode(', ', array_unique($srcset));
    }

    protected function generateSizesForRange(int $start, int $end, array $startSize, array $endSize, int $maxWidth, Media $media): array
    {
        $sizes = [];
        $current = max($this->minWidth, $this->calculateSize($startSize, $start));
        $target = min($maxWidth, $this->calculateSize($endSize, $end));

        while ($current < $target) {
            $sizes[] = $media->getSignedUrl(['w' => $current]) . " {$current}w";
            $current = min($target, $current + max(10, intval($current * $this->increment)));
        }

        return $sizes;
    }

    protected function calculateSize(array $size, int $breakpoint): int
    {
        switch ($size['type']) {
            case 'px':
                return $size['value'];
            case 'vw':
                return intval($breakpoint * $size['value'] / 100);
            case 'calc':
                return intval($breakpoint * $size['vw'] / 100) - $size['px'];
            default:
                throw new \InvalidArgumentException("Invalid size type: {$size['type']}");
        }
    }

    protected function generateSizesAttribute(array $parsedSizes): string
    {
        $sizesAttribute = [];
        foreach ($parsedSizes as $breakpoint => $size) {
            if ($breakpoint === 0) {
                $sizesAttribute[] = $this->formatSize($size);
            } else {
                $sizesAttribute[] = "(min-width: {$breakpoint}px) " . $this->formatSize($size);
            }
        }
        return implode(', ', array_reverse($sizesAttribute));
    }

    protected function formatSize(array $size): string
    {
        switch ($size['type']) {
            case 'px':
                return "{$size['value']}px";
            case 'vw':
                return "{$size['value']}vw";
            case 'calc':
                return "calc({$size['vw']}vw - {$size['px']}px)";
            default:
                throw new \InvalidArgumentException("Invalid size type: {$size['type']}");
        }
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
