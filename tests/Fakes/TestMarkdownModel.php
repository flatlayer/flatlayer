<?php

namespace Tests\Fakes;

use App\Traits\MarkdownModel;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;
use Illuminate\Support\Collection;

class TestMarkdownModel extends Model
{
    use MarkdownModel, HasSlug, HasTags;

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

    public $media;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->media = new Collection();
        $this->initializeMarkdownModel();
    }

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

    public function addMedia($file)
    {
        return new class($this) {
            protected $model;

            public function __construct($model)
            {
                $this->model = $model;
            }

            public function toMediaCollection($collection)
            {
                $this->model->media->push(['collection' => $collection]);
            }
        };
    }
}
