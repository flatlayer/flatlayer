<?php

namespace App\Services\Content;

use App\Models\Entry;
use App\Query\EntrySerializer;
use App\Support\Path;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ContentHierarchy
{
    private const MAX_ALLOWED_DEPTH = 10;

    public function __construct(
        protected readonly EntrySerializer $serializer
    ) {}

    /**
     * Convert a flat collection of entries into a hierarchical structure.
     *
     * @param  string  $type  The content type to build hierarchy for
     * @param  string|null  $root  Optional root path to start from
     * @param  array  $options  Additional options for hierarchy generation
     *                          - depth: Maximum depth to traverse (null for unlimited)
     *                          - fields: Array of fields to include in node data
     *                          - navigation_fields: Array of fields for child nodes
     *                          - sort: Sort nodes by field/direction, e.g. ['title' => 'asc']
     * @return array Hierarchical structure of entries
     *
     * @throws \InvalidArgumentException If type is invalid or entries not found
     */
    public function buildHierarchy(string $type, ?string $root = null, array $options = []): array
    {
        $query = Entry::where('type', $type);

        // Add root path filtering if specified
        if ($root !== null) {
            $root = Path::toSlug($root);
            $query->where(function ($q) use ($root) {
                $q->where('slug', $root)
                    ->orWhere('slug', 'like', $root.'/%');
            });
        }

        // Get all entries
        $entries = $query->get();
        if ($entries->isEmpty()) {
            if ($root !== null) {
                return [];
            }
            throw new \InvalidArgumentException("No entries found for type: {$type}");
        }

        // Build hierarchy
        $grouped = $this->groupByParent($entries, $root);
        $rootEntries = $grouped->get('', collect());
        $childGroups = $grouped->filter(fn($group, $key) => $key !== '');

        return $this->buildNodes($rootEntries, $childGroups, $options);
    }

    /**
     * Filter entries by a root path.
     */
    protected function filterByRoot(Collection $entries, string $root): Collection
    {
        return $entries->filter(function ($entry) use ($root) {
            return $entry->slug === $root ||
                str_starts_with($entry->slug, $root.'/');
        });
    }

    /**
     * Group entries by their parent paths.
     */
    protected function groupByParent(Collection $entries, ?string $root = null): SupportCollection
    {
        // For empty root, handle empty slug entry specially
        if ($root === '') {
            $groups = new SupportCollection();
            $groups[''] = $entries->where('slug', '')->values();

            // Group all other entries by parent
            $nonRootGroups = $entries->where('slug', '!=', '')
                ->groupBy(function ($entry) {
                    return Path::parent($entry->slug);
                });

            return $groups->merge($nonRootGroups);
        }

        // For all other cases, just group by parent
        return $entries->groupBy(function ($entry) {
            return Path::parent($entry->slug);
        });
    }

    /**
     * Build nodes for the current level in the hierarchy.
     *
     * @param  SupportCollection  $entries  Entries at the current level
     * @param  SupportCollection  $grouped  All entries grouped by parent
     * @param  array  $options  Hierarchy options
     * @param  int  $depth  Current depth
     * @return array Array of hierarchical nodes
     */
    protected function buildNodes(
        SupportCollection $entries,
        SupportCollection $grouped,
        array $options,
        int $depth = 0
    ): array {
        $maxDepth = min(
            $options['depth'] ?? self::MAX_ALLOWED_DEPTH,
            self::MAX_ALLOWED_DEPTH
        );

        if ($depth >= $maxDepth || $entries->isEmpty()) {
            return [];
        }

        $entries = $this->sortByNavOrder($entries);
        if (isset($options['sort'])) {
            $entries = $this->applySorting($entries, $options['sort']);
        }

        $nodes = [];
        foreach ($entries as $entry) {
            $fields = $depth === 0
                ? ($options['fields'] ?? [])
                : ($options['navigation_fields'] ?? $options['fields'] ?? []);

            $node = $this->serializer->toArray($entry, $fields);

            if ($children = $grouped->get($entry->slug, collect())) {
                $children = $children->filter(fn ($child) => $child->id !== $entry->id);
                $children = $this->sortByNavOrder($children);
                if (isset($options['sort'])) {
                    $children = $this->applySorting($children, $options['sort']);
                }

                $childNodes = $this->buildNodes($children, $grouped, $options, $depth + 1);
                if (!empty($childNodes)) {
                    $node['children'] = $childNodes;
                }
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Sort entries by nav_order meta field.
     * Lower nav_order values come first, falling back to title for entries without nav_order.
     */
    protected function sortByNavOrder(SupportCollection $entries): SupportCollection
    {
        return $entries->sortBy(fn ($entry) => [
            is_numeric($entry->meta['nav_order'] ?? null)
                ? (int) $entry->meta['nav_order']
                : PHP_FLOAT_MAX,
            $entry->title
        ]);
    }

    /**
     * Apply additional custom sorting.
     */
    protected function applySorting(SupportCollection $entries, array $sort): SupportCollection
    {
        foreach ($sort as $field => $direction) {
            $entries = $entries->sortBy(function ($entry) use ($field) {
                if (str_starts_with($field, 'meta.')) {
                    $key = substr($field, 5);
                    $value = $entry->meta[$key] ?? null;

                    // Handle numeric meta values consistently
                    if (is_numeric($value)) {
                        return (int) $value; // Cast to integer like nav_order
                    }

                    // Return high value for missing meta fields
                    return $value ?? PHP_FLOAT_MAX;
                }

                // Handle direct model attributes
                return $entry->$field ?? PHP_FLOAT_MAX;
            }, SORT_REGULAR, $direction === 'desc');
        }

        return $entries;
    }

    /**
     * Find a specific node in the hierarchy by its path.
     *
     * @param  array  $hierarchy  The hierarchy to search
     * @param  string  $path  The path to find
     * @return array|null The found node or null
     */
    public function findNode(array $hierarchy, string $path): ?array
    {
        $normalizedPath = Path::toSlug($path);

        foreach ($hierarchy as $node) {
            if ($node['slug'] === $normalizedPath) {
                return $node;
            }

            if (isset($node['children'])) {
                $found = $this->findNode($node['children'], $normalizedPath);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Get the ancestry path to a specific node.
     *
     * @param  array  $hierarchy  The hierarchy to search
     * @param  string  $path  The path to find ancestry for
     * @return array Array of ancestor nodes
     */
    public function getAncestry(array $hierarchy, string $path): array
    {
        $normalizedPath = Path::toSlug($path);
        $ancestry = [];

        $this->findAncestors($hierarchy, $normalizedPath, $ancestry);

        return $ancestry;
    }

    /**
     * Recursively find ancestors of a node.
     *
     * @param  array  $nodes  Current level nodes
     * @param  string  $targetPath  Path to find
     * @param  array  &$ancestry  Array to store ancestors
     * @return bool Whether the target was found
     */
    protected function findAncestors(array $nodes, string $targetPath, array &$ancestry): bool
    {
        foreach ($nodes as $node) {
            if ($node['slug'] === $targetPath) {
                return true;
            }

            if (isset($node['children'])) {
                $ancestry[] = $node;
                if ($this->findAncestors($node['children'], $targetPath, $ancestry)) {
                    return true;
                }
                array_pop($ancestry);
            }
        }

        return false;
    }

    /**
     * Flatten a hierarchy back into an array of paths.
     *
     * @param  array  $hierarchy  The hierarchy to flatten
     * @return array Array of paths
     */
    public function flattenHierarchy(array $hierarchy): array
    {
        $paths = [];

        foreach ($hierarchy as $node) {
            $paths[] = $node['slug'];

            if (isset($node['children'])) {
                $paths = array_merge($paths, $this->flattenHierarchy($node['children']));
            }
        }

        return $paths;
    }
}
