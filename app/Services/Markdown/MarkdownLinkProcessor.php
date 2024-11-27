<?php

namespace App\Services\Markdown;

use App\Support\Path;
use Symfony\Component\Filesystem\Path as SymfonyPath;

/**
 * Process internal Markdown links to convert file paths to slugs.
 */
class MarkdownLinkProcessor
{
    /**
     * Process internal Markdown links to use slugs instead of file paths.
     *
     * @param string $content The markdown content
     * @param string $currentPath The current file path
     * @return string The processed content
     */
    public function processLinks(string $content, string $currentPath): string
    {
        $isIndex = basename($currentPath) === 'index.md';
        $currentDir = dirname($currentPath);

        return preg_replace_callback(
            '/\[([^\]]+)\]\(((?:\/|\.\.?\/)?[^)]+\.md[#?]?[^)]*)\)/',
            function ($matches) use ($isIndex, $currentDir) {
                $linkText = $matches[1];
                $linkPath = $matches[2];

                // Skip external links
                if ($this->isExternalLink($linkPath)) {
                    return $matches[0];
                }

                // Process internal link
                return "[{$linkText}](" . $this->processInternalLink($linkPath, $isIndex, $currentDir) . ")";
            },
            $content
        );
    }

    /**
     * Process an internal link path.
     *
     * @param string $linkPath The path to process
     * @param bool $isIndex Whether the current file is an index
     * @param string $currentDir The current directory
     * @return string The processed path
     */
    protected function processInternalLink(string $linkPath, bool $isIndex, string $currentDir): string
    {
        // Split off any anchors or query parameters
        $parts = preg_split('/([#?])/', $linkPath, 2, PREG_SPLIT_DELIM_CAPTURE);
        $path = $parts[0];
        $suffix = isset($parts[1]) ? $parts[1] . $parts[2] : '';

        // Remove .md extension
        $path = preg_replace('/\.md$/', '', $path);

        // Handle index files
        if (basename($path) === 'index') {
            $path = dirname($path);
            if ($path === '.') {
                $path = '';
            }
        }

        // If path is relative, resolve it using Symfony's Path
        if (str_starts_with($path, './') || str_starts_with($path, '../')) {
            // Make relative to current directory
            $fullPath = SymfonyPath::join($currentDir, $path);
            // Get path relative to current directory
            $path = SymfonyPath::makeRelative($fullPath, $currentDir);
        }

        // If it's empty at this point (was just 'index'), return '.'
        if (empty($path)) {
            return '.';
        }

        // Convert to slug only if it's not a relative path indicator
        if (!in_array($path, ['.', '..']) && !str_starts_with($path, '../')) {
            $path = Path::toSlug($path);

            // For index files, add ./ to any local paths that don't already have a relative indicator
            if ($isIndex && !str_starts_with($path, '.')) {
                $path = './' . $path;
            }
        }

        return $path . $suffix;
    }

    /**
     * Check if a link is external.
     *
     * @param string $path The path to check
     * @return bool Whether the path is an external link
     */
    protected function isExternalLink(string $path): bool
    {
        return (bool) preg_match('/^(https?|ftp|mailto):|^\//', $path);
    }
}
