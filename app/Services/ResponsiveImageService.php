<?php

namespace App\Services;

use App\Models\MediaFile;
use Illuminate\Support\Str;

class ResponsiveImageService
{
    const DECREMENT = 0.9; // 10% decrement
    const MIN_SIZE = 100;
    const MAX_SIZE = 8192;

    protected array $breakpoints = [
        'sm' => 640,
        'md' => 768,
        'lg' => 1024,
        'xl' => 1280,
        '2xl' => 1536,
    ];


    public function __construct(
        protected array $defaultTransforms = []
    ) {}

    public function generateImgTag(MediaFile $media, array $sizes, array $attributes = [], bool $isFluid = true, ?array $displaySize = null): string
    {
        $parsedSizes = $this->parseSizes($sizes);
        $srcset = $this->generateSrcset($media, $isFluid, $displaySize);
        $sizesAttribute = $this->generateSizesAttribute($parsedSizes);

        $defaultAttributes = [
            'src' => $media->getUrl(array_merge($this->defaultTransforms, $this->getBaseTransforms($displaySize))),
            'alt' => $this->getMediaAlt($media),
            'sizes' => $sizesAttribute,
            'srcset' => $srcset,
        ];

        if ($displaySize) {
            $defaultAttributes['width'] = $displaySize[0];
            $defaultAttributes['height'] = $displaySize[1];
        }

        $mergedAttributes = array_merge($defaultAttributes, $attributes);

        return $this->buildImgTag($mergedAttributes);
    }

    protected function getMediaAlt(MediaFile $media): string
    {
        $customProperties = $media->custom_properties;
        if (is_string($customProperties)) {
            $customProperties = json_decode($customProperties, true);
        }
        return $customProperties['alt'] ?? '';
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

    protected function generateSrcset(MediaFile $media, bool $isFluid, ?array $displaySize = null): string
    {
        $maxWidth = $this->getMediaWidth($media);
        $srcset = [];

        if ($displaySize) {
            // Case 1: Display size is provided
            $baseWidth = $displaySize[0];
            $baseHeight = $displaySize[1];
            $aspectRatio = $baseHeight / $baseWidth;

            if ($isFluid) {
                $currentWidth = min($baseWidth * 2, $maxWidth);
                $minWidth = max(self::MIN_SIZE, intval($baseWidth * 0.25));

                while ($currentWidth >= $minWidth) {
                    $currentHeight = round($currentWidth * $aspectRatio);
                    $srcset[] = $this->formatSrcsetEntry($media, $currentWidth, $currentHeight);
                    $currentWidth = max($minWidth, intval($currentWidth * self::DECREMENT));

                    if($currentWidth == $minWidth) {
                        break;
                    }
                }

                // Ensure baseWidth is included if it's not already
                if (!in_array($baseWidth, array_column($srcset, 'width'))) {
                    $srcset[] = $this->formatSrcsetEntry($media, $baseWidth, $baseHeight);
                }
            } else {
                // Fixed size: base size and 2x if possible
                $srcset[] = $this->formatSrcsetEntry($media, $baseWidth, $baseHeight);
                $retinaWidth = min($baseWidth * 2, $maxWidth);
                if ($retinaWidth > $baseWidth) {
                    $retinaHeight = round($retinaWidth * $aspectRatio);
                    $srcset[] = $this->formatSrcsetEntry($media, $retinaWidth, $retinaHeight);
                }
            }
        } else {
            // Case 2: No display size provided, use original aspect ratio
            if ($isFluid) {
                $currentWidth = $maxWidth;
                while ($currentWidth >= self::MIN_SIZE) {
                    $srcset[] = $this->formatSrcsetEntry($media, $currentWidth);
                    $currentWidth = max(self::MIN_SIZE, intval($currentWidth * self::DECREMENT));

                    if($currentWidth == self::MIN_SIZE) {
                        break;
                    }
                }
            } else {
                // Fixed size: original size only
                $srcset[] = $this->formatSrcsetEntry($media, $maxWidth);
            }
        }

        return implode(', ', array_unique($srcset));
    }

    protected function getMediaWidth(MediaFile $media): int
    {
        $dimensions = $media->dimensions;
        if (is_string($dimensions)) {
            $dimensions = json_decode($dimensions, true);
        }
        return $dimensions['width'] ?? 0;
    }

    protected function formatSrcsetEntry(MediaFile $media, int $width, ?int $height = null): string
    {
        $transforms = array_merge($this->defaultTransforms, ['w' => $width]);
        if ($height !== null) {
            $transforms['h'] = $height;
        }
        return $media->getUrl($transforms) . " {$width}w";
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

    protected function calculateProportionalHeight(int $width, int $baseWidth, int $baseHeight): int
    {
        return intval(($width / $baseWidth) * $baseHeight);
    }

    protected function getBaseTransforms(?array $displaySize): array
    {
        if (!$displaySize) {
            return [];
        }
        return ['w' => $displaySize[0], 'h' => $displaySize[1]];
    }
}
