<?php

namespace Tests\Fakes;

use App\Traits\HasMedia;
use App\Traits\MarkdownModel;
use Illuminate\Database\Eloquent\Model;
use Spatie\Tags\HasTags;
use Illuminate\Support\Collection;

class TestMarkdownModel extends Model
{
    use MarkdownModel, HasTags, HasMedia;

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

    public function getMarkdownContentField(): string
    {
        return 'content';
    }
}
