<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Webuni\FrontMatter\FrontMatter;

class MarkdownProcessingService
{
    /**
     * Default fields that can be extracted from front matter.
     */
    protected array $defaultFillable = [
        'title',
        'type',
        'tags',
        'published_at',
        'excerpt',
        'description',
    ];

    public function __construct(
        protected readonly ImageService $imageService
    ) {}

    /**
     * Process a markdown file and extract its content and metadata.
     *
     * @param string $filename The path to the markdown file
     * @param string $type The content type
     * @param string $slug The slug for the content
     * @param array $fillable Additional fillable fields
     * @param array $options Options for processing
     * @return array{
     *     type: string,
     *     slug: string,
     *     content: string,
     *     filename: string,
     *     meta: array,
     *     images: array,
     *     title?: string,
     *     published_at?: string,
     *     tags?: array,
     *     excerpt?: string
     * }
     *
     * @throws \InvalidArgumentException If the file cannot be read
     */
    public function processMarkdownFile(
        string $filename,
        string $type,
        string $slug,
        array $fillable = [],
        array $options = []
    ): array
    {
        if (!File::exists($filename)) {
            throw new InvalidArgumentException("File not found: {$filename}");
        }

        $content = File::get($filename);
        $frontMatter = new FrontMatter;
        $document = $frontMatter->parse($content);

        $data = $document->getData();
        $markdownContent = $document->getContent();

        [$title, $markdownContent] = $this->extractTitleFromContent($markdownContent);

        $processedData = $this->processFrontMatter(
            $data,
            array_unique([...$this->defaultFillable, ...$fillable]),
            $options
        );

        $processedData['attributes']['title'] ??= $title;

        return [
            'type' => $type,
            'slug' => $slug, // Use provided slug instead of generating
            'content' => $markdownContent,
            'filename' => $filename,
            'meta' => $this->normalizeMetaData($processedData['meta'] ?? []),
            'images' => $this->normalizeImageData($processedData['images'] ?? [], $data),
            ...$processedData['attributes'],
        ];
    }

    /**
     * Process front matter data into structured components.
     *
     * @param array $data The raw front matter data
     * @param array $fillable The fillable fields to extract as attributes
     * @return array{attributes: array, meta: array, images: array}
     */
    public function processFrontMatter(array $data, array $fillable = [], array $options = []): array
    {
        $dateFormat = $options['dateFormat'] ?? 'Y-m-d H:i:s';

        $processed = [
            'attributes' => [],
            'meta' => [],
            'images' => [],
        ];

        // First pass: handle explicit image structures
        if (isset($data['images']) && is_array($data['images'])) {
            $processed['images'] = $data['images'];
            unset($data['images']); // Remove from data so it doesn't go into meta
        }

        // Second pass: process remaining data
        foreach ($data as $key => $value) {
            match (true) {
                // Legacy support for dot notation images
                str_starts_with($key, 'images.') =>
                $processed['images'][Str::after($key, 'images.')] = $value,

                // Handle fillable fields
                in_array($key, $fillable) =>
                $processed['attributes'][$key] = $this->normalizeAttributeValue(
                    $key,
                    $value,
                    ['dateFormat' => $dateFormat]
                ),

                // Everything else goes to meta
                default =>
                Arr::set($processed['meta'], $key, $value)
            };
        }

        return $processed;
    }

    /**
     * Handle media specified in front matter.
     *
     * @param Entry $entry The entry to attach images to
     * @param array $images The image definitions
     * @param string $filename The base filename for resolving paths
     */
    public function handleMediaFromFrontMatter(Entry $entry, array $images, string $filename): void
    {
        foreach ($images as $collectionName => $imagePaths) {
            $paths = collect(Arr::wrap($imagePaths))
                ->map(fn ($path) => $this->imageService->resolveMediaPath($path, $filename))
                ->filter(fn ($path) => File::exists($path))
                ->values();

            if ($paths->isNotEmpty()) {
                $this->imageService->syncImagesForEntry($entry, $paths->toArray(), $collectionName);
            }
        }
    }

    /**
     * Process images in markdown content.
     *
     * @param Entry $entry The entry to process images for
     * @param string $markdownContent The markdown content
     * @param string $filename The base filename for resolving paths
     * @return string The processed content
     */
    public function processMarkdownImages(Entry $entry, string $markdownContent, string $filename): string
    {
        $processedContent = $this->imageService->syncContentImages($entry, $markdownContent, $filename);

        // Remove any unused images from the content collection
        $usedImages = $entry->images()->where('collection', 'content')->pluck('id');
        $entry->images()
            ->where('collection', 'content')
            ->whereNotIn('id', $usedImages)
            ->delete();

        return $processedContent;
    }

    /**
     * Extract title from content.
     *
     * @param string $content The markdown content
     * @return array{0: string|null, 1: string} The extracted title and remaining content
     */
    protected function extractTitleFromContent(string $content): array
    {
        $lines = explode("\n", $content);
        $firstLine = trim($lines[0]);

        if (str_starts_with($firstLine, '# ')) {
            $title = trim(substr($firstLine, 2));
            return [$title, trim(implode("\n", array_slice($lines, 1)))];
        }

        return [null, $content];
    }

    /**
     * Generate a slug from a filename.
     *
     * @param string $filename The filename to generate a slug from
     * @return string The generated slug
     */
    public function generateSlugFromFilename(string $filename): string
    {
        $pathInfo = pathinfo($filename);
        $basename = $pathInfo['filename'];

        // Handle special cases
        if ($basename === 'index' && isset($pathInfo['dirname']) && $pathInfo['dirname'] !== '.') {
            return Str::slug($pathInfo['dirname']);
        }

        return Str::slug($basename);
    }

    /**
     * Normalize image data from various formats into a consistent structure.
     *
     * @param array $images The processed image data
     * @param array $rawData The raw front matter data for fallback processing
     * @return array The normalized image data
     */
    protected function normalizeImageData(array $images, array $rawData): array
    {
        // First handle any nested image definitions in the raw data
        if (isset($rawData['resources']) && is_array($rawData['resources'])) {
            foreach ($rawData['resources'] as $resource) {
                if (isset($resource['src'], $resource['name'])) {
                    $images[$resource['name']] = $resource['src'];
                }
            }
        }

        // Convert any string values to arrays for consistency, but only for values that should be arrays
        return array_map(function ($value) {
            if (is_string($value)) {
                // Only convert to array if it's not for a single image
                if (str_contains($value, ',')) {
                    return array_map('trim', explode(',', $value));
                }
                return $value;
            }
            return $value;
        }, $images);
    }

    /**
     * Normalize meta data into a consistent structure.
     *
     * @param array $meta The meta data to normalize
     * @return array The normalized meta data
     */
    protected function normalizeMetaData(array $meta): array
    {
        // If we have a nested 'meta' key, use its contents directly
        if (isset($meta['meta']) && is_array($meta['meta'])) {
            $meta = $meta['meta'];
        }

        // Remove any null or empty values
        $meta = array_filter($meta, function ($value) {
            return $value !== null && $value !== '';
        });

        // Sort keys for consistent output
        ksort($meta);

        return $meta;
    }

    /**
     * Normalize attribute values based on their key.
     *
     * @param string $key The attribute key
     * @param mixed $value The attribute value
     * @return mixed The normalized value
     */
    protected function normalizeAttributeValue(string $key, mixed $value, array $options = []): mixed
    {
        return match($key) {
            'tags' => $this->normalizeTagValue($value),
            'published_at' => $this->normalizeDateValue($value, $options['dateFormat'] ?? 'Y-m-d H:i:s'),
            default => $value,
        };
    }

    /**
     * Normalize tag values into a consistent array format.
     *
     * @param mixed $value The tag value to normalize
     * @return array The normalized tags
     */
    protected function normalizeTagValue(mixed $value): array
    {
        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        return [];
    }

    /**
     * Normalize date values into a consistent format.
     *
     * @param mixed $value The date value to normalize
     * @param string $format The date format to use
     * @return string|null The normalized date string
     */
    protected function normalizeDateValue(mixed $value, string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === true) {
            return now()->format($format);
        }

        if (is_string($value)) {
            try {
                return date($format, strtotime($value));
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
