<?php

namespace App\Services;

use App\Support\Path;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FileDiscoveryService
{
    /**
     * Find all Markdown files in a disk, sorted by depth.
     * When a slug conflict occurs between a single file and an index file
     * (e.g., /foo.md vs /foo/index.md), the single file takes precedence.
     *
     * @param  Filesystem  $disk  The filesystem disk to search
     * @return Collection<string, array> Collection of files with metadata, keyed by relative paths
     */
    public function findFiles(Filesystem $disk): Collection
    {
        // Get all markdown files and sort them naturally
        $files = collect($disk->allFiles())
            ->filter(fn ($path) => pathinfo($path, PATHINFO_EXTENSION) === 'md')
            ->sort(fn ($a, $b) => $this->compareFilePaths($a, $b));

        // Track slugs we've seen to detect conflicts
        $seenSlugs = [];
        $sortedFiles = collect();

        foreach ($files as $path) {
            $relativePath = $this->normalizePath($path);
            $slug = Path::toSlug($relativePath);

            // If we've already seen this slug, we have a conflict
            if (isset($seenSlugs[$slug])) {
                // Log the conflict
                Log::warning("Slug conflict detected: {$slug}. Files: {$seenSlugs[$slug]}, {$relativePath}");

                continue;
            }

            $seenSlugs[$slug] = $relativePath;
            $sortedFiles[$relativePath] = [
                'path' => $relativePath,
                'metadata' => $this->getFileMetadata($disk, $relativePath),
            ];
        }

        return $sortedFiles;
    }

    /**
     * Normalize a path to use forward slashes and no leading/trailing slashes.
     */
    protected function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Compare two file paths for sorting.
     * - index.md files come first in their directory
     * - Files in a directory come before subdirectories
     * - Natural sort is used for remaining comparisons
     */
    protected function compareFilePaths(string $a, string $b): int
    {
        $partsA = explode('/', $a);
        $partsB = explode('/', $b);

        $depthA = count($partsA);
        $depthB = count($partsB);

        // Compare directory by directory
        for ($i = 0; $i < min($depthA, $depthB); $i++) {
            // If we're looking at the last part (filename)
            if ($i === $depthA - 1 || $i === $depthB - 1) {
                // If we're in the same directory, index.md comes first
                if (dirname($a) === dirname($b)) {
                    if (basename($a) === 'index.md') {
                        return -1;
                    }
                    if (basename($b) === 'index.md') {
                        return 1;
                    }

                    // Otherwise, natural sort the filenames
                    return strnatcmp(basename($a), basename($b));
                }
            }

            // If parts are different at this level
            if ($partsA[$i] !== $partsB[$i]) {
                // If one path still has more parts (is a subdirectory)
                // and we're comparing against a file in the current directory
                if ($i === $depthB - 1 && $depthA > $depthB) {
                    return 1;
                }  // Subdirectories come after
                if ($i === $depthA - 1 && $depthB > $depthA) {
                    return -1;
                } // Files come first

                // Otherwise, natural sort the directory/file names
                return strnatcmp($partsA[$i], $partsB[$i]);
            }
        }

        // If we get here, one path is a subset of the other
        // Shorter paths (files) come before longer paths (subdirectories)
        return $depthA <=> $depthB;
    }

    /**
     * Get the parent slug for a given path.
     */
    public function getParentSlug(string $relativePath): ?string
    {
        $slug = Path::toSlug($relativePath);

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
        $slug = Path::toSlug($relativePath);

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
     * Get file metadata from the disk.
     *
     * @return array{size: int, mtime: int, mimetype: string|null}
     */
    protected function getFileMetadata(Filesystem $disk, string $path): array
    {
        return [
            'size' => $disk->size($path),
            'mtime' => $disk->lastModified($path),
            'mimetype' => $disk->mimeType($path),
        ];
    }

    /**
     * Get the full contents of a file from the disk.
     */
    public function getFileContents(Filesystem $disk, string $path): string
    {
        return $disk->get($this->normalizePath($path));
    }

    /**
     * Check if a file exists on the disk.
     */
    public function fileExists(Filesystem $disk, string $path): bool
    {
        return $disk->exists($this->normalizePath($path));
    }
}
