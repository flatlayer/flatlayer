<?php

namespace App\Models;

use App\Query\EntrySerializer;
use App\Query\Exceptions\InvalidCastException;
use App\Query\Exceptions\QueryException;
use App\Rules\ValidPath;
use App\Traits\GeneratesContentSlugs;
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
    use HasFactory, HasImages, HasMarkdown, HasTags, Searchable, GeneratesContentSlugs;

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
     * Order entries by their path segments and any numeric prefixes.
     */
    protected function orderEntriesNaturally(Collection $entries): Collection
    {
        return $entries->sortBy(function ($entry) {
            $segments = explode('/', $entry->slug);
            $basename = end($segments);

            // Extract any leading number from the basename (e.g., "01-introduction" -> "01")
            $order = 999999;
            if (preg_match('/^(\d+)-/', $basename, $matches)) {
                $order = (int)$matches[1];
            }

            return [count($segments), $order, $basename];
        })->values();
    }

    /**
     * Get all ancestors of the current entry.
     */
    public function ancestors(): Collection
    {
        if ($this->slug === '') {
            return collect();
        }

        // Get all parent paths up to the root
        $segments = explode('/', $this->slug);
        array_pop($segments); // Remove current segment

        $ancestorPaths = [];
        $currentPath = '';
        foreach ($segments as $segment) {
            $currentPath = $currentPath ? "{$currentPath}/{$segment}" : $segment;
            $ancestorPaths[] = $currentPath;
        }

        if (empty($ancestorPaths)) {
            return collect();
        }

        return static::where('type', $this->type)
            ->whereIn('slug', $ancestorPaths)
            ->orderBy('slug')
            ->get();
    }

    /**
     * Get the immediate parent entry.
     */
    public function parent(): ?self
    {
        if ($this->slug === '') {
            return null;
        }

        $parentPath = dirname($this->slug);
        if ($parentPath === '.') {
            return null;
        }

        return static::where('type', $this->type)
            ->where('slug', $parentPath)
            ->first();
    }

    /**
     * Get immediate child entries.
     */
    public function children(): Collection
    {
        $prefix = $this->slug === '' ? '' : $this->slug . '/';

        $entries = static::where('type', $this->type)
            ->where('slug', 'like', $prefix . '%')
            ->where(function ($query) use ($prefix) {
                $query->whereRaw('replace(slug, ?, "") not like "%/%"', [$prefix]);
            })
            ->where('slug', '!=', $this->slug)
            ->get();

        return $this->orderEntriesNaturally($entries);
    }

    /**
     * Get all descendant entries.
     */
    public function descendants(): Collection
    {
        if ($this->slug === '') {
            $entries = static::where('type', $this->type)
                ->where('slug', '!=', '')
                ->get();
        } else {
            $entries = static::where('type', $this->type)
                ->where('slug', 'like', $this->slug . '/%')
                ->get();
        }

        return $this->orderEntriesNaturally($entries);
    }

    /**
     * Get siblings (entries at the same level).
     */
    public function siblings(): Collection
    {
        if ($this->slug === '') {
            return static::where('type', $this->type)
                ->where('slug', 'not like', '%/%')
                ->where('slug', '!=', '')
                ->get();
        }

        $parentPath = dirname($this->slug);
        if ($parentPath === '.') {
            $parentPath = '';
        }

        $prefix = $parentPath === '' ? '' : $parentPath . '/';

        $entries = static::where('type', $this->type)
            ->where('slug', '!=', $this->slug)  // Not the current entry
            ->where('slug', '!=', $parentPath)  // Not the parent
            ->where('slug', 'like', $prefix . '%')  // In the same directory
            ->whereRaw('replace(slug, ?, "") not like "%/%"', [$prefix])  // Not in subdirectories
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
     * Set the slug attribute with validation and normalization.
     */
    public function setSlugAttribute(string $value): void
    {
        // First normalize the path
        $normalized = $this->generateSlug($value);

        // Validate the normalized path
        $validator = Validator::make(
            ['slug' => $normalized],  // Validate the normalized value
            ['slug' => [
                'nullable',
                new ValidPath
            ]]
        );

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first('slug'));
        }

        $this->attributes['slug'] = $normalized;
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
        return '# ' . $this->title . "\n\n" . $this->excerpt . "\n\n" . $this->stripMdxComponents($this->content ?? '');
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
