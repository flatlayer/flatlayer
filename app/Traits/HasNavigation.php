<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasNavigation
{
    /**
     * Get the next and previous entries based on specified navigation type.
     *
     * @param  string  $type  Either 'hierarchical' or 'chronological'
     * @return array{
     *     previous: ?static,
     *     next: ?static,
     *     position: ?array{current: int, total: int}
     * }
     */
    public function getNavigation(string $type = 'hierarchical'): array
    {
        return match ($type) {
            'chronological' => $this->getChronologicalNavigation(),
            'hierarchical' => $this->getHierarchicalNavigation(),
            default => throw new \InvalidArgumentException("Invalid navigation type: {$type}")
        };
    }

    /**
     * Get next and previous entries based on publication date.
     */
    protected function getChronologicalNavigation(): array
    {
        // If the current entry isn't published, return empty navigation
        if (! $this->published_at) {
            return [
                'previous' => null,
                'next' => null,
                'position' => null,
            ];
        }

        // Only get published entries of the same type
        $baseQuery = static::query()
            ->where('type', $this->type)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        $previous = $baseQuery->clone()
            ->where('published_at', '<', $this->published_at)
            ->orderBy('published_at', 'desc')
            ->first();

        $next = $baseQuery->clone()
            ->where('published_at', '>', $this->published_at)
            ->orderBy('published_at', 'asc')
            ->first();

        // Get position in chronological sequence
        $total = $baseQuery->clone()->count();
        $current = $baseQuery->clone()
            ->where('published_at', '>=', $this->published_at)
            ->count();

        return [
            'previous' => $previous,
            'next' => $next,
            'position' => ['current' => $total - $current + 1, 'total' => $total],
        ];
    }

    /**
     * Get next and previous entries based on hierarchical structure.
     */
    protected function getHierarchicalNavigation(): array
    {
        // Get immediate siblings of the current entry
        $siblings = $this->getSiblingsWithSelf();

        // Order siblings by nav_order if available, then by title
        $siblings = $this->orderSiblingsForNavigation($siblings);

        $currentIndex = $siblings->search(fn ($item) => $item->id === $this->id);

        // If we couldn't find the current item in siblings, return null for both
        if ($currentIndex === false) {
            return ['previous' => null, 'next' => null, 'position' => null];
        }

        // For first item in siblings list, get previous from parent
        $previous = $currentIndex > 0
            ? $siblings[$currentIndex - 1]
            : $this->getPreviousFromParent();

        // For last item in siblings list, get next from parent
        $next = $currentIndex < $siblings->count() - 1
            ? $siblings[$currentIndex + 1]
            : $this->getNextFromParent();

        // If we're at a root node, get the first child as next
        if ($this->isRootNode() && ! $next) {
            $next = $this->getFirstChild();
        }

        return [
            'previous' => $previous,
            'next' => $next,
            'position' => [
                'current' => $currentIndex + 1,
                'total' => $siblings->count(),
            ],
        ];
    }

    /**
     * Check if this is a root level node.
     */
    protected function isRootNode(): bool
    {
        return ! str_contains($this->slug, '/');
    }

    /**
     * Get the first child of the specified parent.
     */
    protected function getFirstChild(?Model $parent = null): ?Model
    {
        $prefix = $parent ? $parent->slug.'/' : $this->slug.'/';

        return static::query()
            ->where('type', $this->type)
            ->where(function (Builder $query) use ($prefix) {
                $query->where('slug', 'like', $prefix.'%')
                    ->whereRaw("replace(slug, ?, '') not like '%/%'", [$prefix]);
            })
            ->orderBy('meta->nav_order', 'asc')
            ->orderBy('title', 'asc')
            ->first();
    }

    /**
     * Get siblings including the current entry.
     */
    protected function getSiblingsWithSelf(): Collection
    {
        // Get the parent path from the current slug
        $parentPath = dirname($this->slug);
        $prefix = $parentPath === '.' ? '' : $parentPath.'/';

        // Get all entries at the same level
        $siblings = static::query()
            ->where('type', $this->type)
            ->where(function (Builder $query) use ($prefix) {
                if (empty($prefix)) {
                    $query->whereRaw("slug not like '%/%'");
                } else {
                    $query->where('slug', 'like', $prefix.'%')
                        ->whereRaw("replace(slug, ?, '') not like '%/%'", [$prefix]);
                }
            })
            ->get();

        // Add current entry if not already in collection
        if (! $siblings->contains(fn ($item) => $item->id === $this->id)) {
            $siblings->push($this);
        }

        return $siblings->unique('id');
    }

    /**
     * Order siblings for navigation, respecting nav_order in meta if available.
     */
    protected function orderSiblingsForNavigation(Collection $siblings): Collection
    {
        return $siblings->sortBy(function ($entry) {
            // First try to get nav_order from meta
            $navOrder = $entry->meta['nav_order'] ?? PHP_FLOAT_MAX;

            // Convert to float for consistent sorting
            $navOrder = match (true) {
                is_numeric($navOrder) => (float) $navOrder,
                default => PHP_FLOAT_MAX
            };

            // Use title as secondary sort, with nullsafe operator
            $title = $entry->title ?? '';

            return [$navOrder, $title];
        })->values();
    }

    /**
     * Get the next entry when we're at the end of current siblings.
     */
    protected function getNextFromParent(): ?Model
    {
        // Get parent path from slug
        $parentPath = dirname($this->slug);
        if ($parentPath === '.') {
            return null;
        }

        // Get the parent
        $parent = static::query()
            ->where('type', $this->type)
            ->where('slug', $parentPath)
            ->first();

        if (! $parent) {
            return null;
        }

        // Get parent's siblings
        $parentSiblings = static::query()
            ->where('type', $this->type)
            ->where(function (Builder $query) use ($parent) {
                $grandParentPath = dirname($parent->slug);
                $prefix = $grandParentPath === '.' ? '' : $grandParentPath.'/';

                if (empty($prefix)) {
                    $query->whereRaw("slug not like '%/%'");
                } else {
                    $query->where('slug', 'like', $prefix.'%')
                        ->whereRaw("replace(slug, ?, '') not like '%/%'", [$prefix]);
                }
            })
            ->get();

        $parentSiblings = $this->orderSiblingsForNavigation($parentSiblings);
        $parentIndex = $parentSiblings->search(fn ($item) => $item->id === $parent->id);

        if ($parentIndex !== false && $parentIndex < $parentSiblings->count() - 1) {
            // Get the next parent
            $nextParent = $parentSiblings[$parentIndex + 1];

            // When moving between sections, prefer the section header (parent)
            // over its first child
            if ($this->isLastChild($parent)) {
                return $nextParent;
            }

            // If we're not the last child, get the first child of the next section
            return $this->getFirstChild($nextParent);
        }

        return null;
    }

    /**
     * Check if this entry is the last child of its parent.
     */
    protected function isLastChild(Model $parent): bool
    {
        $siblings = static::query()
            ->where('type', $this->type)
            ->where(function (Builder $query) use ($parent) {
                $prefix = $parent->slug.'/';
                $query->where('slug', 'like', $prefix.'%')
                    ->whereRaw("replace(slug, ?, '') not like '%/%'", [$prefix]);
            })
            ->orderBy('meta->nav_order', 'desc')
            ->orderBy('title', 'desc')
            ->get();

        return $siblings->first()?->id === $this->id;
    }

    /**
     * Get the previous entry when we're at the start of current siblings.
     */
    protected function getPreviousFromParent(): ?Model
    {
        $parent = $this->parent();
        if (! $parent) {
            return null;
        }

        // Return the parent if we're the first sibling
        return $parent;
    }
}
