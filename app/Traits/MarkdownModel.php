<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Webuni\FrontMatter\FrontMatter;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Tags\HasTags;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

trait MarkdownModel
{
    public static function fromMarkdown(string $filename): self
    {
        $content = file_get_contents($filename);

        $frontMatter = new FrontMatter();
        $document = $frontMatter->parse($content);

        $data = $document->getData();
        $markdownContent = $document->getContent();

        // Extract title from the first line if it starts with #
        $title = null;
        $markdownContent = static::extractTitleFromContent($markdownContent, $title);

        // Use extracted title if available, otherwise use frontmatter title
        if ($title) {
            $data['title'] = $title;
        }

        $model = new self();

        return static::fillModelFromMarkdown($model, $data, $markdownContent, $filename);
    }

    public static function syncFromMarkdown(string $filename, bool $autoSave = false): self
    {
        $content = file_get_contents($filename);

        $frontMatter = new FrontMatter();
        $document = $frontMatter->parse($content);

        $data = $document->getData();
        $markdownContent = $document->getContent();

        // Extract title from the first line if it starts with #
        $title = null;
        $markdownContent = static::extractTitleFromContent($markdownContent, $title);

        // Use extracted title if available, otherwise use front matter title
        if ($title) {
            $data['title'] = $title;
        }

        // Determine the slug value
        $slugValue = $data['slug'] ?? pathinfo($filename, PATHINFO_FILENAME);

        // Find existing model by slug or create a new one
        $model = static::findBySlug($slugValue) ?? new static();

        $model = static::fillModelFromMarkdown($model, $data, $markdownContent, $filename);

        if ($autoSave) {
            $model->save();
        }

        return $model;
    }

    protected static function extractTitleFromContent(string $content, ?string &$title): string
    {
        $lines = explode("\n", $content);
        $firstLine = trim($lines[0]);

        if (str_starts_with($firstLine, '# ')) {
            $title = trim(substr($firstLine, 2));
            array_shift($lines);
            return implode("\n", $lines);
        }

        return $content;
    }

    protected static function fillModelFromMarkdown(self $model, array $data, string $markdownContent, string $filename): self
    {
        // Handle front matter data
        foreach ($data as $key => $value) {
            if ($model->hasCast($key)) {
                $value = $model->castAttribute($key, $value);
            } elseif (is_string($value) && in_array(strtolower($value), ['true', 'false'])) {
                $value = strtolower($value) === 'true';
            }
            $model->$key = $value;
        }

        // Set the main content
        $contentField = $model->getMarkdownContentField();
        $model->$contentField = static::processMarkdownImages($model, $markdownContent, $filename);

        // Handle Spatie Media Library
        if ($model instanceof HasMedia) {
            static::handleMediaLibrary($model, $data, $filename);
        }

        // Handle Spatie Tags
        if (in_array(HasTags::class, class_uses_recursive($model)) && isset($data['tags'])) {
            $model->syncTags($data['tags']);
        }

        // Handle slug
        if (method_exists($model, 'getSlugOptions')) {
            $slugField = $model->getSlugOptions()->slugField;
            if (!isset($model->$slugField)) {
                $model->$slugField = pathinfo($filename, PATHINFO_FILENAME);
            }
        }

        return $model;
    }

    protected static function handleMediaLibrary(HasMedia $model, array $data, string $filename): void
    {
        $mediaCollections = $model->getRegisteredMediaCollections();
        foreach ($mediaCollections as $collection) {
            $collectionName = $collection->name;
            if (isset($data[$collectionName])) {
                $mediaItems = is_array($data[$collectionName]) ? $data[$collectionName] : [$data[$collectionName]];

                $existingMedia = $model->getMedia($collectionName)->keyBy('file_name');
                $newMediaItems = [];

                foreach ($mediaItems as $mediaItem) {
                    $mediaPath = static::resolveMediaPath($mediaItem, $filename);
                    $fileName = basename($mediaPath);

                    if (isset($existingMedia[$fileName])) {
                        // File already exists in the collection, keep it
                        $newMediaItems[] = $existingMedia[$fileName];
                        $existingMedia->forget($fileName);
                    } else {
                        // New file, add it to the collection
                        if (filter_var($mediaItem, FILTER_VALIDATE_URL)) {
                            $newMediaItems[] = $model->addMediaFromUrl($mediaItem)
                                ->toMediaCollection($collectionName);
                        } else {
                            $newMediaItems[] = $model->addMedia($mediaPath)
                                ->preservingOriginal()
                                ->toMediaCollection($collectionName);
                        }
                    }
                }

                // Remove any media items that are no longer in the markdown
                foreach ($existingMedia as $mediaItem) {
                    $mediaItem->delete();
                }

                // Update the media order if necessary
                $model->clearMediaCollection($collectionName);
                foreach ($newMediaItems as $mediaItem) {
                    $mediaItem->move($model, $collectionName);
                }
            }
        }
    }

    protected static function processMarkdownImages(HasMedia $model, string $markdownContent, string $filename): string
    {
        $pattern = '/!\[(.*?)\]\((.*?)\)/';
        return preg_replace_callback($pattern, function ($matches) use ($model, $filename) {
            $altText = $matches[1];
            $imagePath = $matches[2];

            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // Leave external URLs as they are
                return $matches[0];
            }

            $fullImagePath = static::resolveMediaPath($imagePath, $filename);

            if (File::exists($fullImagePath)) {
                $media = $model->addMedia($fullImagePath)
                    ->preservingOriginal()
                    ->toMediaCollection('images');

                return "![{$altText}]({$media->getUrl()})";
            }

            // If the file doesn't exist, leave the original markdown as is
            return $matches[0];
        }, $markdownContent);
    }

    protected static function resolveMediaPath(string $mediaItem, string $markdownFilename): string
    {
        if (filter_var($mediaItem, FILTER_VALIDATE_URL)) {
            return $mediaItem;
        }

        $fullPath = dirname($markdownFilename) . '/' . $mediaItem;
        return File::exists($fullPath) ? $fullPath : $mediaItem;
    }

    protected function getMarkdownContentField(): string
    {
        return $this->markdownContentField ?? 'content';
    }
}
