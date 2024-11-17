<?php

namespace App\Traits;

use App\Services\MarkdownProcessingService;
use Carbon\Carbon;

trait HasMarkdown
{
    protected MarkdownProcessingService $markdownContentService;
    protected ?array $pendingTags = null;
    protected ?array $pendingMedia = null;

    /**
     * Initialize the Markdown model.
     */
    protected function initializeMarkdownModel(): void
    {
        $this->markdownContentService = app(MarkdownProcessingService::class);
    }

    /**
     * Create a new model instance from a Markdown file and save it.
     */
    public static function createFromMarkdown(string $filename, string $type = 'post'): self
    {
        $model = new static(['type' => $type]);
        $model->initializeMarkdownModel();
        $model->slug = static::generateSlugFromFilename($filename);

        // Process the markdown content
        $processedData = $model->markdownContentService->processMarkdownFile(
            $filename,
            $type,
            $model->slug
        );

        // Fill basic attributes first
        foreach ($processedData as $key => $value) {
            match ($key) {
                'tags' => $model->pendingTags = $value,
                'images' => $model->pendingMedia = $value,
                'published_at' => $model->handlePublishedAt($value),
                default => $model->fillAttributeIfFillable($key, $value)
            };
        }

        // Save the model first to get an ID
        $model->save();

        // Now handle tags and media
        if ($model->pendingTags !== null) {
            $model->syncTags($model->pendingTags);
            $model->pendingTags = null;
        }

        if ($model->pendingMedia !== null) {
            $model->markdownContentService->handleMediaFromFrontMatter($model, $model->pendingMedia, $filename);
            $model->content = $model->markdownContentService->processMarkdownImages($model, $model->content, $filename);
            $model->pendingMedia = null;

            // Save again if content was modified by image processing
            $model->save();
        }

        return $model->fresh();
    }

    /**
     * Sync an existing model or create a new one from a Markdown file.
     */
    public static function syncFromMarkdown(string $filename, string $type = 'post', bool $autoSave = false): self
    {
        $slug = static::generateSlugFromFilename($filename);

        // Find existing or create new
        $model = static::firstOrNew(
            ['type' => $type, 'slug' => $slug],
            ['type' => $type]
        );

        if (!$model->exists) {
            return static::createFromMarkdown($filename, $type);
        }

        // For existing models, update their content
        $model->initializeMarkdownModel();

        // Process the markdown content
        $processedData = $model->markdownContentService->processMarkdownFile(
            $filename,
            $type,
            $model->slug
        );

        // Fill basic attributes first
        foreach ($processedData as $key => $value) {
            match ($key) {
                'tags' => $model->pendingTags = $value,
                'images' => $model->pendingMedia = $value,
                'published_at' => $model->handlePublishedAt($value),
                default => $model->fillAttributeIfFillable($key, $value)
            };
        }

        if ($autoSave) {
            // Save base model changes
            $model->save();

            // Handle tags
            if ($model->pendingTags !== null) {
                $model->syncTags($model->pendingTags);
                $model->pendingTags = null;
            }

            // Handle media
            if ($model->pendingMedia !== null) {
                $model->markdownContentService->handleMediaFromFrontMatter($model, $model->pendingMedia, $filename);
                $model->content = $model->markdownContentService->processMarkdownImages($model, $model->content, $filename);
                $model->pendingMedia = null;

                // Save again if content was modified by image processing
                $model->save();
            }

            return $model->fresh();
        }

        return $model;
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
     * Generate a slug from a filename.
     */
    protected static function generateSlugFromFilename(string $filename): string
    {
        return app(MarkdownProcessingService::class)->generateSlugFromFilename($filename);
    }
}
