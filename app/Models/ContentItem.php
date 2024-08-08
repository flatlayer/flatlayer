<?php

namespace App\Models;

use App\Filter\ContentItemArrayConverter;
use App\Traits\HasMediaFiles;
use App\Traits\MarkdownContentModel;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use Spatie\Tags\HasTags;

class ContentItem extends Model
{
    use HasFactory, HasMediaFiles, HasTags, Searchable, MarkdownContentModel;

    protected $fillable = [
        'type',
        'title',
        'slug',
        'content',
        'excerpt',
        'meta',
        'published_at',
        'filename',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'meta' => 'array',
        'embedding' => Vector::class,
    ];

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function toSearchableText(): string
    {
        return $this->title . "\n\n" . $this->content;
    }

    public function getMarkdownContentField(): string
    {
        return 'content';
    }

    public function toArray($fields = null): array
    {
        return (new ContentItemArrayConverter())->toArray($this, $fields);
    }

    public static function findByTypeAndSlug($type, $slug)
    {
        return static::where('type', $type)->where('slug', $slug)->firstOrFail();
    }
}
