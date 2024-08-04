<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Str;

class ResponsiveImageService
{
    const DECREMENT = 0.9;
    const MIN_SIZE = 240;
    const THUMBNAIL_THRESHOLD = 300;

    protected array $breakpoints = [
        'sm' => 640,
        'md' => 768,
        'lg' => 1024,
        'xl' => 1280,
        '2xl' => 1536,
    ];

    public function generateImgTag(Media $media, array $sizes, array $attributes = []): string
    {
        $parsedSizes = $this->parseSizes($sizes);
        $srcset = $this->generateSrcset($media);
        $sizesAttribute = $this->generateSizesAttribute($parsedSizes);

        $defaultAttributes = [
            'src' => $media->getUrl(),
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
        if (Str::contains($size, 'calc') || Str::contains($size, '-')) {
            $pattern = '/(?:calc\()?(\d+)vw\s*-\s*(\d+)px(?:\))?/';
            if (preg_match($pattern, $size, $matches)) {
                return [
                    'type' => 'calc',
                    'vw' => (int) $matches[1],
                    'px' => (int) $matches[2]
                ];
            }
        } elseif (Str::endsWith($size, 'vw')) {
            return ['type' => 'vw', 'value' => (int) $size];
        } elseif (Str::endsWith($size, 'px')) {
            return ['type' => 'px', 'value' => (int) $size];
        }

        throw new \InvalidArgumentException("Invalid size format: $size");
    }

    protected function generateSrcset(Media $media): string
    {
        $maxWidth = $media->getWidth();
        $srcset = [];

        if ($maxWidth <= self::THUMBNAIL_THRESHOLD) {
            // For thumbnails, generate original and @2x if possible
            $srcset[] = $this->formatSrcsetEntry($media, $maxWidth);
            $retinaWidth = min($maxWidth * 2, $media->getWidth());
            if ($retinaWidth > $maxWidth) {
                $srcset[] = $this->formatSrcsetEntry($media, $retinaWidth);
            }
        } else {
            // For larger images, generate a range of sizes
            $currentWidth = $maxWidth;
            while ($currentWidth >= self::MIN_SIZE) {
                $srcset[] = $this->formatSrcsetEntry($media, $currentWidth);
                $currentWidth = max(self::MIN_SIZE, intval($currentWidth * self::DECREMENT));
            }
        }

        return implode(', ', array_unique($srcset));
    }

    protected function formatSrcsetEntry(Media $media, int $width): string
    {
        return $media->getUrl(['w' => $width]) . " {$width}w";
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
