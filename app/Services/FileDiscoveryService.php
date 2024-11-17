<?php

namespace App\Services;

use FilesystemIterator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class FileDiscoveryService
{
    /**
     * Find all Markdown files in a directory, sorted by directory depth.
     *
     * @param string $path Base directory path
     * @return Collection<string, SplFileInfo> Collection of files keyed by their relative paths
     */
    public function findFiles(string $path): Collection
    {
        if (!File::isDirectory($path)) {
            throw new \InvalidArgumentException("Path does not exist or is not a directory: {$path}");
        }

        $directory = new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS |
            FilesystemIterator::FOLLOW_SYMLINKS
        );

        $iterator = new RecursiveIteratorIterator(
            $directory,
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Find all .md files
        $files = new RegexIterator($iterator, '/\.md$/i');

        // Convert to array and sort by directory depth
        $sortedFiles = collect();
        foreach ($files as $file) {
            $relativePath = $this->getRelativePath($path, $file->getPathname());
            $sortedFiles[$relativePath] = $file;
        }

        // Sort files so that:
        // 1. Shallower paths come before deeper paths
        // 2. Regular .md files come before index.md files at the same level
        // 3. Alphabetical sorting within the same depth
        return $sortedFiles->sortBy(function ($file, $relativePath) {
            $depth = substr_count($relativePath, DIRECTORY_SEPARATOR);
            $isIndex = basename($relativePath) === 'index.md';

            // Use depth as primary sort key, then isIndex, then path
            return sprintf('%08d-%d-%s', $depth, $isIndex ? 1 : 0, $relativePath);
        });
    }

    /**
     * Get the relative path from the base directory to a file.
     */
    protected function getRelativePath(string $basePath, string $fullPath): string
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return substr($fullPath, strlen($basePath));
    }

    /**
     * Generate a slug from a file path.
     *
     * @param string $relativePath Relative path to the markdown file
     * @return string The generated slug
     */
    public function generateSlug(string $relativePath): string
    {
        // Remove .md extension
        $slug = preg_replace('/\.md$/', '', $relativePath);

        // Convert Windows path separators to Unix style
        $slug = str_replace('\\', '/', $slug);

        // Remove leading/trailing slashes
        return trim($slug, '/');
    }

    /**
     * Check if a given file is an index file.
     */
    public function isIndexFile(string $relativePath): bool
    {
        return basename($relativePath) === 'index.md';
    }

    /**
     * Get the parent slug for a given path.
     */
    public function getParentSlug(string $relativePath): ?string
    {
        $slug = $this->generateSlug($relativePath);

        if ($slug === 'index') {
            return null;
        }

        $parentSlug = dirname($slug);
        return $parentSlug === '.' ? null : $parentSlug;
    }

    /**
     * Get all ancestor slugs for a given path.
     *
     * @return array<string>
     */
    public function getAncestorSlugs(string $relativePath): array
    {
        $slug = $this->generateSlug($relativePath, fn() => false);

        if (empty($slug)) {
            return [];
        }

        $parts = explode('/', $slug);
        array_pop($parts); // Remove the current slug part

        $ancestors = [];
        $current = '';

        foreach ($parts as $part) {
            $current = $current ? "{$current}/{$part}" : $part;
            $ancestors[] = $current;
        }

        return $ancestors;
    }

    /**
     * Get file metadata including modification time and size.
     *
     * @return array{mtime: int, size: int}
     */
    public function getFileMetadata(SplFileInfo $file): array
    {
        return [
            'mtime' => $file->getMTime(),
            'size' => $file->getSize(),
        ];
    }
}
