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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
 * @property bool $is_index
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
        'is_index',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'meta' => 'array',
        'embedding' => Vector::class,
        'is_index' => 'boolean',
    ];

    /**
     * Get all ancestor entries (parent, grandparent, etc.).
     */
    public function ancestors(): Collection
    {
        if ($this->slug === '' || !str_contains($this->slug, '/')) {
            return collect();
        }

        return static::where('type', $this->type)
            ->whereIn('slug', $this->getAncestorSlugs())
            ->orderBy('slug')
            ->get();
    }

    /**
     * Get the immediate parent entry.
     */
    public function parent(): ?self
    {
        $parentSlug = $this->getParentSlug();

        if (!$parentSlug) {
            return null;
        }

        return static::where('type', $this->type)
            ->where('slug', $parentSlug)
            ->first();
    }

    /**
     * Get immediate child entries.
     */
    public function children(): Collection
    {
        $prefix = $this->slug === '' ? '' : $this->slug . '/';

        return static::where('type', $this->type)
            ->where('slug', 'like', $prefix . '%')
            ->whereRaw('slug NOT LIKE ?', [$prefix . '%/%'])
            ->orderBy('slug')
            ->get();
    }

    /**
     * Get all descendant entries (children, grandchildren, etc.).
     */
    public function descendants(): Collection
    {
        if ($this->slug === '') {
            return static::where('type', $this->type)
                ->where('slug', '!=', '')
                ->orderBy('slug')
                ->get();
        }

        return static::where('type', $this->type)
            ->where('slug', 'like', $this->slug . '/%')
            ->orderBy('slug')
            ->get();
    }

    /**
     * Get the dirname part of a path for the current database driver.
     */
    protected function getDirnameExpression(string $column): string
    {
        $driver = \DB::connection()->getDriverName();

        return match($driver) {
            'pgsql' => "CASE
                WHEN position('/' in $column) = 0 THEN ''
                ELSE substring($column from '^(.+)/[^/]*$')
            END",
            'sqlite' => "CASE
                WHEN instr($column, '/') = 0 THEN ''
                ELSE substr($column, 1, length($column) - length(substr($column, -instr(reverse($column), '/'))))
            END",
            default => throw new \Exception("Unsupported database driver: $driver")
        };
    }

    /**
     * Get siblings (entries with the same parent).
     */
    public function siblings(): Collection
    {
        $query = static::where('type', $this->type)
            ->where('slug', '!=', $this->slug);

        if (!str_contains($this->slug, '/')) {
            // Root level entries
            $query->whereRaw("position('/' in slug) = 0");
        } else {
            // Get siblings with same parent path
            $dirnameExpr = $this->getDirnameExpression('slug');
            $parentSlug = $this->getParentSlug();
            $query->whereRaw("$dirnameExpr = ?", [$parentSlug]);
        }

        return $query->orderBy('slug')->get();
    }

    /**
     * Get the breadcrumb path to this entry.
     */
    public function breadcrumbs(): Collection
    {
        return $this->ancestors()->push($this);
    }

    /**
     * Get the parent slug.
     */
    protected function getParentSlug(): ?string
    {
        if ($this->slug === '' || !str_contains($this->slug, '/')) {
            return null;
        }

        $parentSlug = dirname($this->slug);
        return $parentSlug === '.' ? '' : $parentSlug;
    }

    /**
     * Get an array of ancestor slugs.
     */
    protected function getAncestorSlugs(): array
    {
        $slugs = [];
        $parts = explode('/', $this->slug);
        array_pop($parts);

        $currentPath = '';
        foreach ($parts as $part) {
            $currentPath = $currentPath ? "$currentPath/$part" : $part;
            $slugs[] = $currentPath;
        }

        return $slugs;
    }

    /**
     * Generate a slug from a file path.
     */
    public static function generateSlugFromPath(string $path): string
    {
        // Remove file extension
        $slug = preg_replace('/\.md$/', '', $path);

        // Handle index files
        if (basename($slug) === 'index') {
            $slug = dirname($slug);
            // If we're at root level, return empty string
            if ($slug === '.') {
                return '';
            }
        }

        // Convert backslashes to forward slashes
        $slug = str_replace('\\', '/', $slug);

        // Remove leading/trailing slashes
        return trim($slug, '/');
    }

    /**
     * Set the slug attribute with proper handling for index files.
     */
    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = $value;
        $this->is_index = basename($value) === 'index';
    }

    /**
     * Resolve slug conflicts by appending a suffix if needed.
     */
    public function resolveSlugConflict(): void
    {
        $baseSlug = $this->slug;
        $counter = 1;

        while (static::where('type', $this->type)
            ->where('slug', $this->slug)
            ->where('id', '!=', $this->id)
            ->exists()) {
            $this->slug = $baseSlug . '-' . $counter++;
        }
    }

    /**
     * Find an entry by its type and slug.
     */
    public static function findByTypeAndSlug(string $type, string $slug): self
    {
        return static::where('type', $type)->where('slug', $slug)->firstOrFail();
    }

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
        return '# '.$this->title."\n\n".$this->excerpt."\n\n".$this->stripMdxComponents($this->content);
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
}
