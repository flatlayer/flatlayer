<?php

namespace App\Support;

class Path
{
    /**
     * Generate a slug from a file path.
     * This is the central place for all slug generation logic.
     *
     * @param  string  $path  The path to slugify
     * @param  bool  $preserveExtension  Whether to preserve the .md extension
     */
    public static function toSlug(string $path, bool $preserveExtension = false): string
    {
        // Empty path handling
        if (empty($path) || $path === '/' || $path === '.') {
            return '';
        }

        // URL decode the path first to handle encoded traversal attempts
        $path = urldecode($path);

        // Normalize path separators
        $path = str_replace('\\', '/', $path);

        // Remove multiple consecutive slashes
        $path = preg_replace('#/+#', '/', $path);

        // Security: Block path traversal attempts - now checked after normalization
        if (preg_match('#(?:^|/)\.\.(?:/|$)|^\.\.?/?$#', $path)) {
            return '';
        }

        // Remove leading and trailing slashes
        $path = trim($path, '/');

        if (! $preserveExtension) {
            // Remove .md extension anywhere in the path
            $path = preg_replace('/\.md(?:\/|$)/', '', $path);
        }

        // If path ends with /index, remove it
        $path = preg_replace('#/index$#', '', $path);

        // Special case: if path is just 'index', return empty string
        if ($path === 'index') {
            return '';
        }

        // Transliterate Unicode characters
        $path = transliterator_transliterate('Any-Latin; Latin-ASCII', $path);

        // Handle segments individually
        $segments = explode('/', $path);
        $segments = array_map(function ($segment) {
            // Handle dot directory paths
            if ($segment === '.' || empty($segment)) {
                return '';
            }

            // For path segments starting with dots (e.g., "../" or "./"), preserve them
            if (str_starts_with($segment, '.')) {
                return $segment;
            }

            // Replace invalid characters with dashes
            $segment = preg_replace('/[^a-zA-Z0-9_-]/', '-', $segment);

            // Collapse multiple dashes
            $segment = preg_replace('/-+/', '-', $segment);

            // Remove leading/trailing dashes
            return trim($segment, '-');
        }, $segments);

        // Filter out empty segments and join
        $segments = array_filter($segments, function ($segment) {
            return $segment !== '';
        });

        return implode('/', $segments);
    }

    /**
     * Check if a path represents an index file.
     */
    public static function isIndex(string $path): bool
    {
        return str_ends_with($path, '/index.md') || $path === 'index.md';
    }

    /**
     * Get the parent path.
     */
    public static function parent(string $path): string
    {
        $path = static::toSlug($path);
        if (empty($path)) {
            return '';
        }

        $parent = dirname($path);

        return $parent === '.' ? '' : $parent;
    }

    /**
     * Get all ancestor paths.
     *
     * @return array<string>
     */
    public static function ancestors(string $path): array
    {
        $path = static::toSlug($path);
        if (empty($path)) {
            return [];
        }

        $parts = explode('/', $path);
        array_pop($parts); // Remove current segment

        $ancestors = [];
        $currentPath = '';
        foreach ($parts as $segment) {
            if (! empty($segment)) {
                $currentPath = $currentPath ? "{$currentPath}/{$segment}" : $segment;
                $ancestors[] = $currentPath;
            }
        }

        return $ancestors;
    }

    /**
     * Get all siblings paths given a base directory.
     *
     * @return array<string>
     */
    public static function siblings(string $path, array $allPaths): array
    {
        // Convert backslashes to forward slashes and trim
        $normalizedPath = str_replace('\\', '/', trim($path, '/'));

        // Get the directory part
        $dir = dirname($normalizedPath);
        $dir = $dir === '.' ? '' : $dir;

        // If this is an index file, we want siblings in its directory
        if (basename($normalizedPath) === 'index.md') {
            $prefix = $dir === '' ? '' : $dir.'/';
        } else {
            // For non-index files, get their parent directory
            $prefix = $dir === '' ? '' : $dir.'/';
        }

        return array_values(array_filter($allPaths, function ($siblingPath) use ($normalizedPath, $prefix) {
            // Normalize sibling path
            $siblingPath = str_replace('\\', '/', trim($siblingPath, '/'));

            // Must be in same directory
            if (! str_starts_with($siblingPath, $prefix)) {
                return false;
            }

            // Must not be the current path
            if ($siblingPath === $normalizedPath) {
                return false;
            }

            // Must not be in a subdirectory
            $remaining = substr($siblingPath, strlen($prefix));

            return ! str_contains($remaining, '/');
        }));
    }

    /**
     * Get all children paths.
     *
     * @return array<string>
     */
    public static function children(string $path, array $allPaths): array
    {
        $path = static::toSlug($path);
        $prefix = $path === '' ? '' : $path.'/';

        return array_values(array_filter($allPaths, function ($childPath) use ($path, $prefix) {
            // Normalize path separators but keep extensions
            $childPath = str_replace('\\', '/', trim($childPath, '/'));

            // Must be in the subdirectory
            if (! str_starts_with($childPath, $prefix)) {
                return false;
            }

            // Must not be the current path
            if ($childPath === str_replace('\\', '/', trim($path, '/'))) {
                return false;
            }

            // Must be direct child (no further slashes after prefix)
            $remaining = substr($childPath, strlen($prefix));

            return ! str_contains($remaining, '/');
        }));
    }
}
