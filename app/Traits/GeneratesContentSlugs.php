<?php

namespace App\Traits;

trait GeneratesContentSlugs
{
    /**
     * Generate a slug from a file path.
     * This is the central place for all slug generation logic.
     */
    public static function generateSlug(string $path): string
    {
        // Normalize path separators
        $path = str_replace('\\', '/', $path);

        // Remove leading and trailing slashes first
        $path = trim($path, '/');

        // Remove multiple consecutive slashes
        $path = preg_replace('#/+#', '/', $path);

        // Remove .md extension anywhere in the path
        $path = preg_replace('/\.md(?:\/|$)/', '', $path);

        // If path ends with /index, remove it
        $path = preg_replace('#/index$#', '', $path);

        // Special case: if path is just 'index', return empty string
        if ($path === 'index') {
            return '';
        }

        // Handle other special characters and path normalization
        // First replace invalid characters with dashes
        $path = preg_replace('/[^a-zA-Z0-9_\/-]/', '-', $path);

        // Then collapse multiple dashes into one
        $path = preg_replace('/-+/', '-', $path);

        return $path;
    }

    /**
     * Check if a path represents an index file.
     */
    public static function isIndexPath(string $path): bool
    {
        return str_ends_with($path, '/index.md') || $path === 'index.md';
    }
}
