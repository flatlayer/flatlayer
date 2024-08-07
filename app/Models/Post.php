<?php

namespace App\Models;

use App\Traits\HasMediaFiles;
use App\Traits\MarkdownContentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use Spatie\Tags\HasTags;
use App\Traits\Searchable;
use App\Markdown\CustomMarkdownRenderer;

class Post extends Model
{
    use HasFactory, HasMediaFiles, HasTags, Searchable, MarkdownContentModel;

    protected $fillable = [
        'title',
        'body',
        'content',
        'excerpt',
        'slug',
        'published_at',
        'is_published',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
        'embedding' => Vector::class
    ];

    public static $allowedFilters = ['tags'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main_image')
            ->singleFile();

        $this->addMediaCollection('images');
    }

    public function toSearchableText(): string
    {
        return '# ' . $this->title . "\n\n" . $this->content;
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public static function defaultSearchableQuery(): Builder
    {
        return static::query()->published();
    }

    public function toSummaryArray(): array
    {
        $featuredImage = $this->getMedia('main_image')->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'published_at' => $this->published_at?->toDateTimeString(),
            'tags' => $this->tags->pluck('name')->toArray(),
            'featured_image' => $featuredImage ? $featuredImage->getImgTag(['100vw'], [], false, [150, 150]) : null,
        ];
    }

    public function toDetailArray(): array
    {
        $markdownRenderer = new CustomMarkdownRenderer($this);
        $parsedContent = $markdownRenderer->convertToHtml($this->content);
        $featuredImage = $this->getMedia('featured')->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $parsedContent->getContent(),
            'excerpt' => $this->excerpt,
            'published_at' => $this->published_at?->toDateTimeString(),
            'is_published' => $this->is_published,
            'tags' => $this->tags->pluck('name')->toArray(),
            'featured_image' => $featuredImage ? $featuredImage->getImgTag(['100vw'], [], true) : null,
        ];
    }
}
