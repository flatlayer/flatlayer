<?php

namespace App\Services;

use App\Traits\GeneratesContentSlugs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;

class FileDiscoveryService
{
    use GeneratesContentSlugs;

    /**
     * Find all Markdown files in a directory, sorted by depth.
     * When a slug conflict occurs between a single file and an index file
     * (e.g., /foo.md vs /foo/index.md), the single file takes precedence.
     *
     * @param string $path Base directory path
     * @return Collection<string, SplFileInfo> Collection of files keyed by their relative paths
     * @throws \InvalidArgumentException If path doesn't exist or isn't a directory
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

        // Track slugs we've seen to detect conflicts
        $seenSlugs = [];
        $sortedFiles = collect();

        foreach ($files as $file) {
            $relativePath = $this->getRelativePath($path, $file->getPathname());
            $slug = $this->generateSlug($relativePath);

            // If we've already seen this slug, we have a conflict
            if (isset($seenSlugs[$slug])) {
                // Log the conflict - you might want to handle this differently
                Log::warning("Slug conflict detected: {$slug}. Files: {$seenSlugs[$slug]}, {$relativePath}");
                continue;
            }

            $seenSlugs[$slug] = $relativePath;
            $sortedFiles[$relativePath] = $file;
        }

        // Sort files by directory depth and then alphabetically
        return $sortedFiles->sortBy(function ($file, $relativePath) {
            $depth = substr_count($relativePath, DIRECTORY_SEPARATOR);
            return sprintf('%08d-%s', $depth, $relativePath);
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
     * Get the parent slug for a given path.
     */
    public function getParentSlug(string $relativePath): ?string
    {
        $slug = $this->generateSlug($relativePath);

        if ($slug === '') {
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
        $slug = $this->generateSlug($relativePath);

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
