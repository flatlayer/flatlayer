<?php

namespace App\Services\Markdown;

class MarkdownLinkProcessor
{
    /**
     * Process internal Markdown links to use slugs instead of file paths.
     */
    public function processLinks(string $content): string
    {
        return preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+\.md[#?]?[^)]*)\)/',  // Match .md files with optional anchors/query params
            function ($matches) {
                $linkText = $matches[1];
                $linkPath = $matches[2];

                // Skip external links
                if ($this->isExternalLink($linkPath)) {
                    return $matches[0];
                }

                // Process internal link
                return "[{$linkText}](" . $this->processInternalLink($linkPath) . ")";
            },
            $content
        );
    }

    /**
     * Process an internal link path.
     */
    protected function processInternalLink(string $linkPath): string
    {
        // Split off any anchors or query parameters
        $parts = preg_split('/([#?])/', $linkPath, 2, PREG_SPLIT_DELIM_CAPTURE);
        $path = $parts[0];
        $suffix = isset($parts[1]) ? $parts[1] . $parts[2] : '';

        // Only remove the final .md extension, preserving any other extensions
        $path = preg_replace('/\.md$/', '', $path);

        // Normalize the path
        $path = $this->normalizePath($path);

        // Handle special cases for index files
        if (basename($path) === 'index') {
            $dirname = dirname($path);
            $path = $dirname === '.' ? '.' : $dirname;
        }

        return $path . $suffix;
    }

    /**
     * Check if a link is external.
     */
    protected function isExternalLink(string $path): bool
    {
        return (bool) preg_match('/^(https?|ftp):\/\//', $path);
    }

    /**
     * Normalize a path by resolving './', '../', and multiple slashes.
     */
    protected function normalizePath(string $path): string
    {
        // Remove leading ./
        $path = preg_replace('/^\.\//', '', $path);

        // For paths starting with ../, preserve the prefix
        if (str_starts_with($path, '../')) {
            return $this->normalizeParentPath($path);
        }

        return $this->normalizeSegments(explode('/', $path));
    }

    /**
     * Normalize a path that starts with parent directory references.
     */
    protected function normalizeParentPath(string $path): string
    {
        // Extract the ../ prefix
        preg_match('/^(\.\.\/)+/', $path, $matches);
        $prefix = $matches[0];
        $remainingPath = substr($path, strlen($prefix));

        // Normalize the remaining path segments
        $normalizedPath = $this->normalizeSegments(explode('/', $remainingPath));

        return $prefix . $normalizedPath;
    }

    /**
     * Normalize path segments by resolving '.' and '..' references.
     */
    protected function normalizeSegments(array $segments): string
    {
        $result = [];

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($result);
                continue;
            }
            $result[] = $segment;
        }

        return implode('/', $result);
    }
}
