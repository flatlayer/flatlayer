<?php

namespace Tests\Fakes;

use App\Traits\MarkdownModel;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;

class TestMarkdownModel extends Model
{
    use MarkdownModel, HasMedia, HasSlug, HasTags;

    protected $table = 'test_markdown_models';

    protected $fillable = [
        'title',
        'content',
        'slug',
        'published_at',
        'is_published',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function getMarkdownContentField(): string
    {
        return 'content';
    }
}
