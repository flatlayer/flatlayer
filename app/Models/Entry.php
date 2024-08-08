<?php

namespace App\Models;

use App\Query\EntrySerializer;
use App\Traits\HasAssets;
use App\Traits\HasMarkdown;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use Spatie\Tags\HasTags;

class Entry extends Model
{
    use HasFactory, HasAssets, HasTags, Searchable, HasMarkdown;

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
        return (new EntrySerializer())->toArray($this, $fields);
    }

    public static function findByTypeAndSlug($type, $slug)
    {
        return static::where('type', $type)->where('slug', $slug)->firstOrFail();
    }
}
