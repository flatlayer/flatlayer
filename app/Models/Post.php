<?php

namespace App\Models;

use App\Traits\HasMedia;
use App\Traits\MarkdownModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use Spatie\Tags\HasTags;
use App\Traits\Searchable;

class Post extends Model
{
    use HasFactory, HasMedia, HasTags, Searchable, MarkdownModel;

    protected $fillable = [
        'title',
        'body',
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
}
