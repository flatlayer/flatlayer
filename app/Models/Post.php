<?php

namespace App\Models;

use App\Traits\HasMedia;
use App\Traits\MarkdownModel;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use Spatie\Tags\HasTags;
use WendellAdriel\Lift\Attributes\Cast;
use WendellAdriel\Lift\Attributes\Fillable;
use WendellAdriel\Lift\Attributes\PrimaryKey;
use WendellAdriel\Lift\Lift;

class Post extends Model
{
    use HasFactory, HasMedia, HasTags, Searchable, MarkdownModel, Lift;

    #[PrimaryKey]
    public int $id;

    #[Fillable]
    public string $slug;

    #[Fillable]
    public string $title;

    #[Fillable]
    public string $content;

    #[Fillable]
    public ?string $excerpt;

    #[Fillable]
    #[Cast('datetime')]
    public ?\DateTime $published_at;

    #[Fillable]
    #[Cast('boolean')]
    public bool $is_published = false;

    public function toSearchableText(): string
    {
        return '# ' . $this->title . "\n\n" . $this->content;
    }

    public function scopePublished($query): Builder
    {
        return $query->where('is_published', true)
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function defaultSearchableQuery(): Builder
    {
        return static::query()->published();
    }
}
