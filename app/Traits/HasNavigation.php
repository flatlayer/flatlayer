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
        if (!$this->published_at) {
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
        // Get all siblings including current entry
        $siblings = $this->getSiblingsWithSelf();

        // Order siblings by nav_order if available, then by title
        $siblings = $this->orderSiblingsForNavigation($siblings);

        $currentIndex = $siblings->search(fn ($item) => $item->id === $this->id);

        // If we couldn't find the current item in siblings, return null for both
        if ($currentIndex === false) {
            return ['previous' => null, 'next' => null, 'position' => null];
        }

        $previous = $currentIndex > 0
            ? $siblings[$currentIndex - 1]
            : $this->getPreviousFromParent();

        $next = $currentIndex < $siblings->count() - 1
            ? $siblings[$currentIndex + 1]
            : $this->getNextFromParent();

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
     * Get siblings including the current entry.
     */
    protected function getSiblingsWithSelf(): Collection
    {
        return $this->siblings()->push($this);
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
            if (is_numeric($navOrder)) {
                $navOrder = (float) $navOrder;
            } else {
                $navOrder = PHP_FLOAT_MAX;
            }

            // Use title as secondary sort
            return [$navOrder, $entry->title ?? ''];
        })->values();
    }

    /**
     * Get the next entry when we're at the end of current siblings.
     */
    protected function getNextFromParent(): ?Model
    {
        $parent = $this->parent();
        if (!$parent) {
            return null;
        }

        // If we have a parent, get its siblings
        $parentSiblings = $parent->getSiblingsWithSelf();
        $parentSiblings = $this->orderSiblingsForNavigation($parentSiblings);

        $parentIndex = $parentSiblings->search(fn ($item) => $item->id === $parent->id);

        if ($parentIndex < $parentSiblings->count() - 1) {
            // Get the next parent's first child
            $nextParent = $parentSiblings[$parentIndex + 1];

            // Get all children ordered by nav_order and title
            $children = static::query()
                ->where('type', $this->type)
                ->where(function (Builder $query) use ($nextParent) {
                    $prefix = $nextParent->slug === '' ? '' : $nextParent->slug . '/';
                    $query->where('slug', 'like', $prefix . '%')
                        ->whereRaw("replace(slug, ?, '') not like '%/%'", [$prefix]);
                })
                ->orderBy('meta->nav_order', 'asc')
                ->orderBy('title', 'asc')
                ->get();

            return $children->first() ?? $nextParent;
        }

        return null;
    }

    /**
     * Get the previous entry when we're at the start of current siblings.
     */
    protected function getPreviousFromParent(): ?Model
    {
        $parent = $this->parent();
        if (!$parent) {
            return null;
        }

        // If we have a parent, get its siblings
        $parentSiblings = $parent->getSiblingsWithSelf();
        $parentSiblings = $this->orderSiblingsForNavigation($parentSiblings);

        $parentIndex = $parentSiblings->search(fn ($item) => $item->id === $parent->id);

        if ($parentIndex > 0) {
            // Get the previous parent's last child
            $previousParent = $parentSiblings[$parentIndex - 1];

            // Get all children ordered by nav_order and title
            $children = static::query()
                ->where('type', $this->type)
                ->where(function (Builder $query) use ($previousParent) {
                    $prefix = $previousParent->slug === '' ? '' : $previousParent->slug . '/';
                    $query->where('slug', 'like', $prefix . '%')
                        ->whereRaw("replace(slug, ?, '') not like '%/%'", [$prefix]);
                })
                ->orderBy('meta->nav_order', 'desc')
                ->orderBy('title', 'desc')
                ->get();

            return $children->first() ?? $previousParent;
        }

        return $parent;
    }
}
