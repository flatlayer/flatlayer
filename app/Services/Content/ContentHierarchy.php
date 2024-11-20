<?php

namespace App\Services\Content;

use App\Models\Entry;
use App\Support\Path;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ContentHierarchy
{
    /**
     * Convert a flat collection of entries into a hierarchical structure.
     *
     * @param  string  $type  The content type to build hierarchy for
     * @param  string|null  $root  Optional root path to start from
     * @param  array  $options  Additional options for hierarchy generation
     *                          - depth: Maximum depth to traverse (null for unlimited)
     *                          - fields: Array of fields to include in node data
     *                          - sort: Sort nodes by field/direction, e.g. ['title' => 'asc']
     * @return array Hierarchical structure of entries
     *
     * @throws \InvalidArgumentException If type is invalid or entries not found
     */
    public function buildHierarchy(string $type, ?string $root = null, array $options = []): array
    {
        $query = Entry::where('type', $type);

        if ($root !== null) {
            $root = Path::toSlug($root);
            $query->where(function ($q) use ($root) {
                $q->where('slug', $root)
                    ->orWhere('slug', 'like', $root.'/%');
            });
        }

        $entries = $query->get();

        // If filtering by root and no entries found, return empty array
        if ($root !== null && $entries->isEmpty()) {
            return [];
        }

        // Only throw if no entries found for the type at all
        if ($root === null && $entries->isEmpty()) {
            throw new \InvalidArgumentException("No entries found for type: {$type}");
        }

        $grouped = $this->groupByParent($entries, $root);
        $rootEntries = $grouped->get('', collect());

        return $this->buildNodes($rootEntries, $grouped, $options);
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
        if ($root === null) {
            return $entries->groupBy(function ($entry) {
                return Path::parent($entry->slug);
            });
        }

        return $entries->groupBy(function ($entry) use ($root) {
            if ($entry->slug === $root) {
                return '';
            }

            return $entry->slug === $root ? '' : Path::parent($entry->slug);
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
        $maxDepth = $options['depth'] ?? null;
        if ($maxDepth !== null && $depth >= $maxDepth) {
            return [];
        }

        $nodes = $entries->map(function ($entry) use ($grouped, $options, $depth) {
            $node = $this->createNode($entry, $options);

            $children = $grouped->get($entry->slug, collect());
            if ($children->isNotEmpty()) {
                if (isset($options['sort'])) {
                    foreach ($options['sort'] as $field => $direction) {
                        $children = $children->sortBy($field, SORT_REGULAR, $direction === 'desc');
                    }
                }

                $node['children'] = $this->buildNodes($children, $grouped, $options, $depth + 1);
            }

            return $node;
        });

        if (isset($options['sort'])) {
            foreach ($options['sort'] as $field => $direction) {
                $nodes = $nodes->sortBy($field, SORT_REGULAR, $direction === 'desc');
            }
        }

        return $nodes->values()->all();
    }

    /**
     * Create a node from an entry.
     *
     * @param  Entry  $entry  The entry to create node from
     * @param  array  $options  Node creation options
     * @return array Node data
     */
    protected function createNode(Entry $entry, array $options): array
    {
        $fields = $options['fields'] ?? ['id', 'title', 'slug', 'meta'];

        $node = [];
        foreach ($fields as $field) {
            if (str_starts_with($field, 'meta.')) {
                $metaKey = substr($field, 5);
                $node['meta'][$metaKey] = data_get($entry->meta, $metaKey);
            } else {
                $node[$field] = $entry->$field;
            }
        }

        return $node;
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
