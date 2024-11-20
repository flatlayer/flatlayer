<?php

namespace App\Services\Media;

use App\Services\Storage\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * Handles media file access and path resolution within content repositories.
 *
 * This service provides:
 * - Media file access from configured content repositories
 * - Path resolution and normalization
 * - Basic file metadata retrieval
 * - Integration with StorageResolver for disk configuration
 */
class MediaStorage
{
    private Filesystem $disk;

    /**
     * Create a new MediaStorage service instance.
     *
     * @param  StorageResolver  $resolver  Service for resolving storage disks
     * @param  string  $type  Content type for resolving disk configuration
     * @param  Filesystem|string|null  $disk  Optional specific disk to use
     *
     * @throws \InvalidArgumentException If disk cannot be resolved
     */
    public function __construct(
        protected readonly StorageResolver $resolver,
        string $type,
        Filesystem|string|null $disk = null,
    ) {
        $this->disk = $this->resolver->resolve($disk, $type);
    }

    /**
     * Get the underlying filesystem disk.
     */
    public function getDisk(): Filesystem
    {
        return $this->disk;
    }

    /**
     * Use a different disk for subsequent operations.
     *
     * @param  Filesystem|string  $disk  The disk to use
     * @param  string  $type  Optional content type for disk resolution
     *
     * @throws \InvalidArgumentException If disk cannot be resolved
     */
    public function useDisk(Filesystem|string $disk, string $type): self
    {
        $this->disk = $this->resolver->resolve($disk, $type ?? 'default');

        return $this;
    }

    /**
     * Get the contents of a media file.
     *
     * @throws RuntimeException If the file cannot be read
     */
    public function get(string $path): string
    {
        $path = $this->normalizePath($path);

        if (! $this->exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        try {
            return $this->disk->get($path);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to read file {$path}: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if a media file exists.
     */
    public function exists(string $path): bool
    {
        return $this->disk->exists($this->normalizePath($path));
    }

    /**
     * Get a media file's size in bytes.
     *
     * @throws RuntimeException If the file cannot be found
     */
    public function size(string $path): int
    {
        $path = $this->normalizePath($path);

        if (! $this->exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        return $this->disk->size($path);
    }

    /**
     * Get a media file's mime type.
     *
     * @throws RuntimeException If the file cannot be found
     */
    public function mimeType(string $path): string
    {
        $path = $this->normalizePath($path);

        if (! $this->exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        return $this->disk->mimeType($path);
    }

    /**
     * Get a media file's last modified timestamp.
     *
     * @throws RuntimeException If the file cannot be found
     */
    public function lastModified(string $path): int
    {
        $path = $this->normalizePath($path);

        if (! $this->exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        return $this->disk->lastModified($path);
    }

    /**
     * Resolve a media path relative to a content file.
     *
     * @param  string  $mediaPath  The media file path (can be relative)
     * @param  string  $contentPath  The content file path for relative resolution
     * @return string The resolved absolute path
     *
     * @throws RuntimeException If the media file cannot be found
     */
    public function resolveRelativePath(string $mediaPath, string $contentPath): string
    {
        // Normalize paths
        $mediaPath = trim($mediaPath, '/');
        $contentPath = trim($contentPath, '/');

        // Handle absolute paths (no ../ or ./)
        if (! str_starts_with($mediaPath, './') && ! str_starts_with($mediaPath, '../')) {
            if ($this->exists($mediaPath)) {
                return $mediaPath;
            }
        }

        // Handle relative paths
        $contentDir = dirname($contentPath);

        // Special handling for parent directory references
        while (str_starts_with($mediaPath, '../')) {
            $mediaPath = substr($mediaPath, 3);
            $contentDir = dirname($contentDir);
        }

        // Remove any ./ references
        $mediaPath = str_replace('./', '', $mediaPath);

        $resolvedPath = $contentDir === '.' ? $mediaPath : "{$contentDir}/{$mediaPath}";
        $normalizedPath = $this->normalizePath($resolvedPath);

        if (! $this->exists($normalizedPath)) {
            throw new RuntimeException(sprintf(
                'Media file not found: %s (relative to %s)',
                $mediaPath,
                $contentPath
            ));
        }

        return $normalizedPath;
    }

    /**
     * Normalize a file path.
     */
    protected function normalizePath(string $path): string
    {
        // Convert backslashes to forward slashes and collapse multiple slashes
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);

        // Security: Block path traversal attempts
        if (preg_match('#(?:^|/)\.\.(?:/|$)|^\.\.?/?$#', $path)) {
            throw new RuntimeException('Path traversal not allowed');
        }

        // Remove leading/trailing slashes
        return trim($path, '/');
    }
}
