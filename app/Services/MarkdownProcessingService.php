<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Webuni\FrontMatter\FrontMatter;

class MarkdownProcessingService
{
    protected Collection $imagePaths;

    public function __construct(protected ImageService $imageProcessingService)
    {
        $this->imagePaths = new Collection;
    }

    /**
     * Process a markdown file and extract its content and metadata.
     *
     * @param  string  $filename  The path to the markdown file
     * @param  string  $type  The type of content
     * @param  array  $fillable  List of fillable attributes
     * @return array Processed markdown data
     */
    public function processMarkdownFile(string $filename, string $type, array $fillable = []): array
    {
        $content = file_get_contents($filename);

        $frontMatter = new FrontMatter;
        $document = $frontMatter->parse($content);

        $data = $document->getData();
        $markdownContent = $document->getContent();

        [$title, $markdownContent] = $this->extractTitleFromContent($markdownContent);
        $data = $this->processFrontMatter($data, array_merge($fillable, ['tags']));
        $data['attributes']['title'] = $data['attributes']['title'] ?? $title ?? null;

        return array_merge($data['attributes'], [
            'type' => $type,
            'slug' => $this->generateSlugFromFilename($filename),
            'content' => $markdownContent,
            'filename' => $filename,
            'meta' => $data['meta'] ?? [],
            'images' => $data['images'] ?? [],
        ]);
    }

    /**
     * Process front matter data.
     *
     * @param  array  $data  Raw front matter data
     * @param  array  $fillable  List of fillable attributes
     * @return array Processed front matter data
     */
    public function processFrontMatter(array $data, array $fillable = []): array
    {
        $processed = [
            'attributes' => [],
            'meta' => [],
            'images' => [],
        ];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'images.')) {
                $collectionName = Str::after($key, 'images.');
                $processed['images'][$collectionName] = $value;
            } elseif (in_array($key, $fillable)) {
                $processed['attributes'][$key] = $value;
            } else {
                $this->arraySet($processed['meta'], explode('.', $key), $value);
            }
        }

        return $processed;
    }

    /**
     * Handle media specified in front matter.
     */
    public function handleMediaFromFrontMatter(Entry $entry, array $images, string $filename): void
    {
        $imageService = app(ImageService::class);

        foreach ($images as $collectionName => $imagePaths) {
            $imagePaths = Arr::wrap($imagePaths);
            $fullPaths = array_map(fn ($imagePath) => $this->resolveMediaPath($imagePath, $filename), $imagePaths);

            // Filter out any paths that don't exist
            $existingPaths = array_filter($fullPaths, fn ($path) => File::exists($path));

            $imageService->syncImages($entry, $existingPaths, $collectionName);
        }
    }

    /**
     * Process images in markdown content.
     *
     * @param  Entry  $entry  The entry to associate images with
     * @param  string  $markdownContent  The markdown content to process
     * @param  string  $filename  The path to the markdown file
     * @return string Processed markdown content
     */
    public function processMarkdownImages(Entry $entry, string $markdownContent, string $filename): string
    {
        $pattern = '/!\[(.*?)\]\((.*?)\)/';
        $imagePaths = new Collection;

        $processedContent = preg_replace_callback($pattern, function ($matches) use ($filename, &$imagePaths) {
            $altText = $matches[1];
            $imagePath = $matches[2];

            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // Leave external URLs as they are
                return $matches[0];
            }

            $fullImagePath = $this->resolveMediaPath($imagePath, $filename);

            if (File::exists($fullImagePath)) {
                $imagePaths->push($fullImagePath);

                return "![{$altText}]({$fullImagePath})";
            }

            // If the file doesn't exist, leave the original markdown as is
            return $matches[0];
        }, $markdownContent);

        $this->imageProcessingService->syncImages($entry, $imagePaths->toArray(), 'content');

        return $processedContent;
    }

    /**
     * Resolve the full path of a media item.
     */
    protected function resolveMediaPath(string $mediaItem, string $markdownFilename): string
    {
        $fullPath = dirname($markdownFilename).'/'.$mediaItem;

        return File::exists($fullPath) ? $fullPath : $mediaItem;
    }

    /**
     * Sync images for a specific collection.
     *
     * @param  Entry  $entry  The entry to sync images for
     * @param  Arrayable|array  $newImagePaths  The new image paths to sync
     * @param  string  $collectionName  The name of the image collection
     */
    protected function syncImagesCollection(Entry $entry, Arrayable|array $newImagePaths, string $collectionName): void
    {
        $newImagePaths = Collection::make($newImagePaths);

        $existingImages = $entry->getImages($collectionName);
        $existingPaths = $existingImages->pluck('path');

        // Determine which images to add, keep, and remove
        $pathsToAdd = $newImagePaths->diff($existingPaths);
        $pathsToRemove = $existingPaths->diff($newImagePaths);

        // Add new images
        foreach ($pathsToAdd as $path) {
            $this->addImageToEntry($entry, $path, $collectionName);
        }

        // Remove images that are no longer present
        $existingImages->whereIn('path', $pathsToRemove)->each->delete();
    }

    /**
     * Add an image to an entry.
     */
    protected function addImageToEntry(Entry $entry, string $path, string $collectionName): void
    {
        if (method_exists($entry, 'addImage')) {
            $entry->addImage($path, $collectionName);
        }
    }

    /**
     * Set a value in a nested array using dot notation.
     *
     * @param  array  $array  The array to modify
     * @param  array  $keys  The keys in dot notation
     * @param  mixed  $value  The value to set
     */
    protected function arraySet(array &$array, array $keys, mixed $value): void
    {
        $key = array_shift($keys);
        if (empty($keys)) {
            if (isset($array[$key]) && is_array($array[$key])) {
                $array[$key][] = $value;
            } else {
                $array[$key] = $value;
            }
        } else {
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }
            $this->arraySet($array[$key], $keys, $value);
        }
    }

    /**
     * Extract title from content.
     *
     * @param  string  $content  The content to extract title from
     * @return array{0: string|null, 1: string} An array containing the title and remaining content
     */
    protected function extractTitleFromContent(string $content): array
    {
        $lines = explode("\n", $content);
        $firstLine = trim($lines[0]);

        if (str_starts_with($firstLine, '# ')) {
            $title = trim(substr($firstLine, 2));
            array_shift($lines);

            return [$title, trim(implode("\n", $lines))];
        }

        return [null, $content];
    }

    /**
     * Generate a slug from a filename.
     */
    public function generateSlugFromFilename(string $filename): string
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
    }
}
