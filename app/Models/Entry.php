<?php

namespace App\Models;

use App\Query\EntrySerializer;
use App\Query\Exceptions\InvalidCastException;
use App\Query\Exceptions\QueryException;
use App\Rules\ValidPath;
use App\Traits\HasImages;
use App\Traits\HasMarkdown;
use App\Traits\HasTags;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Pgvector\Laravel\Vector;

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
     * Order a collection of entries using natural file system ordering.
     */
    protected function orderEntriesNaturally(Collection $entries): Collection
    {
        return $entries->sortBy(function ($entry) {
            // Split the path into segments
            $segments = explode('/', $entry->slug);

            // Get the basename (last segment)
            $basename = end($segments);

            // Special handling for index files - they should come first in their directory
            if ($entry->is_index) {
                return [count($segments), 0, ''];
            }

            // Extract any leading number from the basename (e.g., "01-introduction" -> "01")
            $order = 999999;
            if (preg_match('/^(\d+)-/', $basename, $matches)) {
                $order = (int)$matches[1];
            }

            return [count($segments), $order, $basename];
        })->values();
    }

    /**
     * Get ancestors.
     */
    public function ancestors(): Collection
    {
        if ($this->slug === '' || !str_contains($this->slug, '/')) {
            return collect();
        }

        $ancestorSlugs = collect();
        $parts = explode('/', $this->slug);
        array_pop($parts); // Remove current slug

        $currentPath = '';
        foreach ($parts as $part) {
            $currentPath = $currentPath ? "{$currentPath}/{$part}" : $part;
            $ancestorSlugs->push($currentPath . '/index');
        }

        // Get ancestors and ensure they're in the correct order by path depth
        $entries = static::where('type', $this->type)
            ->whereIn('slug', $ancestorSlugs)
            ->get();

        // Return in path order (shorter paths first)
        return $entries->sortBy(function ($entry) {
            return substr_count($entry->slug, '/');
        })->values();
    }

    /**
     * Get the immediate parent entry.
     */
    public function parent(): ?self
    {
        if ($this->slug === '' || !str_contains($this->slug, '/')) {
            return null;
        }

        $parentPath = dirname($this->slug);
        if ($parentPath === '.') {
            return null;
        }

        return static::where('type', $this->type)
            ->where('slug', $parentPath . '/index')
            ->first();
    }

    /**
     * Get immediate child entries.
     */
    public function children(): Collection
    {
        $prefix = dirname($this->slug) . '/';
        if ($prefix === './') {
            $prefix = '';
        }

        $entries = static::where('type', $this->type)
            ->where('slug', 'like', $prefix . '%')
            ->where(function ($query) use ($prefix) {
                $query->whereRaw('replace(slug, ?, "") not like "%/%"', [$prefix])
                    ->where('slug', '!=', $this->slug);
            })
            ->get();

        return $this->orderEntriesNaturally($entries);
    }

    /**
     * Get all descendant entries (children, grandchildren, etc.).
     */
    public function descendants(): Collection
    {
        if ($this->slug === '') {
            $entries = static::where('type', $this->type)
                ->where('slug', '!=', '')
                ->get();
        } else {
            $prefix = dirname($this->slug) . '/';
            if ($prefix === './') {
                $prefix = '';
            }

            $entries = static::where('type', $this->type)
                ->where('slug', 'like', $prefix . '%')
                ->where('slug', '!=', $this->slug)
                ->get();
        }

        return $this->orderEntriesNaturally($entries);
    }

    /**
     * Get siblings (entries with the same parent).
     */
    public function siblings(): Collection
    {
        // If we're at root level
        if (!str_contains($this->slug, '/')) {
            // Only get root level files that:
            // - Aren't index files
            // - Aren't the current file
            // - Don't contain any slashes (not in subdirectories)
            $entries = static::where('type', $this->type)
                ->where('is_index', false) // Explicitly exclude all index files
                ->where('slug', '!=', $this->slug)
                ->whereRaw('slug not like "%/%"')
                ->get();

            return $this->orderEntriesNaturally($entries);
        }

        // Get the parent path
        $parentPath = dirname($this->slug);

        // Get siblings at the same level
        $entries = static::where('type', $this->type)
            ->where('slug', '!=', $this->slug) // Exclude self
            ->where('slug', 'like', $parentPath . '/%') // Same parent directory
            ->where(function ($query) use ($parentPath) {
                // Either it's not an index file or it doesn't have further nesting
                $query->where('is_index', false)
                    ->whereRaw('replace(slug, ?, "") not like "%/%"', [$parentPath . '/']);
            })
            ->get();

        return $this->orderEntriesNaturally($entries);
    }

    /**
     * Get the breadcrumb path to this entry.
     */
    public function breadcrumbs(): Collection
    {
        return $this->ancestors()->push($this);
    }

    /**
     * Generate a slug from a file path.
     */
    public static function generateSlugFromPath(string $path): string
    {
        return (new static)->normalizePath($path);
    }

    /**
     * Set the slug attribute with validation and normalization.
     */
    public function setSlugAttribute(string $value): void
    {
        // First normalize the path
        $normalized = $this->normalizePath($value);

        // Then validate the normalized path
        $validator = Validator::make(
            ['slug' => $normalized],
            ['slug' => ['required', new ValidPath]]
        );

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first('slug'));
        }

        // Set the normalized and validated path
        $this->attributes['slug'] = $normalized;

        // Set is_index based on whether the original file was index.md
        $this->attributes['is_index'] = str_ends_with($value, 'index.md');
    }

    /**
     * Normalize a path for use as a slug.
     */
    protected function normalizePath(string $path): string
    {
        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Remove multiple consecutive slashes
        $path = preg_replace('#/+#', '/', $path);

        // Remove leading and trailing slashes first
        $path = trim($path, '/');

        // Then remove .md extension
        return preg_replace('/\.md$/', '', $path);
    }

    /**
     * Find an entry by its type and slug.
     */
    public static function findByTypeAndSlug(string $type, string $slug): self
    {
        return static::where('type', $type)
            ->where('slug', $slug)
            ->firstOrFail();
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
        return '# '.$this->title."\n\n".$this->excerpt."\n\n".$this->stripMdxComponents($this->content ?? '');
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
