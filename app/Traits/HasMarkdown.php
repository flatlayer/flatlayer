<?php

namespace App\Traits;

use App\Markdown\EnhancedMarkdownRenderer;
use App\Services\MarkdownProcessingService;

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
     *
     * @param  string  $filename  The filename of the Markdown file
     * @param  string  $type  The type of the model (default: 'post')
     */
    public static function createFromMarkdown(string $filename, string $type = 'post'): self
    {
        $model = new static(['type' => $type]);
        $model->initializeMarkdownModel();

        return $model->fillFromMarkdown($filename, $type);
    }

    /**
     * Sync an existing model or create a new one from a Markdown file.
     *
     * @param  string  $filename  The filename of the Markdown file
     * @param  string  $type  The type of the model (default: 'post')
     * @param  bool  $autoSave  Whether to automatically save the model
     */
    public static function syncFromMarkdown(string $filename, string $type = 'post', bool $autoSave = false): self
    {
        $model = static::where('type', $type)->where('slug', static::generateSlugFromFilename($filename))->first();
        if (! $model) {
            // Create a new model
            return static::createFromMarkdown($filename, $type);
        }

        $model->initializeMarkdownModel();
        $model = $model->fillFromMarkdown($filename, $type);

        if ($autoSave) {
            $model->save();
        }

        return $model;
    }

    /**
     * Fill the model attributes from a Markdown file.
     *
     * @param  string  $filename  The filename of the Markdown file
     * @param  string  $type  The type of the model
     */
    protected function fillFromMarkdown(string $filename, string $type): self
    {
        $processedData = $this->markdownContentService->processMarkdownFile($filename, $type, $this->fillable);

        foreach ($processedData as $key => $value) {
            if ($key === 'tags') {
                continue;
            }
            if ($this->isFillable($key)) {
                $this->$key = $value;
            }
        }

        // Handle published_at attribute
        if (isset($processedData['published_at']) && $processedData['published_at'] === true && $this->published_at === null) {
            $this->published_at = now();
        }

        $this->save();

        if (method_exists($this, 'addImage') && isset($processedData['images'])) {
            $this->markdownContentService->handleMediaFromFrontMatter($this, $processedData['images'], $filename);
            $this->content = $this->markdownContentService->processMarkdownImages($this, $this->content, $filename);
        }

        if (in_array(HasTags::class, class_uses_recursive($this)) && isset($processedData['tags'])) {
            $this->syncTags($processedData['tags']);
        }

        return $this;
    }

    /**
     * Generate a slug from a filename.
     *
     * @param  string  $filename  The filename to generate the slug from
     * @return string The generated slug
     */
    protected static function generateSlugFromFilename(string $filename): string
    {
        return app(MarkdownProcessingService::class)->generateSlugFromFilename($filename);
    }

    /**
     * Get the parsed HTML content of the Markdown.
     *
     * @return string The parsed HTML content
     */
    public function getParsedContent(): string
    {
        $markdownRenderer = new EnhancedMarkdownRenderer($this);

        return $markdownRenderer->convertToHtml($this->content)->getContent();
    }
}
