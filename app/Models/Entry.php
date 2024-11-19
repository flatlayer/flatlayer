<?php

namespace App\Models;

use App\Query\EntrySerializer;
use App\Rules\ValidPath;
use App\Support\Path;
use App\Traits\HasImages;
use App\Traits\HasMarkdown;
use App\Traits\HasNavigation;
use App\Traits\HasTags;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Pgvector\Laravel\Vector;

class Entry extends Model
{
    use HasFactory,
        HasImages,
        HasMarkdown,
        HasNavigation,
        HasTags,
        Searchable;

    /**
     * The attributes that are mass assignable.
     */
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

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'published_at' => 'datetime',
        'meta' => 'array',
        'embedding' => Vector::class,
    ];

    /**
     * Set the slug attribute with validation and normalization.
     */
    public function setSlugAttribute(string $value): void
    {
        $normalized = Path::toSlug($value);

        $validator = Validator::make(
            ['slug' => $normalized],
            ['slug' => [
                'nullable',
                new ValidPath,
            ]]
        );

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first('slug'));
        }

        $this->attributes['slug'] = $normalized;
    }

    /**
     * Determine if the entry is an index file.
     */
    public function getIsIndexAttribute(): bool
    {
        return basename($this->filename) === 'index.md';
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
     * Get all ancestors of the current entry.
     */
    public function ancestors(): Collection
    {
        $ancestorPaths = Path::ancestors($this->slug);

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
        $parentPath = Path::parent($this->slug);

        if (empty($parentPath)) {
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
        $allSlugs = static::where('type', $this->type)->pluck('slug')->all();
        $childPaths = Path::children($this->slug, $allSlugs);

        $entries = static::where('type', $this->type)
            ->whereIn('slug', $childPaths)
            ->get();

        return $this->orderEntriesNaturally($entries);
    }

    /**
     * Get all descendant entries.
     */
    public function descendants(): Collection
    {
        $prefix = $this->slug === '' ? '' : $this->slug.'/';

        $entries = static::where('type', $this->type)
            ->where('slug', '!=', '')
            ->when($this->slug !== '', function ($query) use ($prefix) {
                $query->where('slug', 'like', $prefix.'%');
            })
            ->get();

        return $this->orderEntriesNaturally($entries);
    }

    /**
     * Get siblings (entries at the same level).
     */
    public function siblings(): Collection
    {
        $allSlugs = static::where('type', $this->type)->pluck('slug')->all();
        $siblingPaths = Path::siblings($this->slug, $allSlugs);

        $entries = static::where('type', $this->type)
            ->whereIn('slug', $siblingPaths)
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
     * Get the searchable text representation of the entry.
     */
    public function toSearchableText(): string
    {
        return '# '.$this->title."\n\n".$this->excerpt."\n\n".$this->stripMdxComponents($this->content ?? '');
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
        return static::where('type', $type)
            ->where('slug', Path::toSlug($slug))
            ->firstOrFail();
    }

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
                $order = (int) $matches[1];
            }

            return [count($segments), $order, $basename];
        })->values();
    }

    /**
     * Get the field name that contains the Markdown content.
     */
    protected function getMarkdownContentField(): string
    {
        return 'content';
    }
}
