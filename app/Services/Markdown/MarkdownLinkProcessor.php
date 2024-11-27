<?php

namespace App\Services\Markdown;

use App\Support\Path;
use Symfony\Component\Filesystem\Path as SymfonyPath;

class MarkdownLinkProcessor
{
    /**
     * Process all markdown links in content, converting internal links to relative URLs.
     */
    public function processLinks(string $content, string $currentPath): string
    {
        return preg_replace_callback(
            '/\[([^\]]+)\]\(((?:\/|\.\.?\/)?[^)]+\.md[#?]?[^)]*)\)/',
            function ($matches) use ($currentPath) {
                $linkText = $matches[1];
                $linkPath = $matches[2];

                if ($this->isExternalLink($linkPath)) {
                    return $matches[0];
                }

                return "[{$linkText}](" . $this->resolveInternalLink($linkPath, $currentPath) . ")";
            },
            $content
        );
    }

    /**
     * Resolve an internal markdown link to a relative URL.
     */
    protected function resolveInternalLink(string $linkPath, string $currentPath): string
    {
        // Split off any anchors or query parameters
        $parts = preg_split('/([#?])/', $linkPath, 2, PREG_SPLIT_DELIM_CAPTURE);
        $path = $parts[0];
        $suffix = isset($parts[1]) ? $parts[1] . $parts[2] : '';

        // Use SymfonyPath to get base directory
        $baseDir = SymfonyPath::getDirectory($currentPath);

        // Use SymfonyPath to resolve target path
        $absolutePath = str_starts_with($path, '/')
            ? ltrim($path, '/')
            : SymfonyPath::join($baseDir, $path);

        // Convert both paths to slugs - this handles all index.md conversions
        $sourceSlug = Path::toSlug($currentPath);
        $targetSlug = Path::toSlug($absolutePath);

        // Get relative path using Symfony Path
        $relativePath = SymfonyPath::makeRelative($targetSlug, SymfonyPath::getDirectory($sourceSlug));

        // Only special case is empty path, which means current directory
        if ($relativePath === '') {
            $relativePath = '.';
        }

        return $relativePath . $suffix;
    }

    /**
     * Check if a link is external.
     */
    protected function isExternalLink(string $path): bool
    {
        return (bool) preg_match('/^(https?|ftp|mailto):|^\/\//', $path);
    }
}
