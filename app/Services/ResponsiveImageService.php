<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ResponsiveImageService
{
    private const DECREMENT = 0.9; // 10% decrement

    private const MIN_SIZE = 100;

    private const MAX_SIZE = 8192;

    protected readonly array $breakpoints;

    /**
     * @param  array  $defaultTransforms  Default image transformations
     * @param  array  $breakpoints  Breakpoints for responsive sizes
     */
    public function __construct(
        protected readonly array $defaultTransforms = [],
        array $breakpoints = []
    ) {
        $this->breakpoints = $breakpoints ?: [
            'sm' => 640,
            'md' => 768,
            'lg' => 1024,
            'xl' => 1280,
            '2xl' => 1536,
        ];
    }

    /**
     * Generate an HTML img tag with responsive attributes.
     */
    public function generateImgTag(Image $media, array $sizes, array $attributes = [], bool $isFluid = true, ?array $displaySize = null): string
    {
        $parsedSizes = $this->parseSizes($sizes);
        $srcset = $this->generateSrcset($media, $isFluid, $displaySize);
        $sizesAttribute = $this->generateSizesAttribute($parsedSizes);

        $defaultAttributes = [
            'src' => $media->getUrl([...$this->defaultTransforms, ...$this->getBaseTransforms($displaySize)]),
            'alt' => $this->getMediaAlt($media),
            'sizes' => $sizesAttribute,
            'srcset' => $srcset,
            ...$displaySize ? ['width' => $displaySize[0], 'height' => $displaySize[1]] : [],
        ];

        return $this->buildImgTag([...$defaultAttributes, ...$attributes]);
    }

    /**
     * Get the alt text for the image.
     */
    protected function getMediaAlt(Image $media): string
    {
        $customProperties = is_string($media->custom_properties)
            ? json_decode($media->custom_properties, true)
            : $media->custom_properties;

        return $customProperties['alt'] ?? '';
    }

    /**
     * Parse the sizes array into a structured format.
     */
    protected function parseSizes(array $sizes): array
    {
        return collect($sizes)
            ->mapWithKeys(function ($size) {
                if (Str::contains($size, ':')) {
                    [$breakpoint, $value] = explode(':', $size, 2);
                    $breakpoint = trim($breakpoint);
                    $value = trim($value);

                    return isset($this->breakpoints[$breakpoint])
                        ? [$this->breakpoints[$breakpoint] => $this->parseSize($value)]
                        : [];
                }

                return [0 => $this->parseSize($size)];
            })
            ->sortKeys()
            ->all();
    }

    /**
     * Parse a single size descriptor.
     *
     * @throws \InvalidArgumentException If the size format is invalid
     */
    protected function parseSize(string $size): array
    {
        if (Str::contains($size, ['calc', '-'])) {
            if (preg_match('/(?:calc\()?(\d+)vw\s*-\s*(\d+)px(?:\))?/', $size, $matches)) {
                return ['type' => 'calc', 'vw' => (int) $matches[1], 'px' => (int) $matches[2]];
            }
        }

        if (Str::endsWith($size, 'vw')) {
            return ['type' => 'vw', 'value' => (int) $size];
        }

        if (Str::endsWith($size, 'px')) {
            return ['type' => 'px', 'value' => (int) $size];
        }

        throw new \InvalidArgumentException("Invalid size format: $size");
    }

    /**
     * Generate the srcset attribute.
     */
    protected function generateSrcset(Image $media, bool $isFluid, ?array $displaySize = null): string
    {
        $maxWidth = $this->getMediaWidth($media);
        $srcset = [];

        if ($displaySize) {
            [$baseWidth, $baseHeight] = $displaySize;
            $aspectRatio = $baseHeight / $baseWidth;

            if ($isFluid) {
                $srcset = $this->generateFluidSrcset($media, $baseWidth, $maxWidth, $aspectRatio);
            } else {
                $srcset = $this->generateFixedSrcset($media, $baseWidth, $baseHeight, $maxWidth);
            }
        } else {
            $srcset = $isFluid
                ? $this->generateFluidSrcset($media, $maxWidth, $maxWidth)
                : [$this->formatSrcsetEntry($media, $maxWidth)];
        }

        return implode(', ', array_unique($srcset));
    }

    /**
     * Generate srcset for fluid images.
     */
    protected function generateFluidSrcset(Image $media, int $baseWidth, int $maxWidth, ?float $aspectRatio = null): array
    {
        $srcset = [];
        $currentWidth = min($baseWidth * 2, $maxWidth);
        $minWidth = self::MIN_SIZE; // This is 100 as defined in the class constant

        while ($currentWidth > $minWidth) {
            $currentHeight = $aspectRatio ? round($currentWidth * $aspectRatio) : null;
            $srcset[] = $this->formatSrcsetEntry($media, $currentWidth, $currentHeight);
            $currentWidth = max($minWidth, intval($currentWidth * self::DECREMENT));
        }

        if ($aspectRatio && ! in_array($baseWidth, array_column($srcset, 'width'))) {
            $srcset[] = $this->formatSrcsetEntry($media, $baseWidth, round($baseWidth * $aspectRatio));
        }

        return $srcset;
    }

    /**
     * Generate srcset for fixed size images.
     */
    protected function generateFixedSrcset(Image $media, int $baseWidth, int $baseHeight, int $maxWidth): array
    {
        $srcset = [$this->formatSrcsetEntry($media, $baseWidth, $baseHeight)];
        $retinaWidth = min($baseWidth * 2, $maxWidth);

        if ($retinaWidth > $baseWidth) {
            $retinaHeight = round($retinaWidth * ($baseHeight / $baseWidth));
            $srcset[] = $this->formatSrcsetEntry($media, $retinaWidth, $retinaHeight);
        }

        return $srcset;
    }

    /**
     * Get the width of the media.
     */
    protected function getMediaWidth(Image $media): int
    {
        $dimensions = is_string($media->dimensions)
            ? json_decode($media->dimensions, true)
            : $media->dimensions;

        return $dimensions['width'] ?? 0;
    }

    /**
     * Format a single srcset entry.
     */
    protected function formatSrcsetEntry(Image $media, int $width, ?int $height = null): string
    {
        $transforms = [...$this->defaultTransforms, 'w' => $width, ...($height ? ['h' => $height] : [])];

        return $media->getUrl($transforms)." {$width}w";
    }

    /**
     * Generate the sizes attribute.
     */
    protected function generateSizesAttribute(array $parsedSizes): string
    {
        return collect($parsedSizes)
            ->map(fn ($size, $breakpoint) => $breakpoint === 0
                ? $this->formatSize($size)
                : "(min-width: {$breakpoint}px) ".$this->formatSize($size))
            ->reverse()
            ->implode(', ');
    }

    /**
     * Format a single size for the sizes attribute.
     *
     * @throws \InvalidArgumentException If the size type is invalid
     */
    protected function formatSize(array $size): string
    {
        return match ($size['type']) {
            'px' => "{$size['value']}px",
            'vw' => "{$size['value']}vw",
            'calc' => "calc({$size['vw']}vw - {$size['px']}px)",
            default => throw new \InvalidArgumentException("Invalid size type: {$size['type']}"),
        };
    }

    /**
     * Build the img tag from attributes.
     */
    protected function buildImgTag(array $attributes): string
    {
        $attributeString = Arr::join(Arr::map($attributes, fn ($value, $key) => "$key=\"".htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'), ' ');

        return "<img $attributeString>";
    }

    /**
     * Get the base transforms for the image.
     */
    protected function getBaseTransforms(?array $displaySize): array
    {
        return $displaySize ? ['w' => $displaySize[0], 'h' => $displaySize[1]] : [];
    }
}
