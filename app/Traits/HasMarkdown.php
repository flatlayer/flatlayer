<?php

namespace App\Traits;

use App\Markdown\EnhancedMarkdownRenderer;
use App\Services\MarkdownProcessingService;
use Carbon\Carbon;

/**
 * Trait HasMarkdown
 *
 * This trait provides functionality for working with Markdown content in models.
 * It allows creating and syncing models from Markdown files, and parsing Markdown content.
 */
trait HasMarkdown
{
    protected MarkdownProcessingService $markdownContentService;

    /**
     * Initialize the Markdown model.
     */
    public function initializeMarkdownModel(): void
    {
        $this->markdownContentService = app(MarkdownProcessingService::class);
    }

    /**
     * Create a new model instance from a Markdown file.
     */
    public static function createFromMarkdown(string $filename, string $type = 'post'): self
    {
        $model = new static(['type' => $type]);
        $model->initializeMarkdownModel();

        return $model->fillFromMarkdown($filename, $type);
    }

    /**
     * Sync an existing model or create a new one from a Markdown file.
     */
    public static function syncFromMarkdown(string $filename, string $type = 'post', bool $autoSave = false): self
    {
        $model = static::firstOrNew(
            ['type' => $type, 'slug' => static::generateSlugFromFilename($filename)],
            ['type' => $type]
        );

        $model->initializeMarkdownModel();
        $model = $model->fillFromMarkdown($filename, $type);

        if ($autoSave) {
            $model->save();
        }

        return $model;
    }

    /**
     * Fill the model attributes from a Markdown file.
     */
    protected function fillFromMarkdown(string $filename, string $type): self
    {
        $processedData = $this->markdownContentService->processMarkdownFile($filename, $type, $this->fillable);

        foreach ($processedData as $key => $value) {
            match ($key) {
                'tags' => null,
                'published_at' => $this->handlePublishedAt($value),
                default => $this->fillAttributeIfFillable($key, $value),
            };
        }

        $this->save();
        $this->handleMediaAndTags($processedData, $filename);

        return $this;
    }

    /**
     * Handle the published_at attribute.
     */
    protected function handlePublishedAt(mixed $value): void
    {
        if ($this->published_at === null) {
            if ($value === true) {
                $this->published_at = now();
            } elseif (is_string($value)) {
                $this->published_at = Carbon::parse($value);
            } elseif ($value instanceof \DateTimeInterface) {
                $this->published_at = Carbon::instance($value);
            }
        }
    }

    /**
     * Fill an attribute if it's fillable.
     */
    protected function fillAttributeIfFillable(string $key, mixed $value): void
    {
        if ($this->isFillable($key)) {
            $this->$key = $value;
        }
    }

    /**
     * Handle media and tags processing.
     */
    protected function handleMediaAndTags(array $processedData, string $filename): void
    {
        if (method_exists($this, 'addImage') && isset($processedData['images'])) {
            $this->markdownContentService->handleMediaFromFrontMatter($this, $processedData['images'], $filename);
            $this->content = $this->markdownContentService->processMarkdownImages($this, $this->content, $filename);
        }

        if (in_array(HasTags::class, class_uses_recursive($this)) && isset($processedData['tags'])) {
            $this->syncTags($processedData['tags']);
        }
    }

    /**
     * Generate a slug from a filename.
     */
    protected static function generateSlugFromFilename(string $filename): string
    {
        return app(MarkdownProcessingService::class)->generateSlugFromFilename($filename);
    }

    /**
     * Get the parsed HTML content of the Markdown.
     */
    public function getParsedContent(): string
    {
        return (new EnhancedMarkdownRenderer($this))
            ->convertToHtml($this->content)
            ->getContent();
    }
}
