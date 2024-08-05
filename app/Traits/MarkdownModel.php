<?php

namespace App\Traits;

use App\Services\MarkdownMediaService;
use Illuminate\Database\Eloquent\Model;
use Webuni\FrontMatter\FrontMatter;
use Illuminate\Support\Str;
use Spatie\Tags\HasTags;

trait MarkdownModel
{
    protected $markdownMediaService;

    public function initializeMarkdownModel()
    {
        $this->markdownMediaService = app(MarkdownMediaService::class);
    }

    public static function fromMarkdown(string $filename): self
    {
        $model = new static();
        $model->initializeMarkdownModel();

        $content = file_get_contents($filename);

        $frontMatter = new FrontMatter();
        $document = $frontMatter->parse($content);

        $data = $document->getData();
        $markdownContent = $document->getContent();

        return static::fillModelFromMarkdown($model, $data, $markdownContent, $filename);
    }

    public static function syncFromMarkdown(string $filename, bool $autoSave = false): self
    {
        $content = file_get_contents($filename);

        $frontMatter = new FrontMatter();
        $document = $frontMatter->parse($content);

        $data = $document->getData();
        $markdownContent = $document->getContent();

        // Determine the slug value
        $slugValue = $data['slug'] ?? pathinfo($filename, PATHINFO_FILENAME);

        // Find existing model by slug or create a new one
        $model = static::firstOrNew(['slug' => $slugValue]);
        $model->initializeMarkdownModel();

        $model = static::fillModelFromMarkdown($model, $data, $markdownContent, $filename);

        if ($autoSave) {
            $model->save();
        }

        return $model;
    }

    protected static function fillModelFromMarkdown(self $model, array $data, string $markdownContent, string $filename): self
    {
        // Extract title from the first line if it starts with #
        $title = null;
        $markdownContent = static::extractTitleFromContent($markdownContent, $title);

        // Use extracted title if available, otherwise use front matter title
        if ($title && !isset($data['title'])) {
            $data['title'] = $title;
        }

        // Handle front matter data
        foreach ($data as $key => $value) {
            if ($model->isFillable($key)) {
                if ($model->hasCast($key)) {
                    $value = $model->castAttribute($key, $value);
                } elseif (is_string($value) && in_array(strtolower($value), ['true', 'false'])) {
                    $value = strtolower($value) === 'true';
                }
                $model->$key = $value;
            }
        }

        // Set the main content
        $contentField = $model->getMarkdownContentField();
        $model->$contentField = $markdownContent;

        // Handle slug
        if (method_exists($model, 'getSlugOptions')) {
            $slugField = $model->getSlugOptions()->slugField;
            if (!isset($model->$slugField)) {
                $model->$slugField = pathinfo($filename, PATHINFO_FILENAME);
            }
        }

        // Save the model before attaching relationships
        $model->save();

        // Handle Media
        if (method_exists($model, 'addMedia')) {
            $model->markdownMediaService->handleMediaFromFrontMatter($model, $data, $filename);
            $model->markdownMediaService->processMarkdownImages($model, $markdownContent, $filename);
        }

        // Handle Spatie Tags
        if (in_array(HasTags::class, class_uses_recursive($model)) && isset($data['tags'])) {
            $model->syncTags($data['tags']);
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

    protected function getMarkdownContentField(): string
    {
        return $this->markdownContentField ?? 'content';
    }
}
