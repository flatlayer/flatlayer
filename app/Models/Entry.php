<?php

namespace App\Models;

use App\Query\EntrySerializer;
use App\Traits\HasImages;
use App\Traits\HasMarkdown;
use App\Traits\HasTags;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;

/**
 * Class Entry
 *
 * Represents a content entry in the application.
 *
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $excerpt
 * @property array $meta
 * @property \DateTime|null $published_at
 * @property string $filename
 * @property Vector $embedding
 */
class Entry extends Model
{
    use HasFactory, HasImages, HasMarkdown, HasTags, Searchable;

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

    /**
     * Scope a query to only include published entries.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope a query to entries of a specific type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Get the searchable text representation of the entry.
     */
    public function toSearchableText(): string
    {
        return $this->title."\n\n".$this->stripMdxComponents($this->content);
    }

    /**
     * Get the field name that contains the Markdown content.
     */
    public function getMarkdownContentField(): string
    {
        return 'content';
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray(?array $fields = null): array
    {
        return (new EntrySerializer)->toArray($this, $fields);
    }

    /**
     * Find an entry by its type and slug.
     */
    public static function findByTypeAndSlug(string $type, string $slug): self
    {
        return static::where('type', $type)->where('slug', $slug)->firstOrFail();
    }
}
