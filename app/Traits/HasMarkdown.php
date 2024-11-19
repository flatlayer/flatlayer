<?php

namespace App\Traits;

use App\Services\ImageService;
use App\Services\MarkdownProcessingService;
use App\Support\Path;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait HasMarkdown
{
    protected MarkdownProcessingService $markdownContentService;

    protected ?array $pendingTags = null;

    protected ?array $pendingMedia = null;

    /**
     * Initialize the Markdown model with a specific disk.
     */
    protected function initializeMarkdownModel(Filesystem $disk): void
    {
        $imageService = new ImageService(
            disk: $disk,
            imageManager: new ImageManager(new Driver)
        );

        $this->markdownContentService = new MarkdownProcessingService(
            imageService: $imageService,
            disk: $disk
        );
    }

    /**
     * Create a new model instance from a Markdown file and save it.
     */
    public static function createFromMarkdown(Filesystem $disk, string $relativePath, string $type = 'post'): static
    {
        if (! $disk->exists($relativePath)) {
            throw new \InvalidArgumentException("File not found: {$relativePath}");
        }

        $model = new static(['type' => $type]);
        $model->initializeMarkdownModel($disk);

        // Generate slug from the relative path
        $model->slug = Path::toSlug($relativePath);

        // Process the markdown content
        $processedData = $model->markdownContentService->processMarkdownFile(
            relativePath: $relativePath,
            type: $type,
            slug: $model->slug,
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
            $model->markdownContentService->handleMediaFromFrontMatter($model, $model->pendingMedia, $relativePath);
            $model->content = $model->markdownContentService->processMarkdownImages($model, $model->content, $relativePath);
            $model->pendingMedia = null;

            // Save again if content was modified by image processing
            $model->save();
        }

        return $model->fresh();
    }

    /**
     * Sync an existing model or create a new one from a Markdown file.
     */
    public static function syncFromMarkdown(
        Filesystem $disk,
        string $relativePath,
        string $type = 'post',
        bool $autoSave = false
    ): self {
        $slug = Path::toSlug($relativePath);

        // Find existing or create new
        $model = static::firstOrNew(
            ['type' => $type, 'slug' => $slug],
            ['type' => $type]
        );

        if (! $model->exists) {
            return static::createFromMarkdown($disk, $relativePath, $type);
        }

        // For existing models, update their content
        $model->initializeMarkdownModel($disk);

        // Process the markdown content
        $processedData = $model->markdownContentService->processMarkdownFile(
            relativePath: $relativePath,
            type: $type,
            slug: $model->slug
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
                $model->markdownContentService->handleMediaFromFrontMatter($model, $model->pendingMedia, $relativePath);
                $model->content = $model->markdownContentService->processMarkdownImages($model, $model->content, $relativePath);
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
}
