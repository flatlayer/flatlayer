<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class RepositoryDiskManager
{
    /**
     * Collection of configured repository disks
     *
     * @var array<string, array>
     */
    protected array $repositories = [];

    /**
     * Create a new disk for a repository
     *
     * @param string $type The content type (e.g., 'posts', 'docs')
     * @param string $path The local path to the repository
     * @param array $config Additional disk configuration
     * @return Filesystem The configured filesystem disk
     * @throws InvalidArgumentException If path is invalid
     */
    public function createDiskForRepository(string $type, string $path, array $config = []): Filesystem
    {
        $realPath = realpath($path);

        if (!$realPath || !is_dir($realPath)) {
            throw new InvalidArgumentException("Invalid repository path: {$path}");
        }

        // Generate a unique disk name for this repository
        $diskName = "repo_{$type}_" . md5($realPath);

        // Store repository configuration
        $this->repositories[$type] = [
            'disk' => $diskName,
            'path' => $realPath,
            'config' => $config,
        ];

        // Create and configure the disk
        $diskConfig = array_merge([
            'driver' => 'local',
            'root' => $realPath,
            'throw' => true, // Enable exception throwing
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
        ], $config);

        // Register the disk configuration
        config(["filesystems.disks.{$diskName}" => $diskConfig]);

        return Storage::build($diskConfig);
    }

    /**
     * Get a repository disk by content type
     *
     * @param string $type The content type
     * @return Filesystem The filesystem disk
     * @throws InvalidArgumentException If repository type not found
     */
    public function getDisk(string $type): Filesystem
    {
        if (!isset($this->repositories[$type])) {
            throw new InvalidArgumentException("No repository configured for type: {$type}");
        }

        return Storage::disk($this->repositories[$type]['disk']);
    }

    /**
     * Get repository configuration
     *
     * @param string $type The content type
     * @return array{disk: string, path: string, config: array} Repository configuration
     * @throws InvalidArgumentException If repository type not found
     */
    public function getConfig(string $type): array
    {
        if (!isset($this->repositories[$type])) {
            throw new InvalidArgumentException("No repository configured for type: {$type}");
        }

        return $this->repositories[$type];
    }

    /**
     * Check if a repository is configured
     *
     * @param string $type The content type
     * @return bool Whether the repository is configured
     */
    public function hasRepository(string $type): bool
    {
        return isset($this->repositories[$type]);
    }

    /**
     * Get all configured repositories
     *
     * @return array<string, array> Array of repository configurations
     */
    public function getRepositories(): array
    {
        return $this->repositories;
    }
}
