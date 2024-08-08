<?php

namespace App\Traits;

use App\Markdown\EnhancedMarkdownRenderer;
use App\Services\MarkdownProcessingService;
use Spatie\Tags\HasTags;

trait HasMarkdown
{
    protected MarkdownProcessingService $markdownContentService;

    public function initializeMarkdownModel()
    {
        $this->markdownContentService = app(MarkdownProcessingService::class);
    }

    public static function createFromMarkdown(string $filename, string $type='post'): self
    {
        $model = new static(['type' => $type]);
        $model->initializeMarkdownModel();
        return $model->fillFromMarkdown($filename, $type);
    }

    public static function syncFromMarkdown(string $filename, string $type='post', bool $autoSave = false): self
    {
        $model = static::where('type', $type)->where('slug', static::generateSlugFromFilename($filename))->first();
        if(!$model) {
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

    protected function fillFromMarkdown(string $filename, string $type): self
    {
        $processedData = $this->markdownContentService->processMarkdownFile($filename, $type, $this->fillable);

        foreach ($processedData as $key => $value) {
            if($key === 'tags') {
                continue;
            }
            if ($this->isFillable($key)) {
                $this->$key = $value;
            }
        }

        $this->save();

        if (method_exists($this, 'addMedia') && isset($processedData['images'])) {
            $this->markdownContentService->handleMediaFromFrontMatter($this, $processedData['images'], $filename);
            $this->content = $this->markdownContentService->processMarkdownImages($this, $this->content, $filename);
        }

        if (in_array(HasTags::class, class_uses_recursive($this)) && isset($processedData['tags'])) {
            $this->syncTags($processedData['tags']);
        }

        return $this;
    }

    protected static function generateSlugFromFilename(string $filename): string
    {
        return app(MarkdownProcessingService::class)->generateSlugFromFilename($filename);
    }

    public function getParsedContent(): string
    {
        $markdownRenderer = new EnhancedMarkdownRenderer($this);
        return $markdownRenderer->convertToHtml($this->content)->getContent();
    }
}
