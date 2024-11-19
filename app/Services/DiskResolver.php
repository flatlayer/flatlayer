<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DiskResolver
{
    /**
     * Resolve a disk from various input types.
     *
     * @param  string|array|Filesystem|null  $disk  The disk specification:
     *                                              - string: Name of an existing disk
     *                                              - array: Configuration for Storage::build()
     *                                              - Filesystem: Used directly
     *                                              - null: Use repository configuration
     * @param  string  $type  Content type (used when disk is null)
     *
     * @throws InvalidArgumentException If disk cannot be resolved
     */
    public function resolve(string|array|Filesystem|null $disk, string $type): Filesystem
    {
        return match (true) {
            // If already a Filesystem instance, return it directly
            $disk instanceof Filesystem => $disk,

            // If string, treat as disk name
            is_string($disk) => $this->resolveFromString($disk),

            // If array, treat as build configuration
            is_array($disk) => $this->resolveFromArray($disk),

            // If null, get from repository configuration
            $disk === null => $this->resolveFromType($type),

            // Should never reach this due to type hint, but good practice
            default => throw new InvalidArgumentException('Invalid disk specification'),
        };
    }

    /**
     * Resolve a disk from a string name.
     */
    protected function resolveFromString(string $name): Filesystem
    {
        $fullName = $name;
        if (! str_contains($name, '.')) {
            $fullName = "content.{$name}";
        }

        if (! Config::has("filesystems.disks.{$fullName}")) {
            if (! Config::has("filesystems.disks.{$name}")) {
                throw new InvalidArgumentException("Disk '{$name}' is not configured");
            }
            $fullName = $name;
        }

        return Storage::disk($fullName);
    }

    /**
     * Resolve a disk from a configuration array.
     */
    protected function resolveFromArray(array $config): Filesystem
    {
        if (! isset($config['driver'])) {
            throw new InvalidArgumentException("Disk configuration must include 'driver'");
        }

        return Storage::build($config);
    }

    /**
     * Resolve a disk from the repository type.
     */
    protected function resolveFromType(string $type): Filesystem
    {
        if (! Config::has("flatlayer.repositories.{$type}")) {
            throw new InvalidArgumentException("No repository configured for type: {$type}");
        }

        $diskName = Config::get("flatlayer.repositories.{$type}.disk");

        return Storage::disk($diskName);
    }
}
