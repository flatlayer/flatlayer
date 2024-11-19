<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ContentRepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerContentRepositoryDisks();
    }

    /**
     * Register filesystem disks for content repositories based on environment variables.
     */
    protected function registerContentRepositoryDisks(): void
    {
        $repositories = $this->discoverRepositories();

        foreach ($repositories as $type => $config) {
            // Register the disk directly with the repository type as the name
            Config::set("filesystems.disks.{$type}", [
                'driver' => $config['driver'] ?? 'local',
                'root' => $config['path'],
                'throw' => true,
                'visibility' => 'private',
                // Add S3 configuration if specified
                'key' => $config['key'] ?? null,
                'secret' => $config['secret'] ?? null,
                'region' => $config['region'] ?? null,
                'bucket' => $config['bucket'] ?? null,
                'url' => $config['url'] ?? null,
                'endpoint' => $config['endpoint'] ?? null,
                'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            ]);

            // Add to flatlayer config
            Config::set("flatlayer.repositories.{$type}", [
                'disk' => $type,
                'webhook_url' => $config['webhook_url'] ?? null,
                'pull' => $config['pull'] ?? false,
            ]);
        }
    }

    /**
     * Discover content repositories from environment variables.
     *
     * Environment variables should be in the format:
     * CONTENT_REPOSITORY_{TYPE}_PATH=/path/to/content
     * CONTENT_REPOSITORY_{TYPE}_DRIVER=local|s3
     * CONTENT_REPOSITORY_{TYPE}_WEBHOOK_URL=https://example.com/webhook
     *
     * For S3:
     * CONTENT_REPOSITORY_{TYPE}_KEY=aws-key
     * CONTENT_REPOSITORY_{TYPE}_SECRET=aws-secret
     * CONTENT_REPOSITORY_{TYPE}_REGION=aws-region
     * CONTENT_REPOSITORY_{TYPE}_BUCKET=bucket-name
     *
     * @return array<string, array>
     */
    protected function discoverRepositories(): array
    {
        $repositories = [];
        $prefix = 'CONTENT_REPOSITORY_';

        foreach ($_ENV as $key => $value) {
            if (! str_starts_with($key, $prefix) || ! str_ends_with($key, '_PATH')) {
                continue;
            }

            // Extract type from the environment variable name
            // e.g., CONTENT_REPOSITORY_DOCS_PATH -> docs
            $type = Str::lower(
                Str::substr($key, strlen($prefix), -strlen('_PATH'))
            );

            // Build repository config
            $repositories[$type] = $this->buildRepositoryConfig($type);
        }

        return $repositories;
    }

    /**
     * Build repository configuration from environment variables.
     */
    protected function buildRepositoryConfig(string $type): array
    {
        $prefix = "CONTENT_REPOSITORY_{$type}_";
        $config = [];

        // Required configuration
        $config['path'] = $_ENV["{$prefix}PATH"];

        // Optional configuration with defaults
        $config['driver'] = $_ENV["{$prefix}DRIVER"] ?? 'local';
        $config['webhook_url'] = $_ENV["{$prefix}WEBHOOK_URL"] ?? null;
        $config['pull'] = isset($_ENV["{$prefix}PULL"]) ?
            filter_var($_ENV["{$prefix}PULL"], FILTER_VALIDATE_BOOLEAN) :
            false;

        // S3-specific configuration
        if ($config['driver'] === 's3') {
            $config['key'] = $_ENV["{$prefix}KEY"] ?? null;
            $config['secret'] = $_ENV["{$prefix}SECRET"] ?? null;
            $config['region'] = $_ENV["{$prefix}REGION"] ?? null;
            $config['bucket'] = $_ENV["{$prefix}BUCKET"] ?? null;
            $config['url'] = $_ENV["{$prefix}URL"] ?? null;
            $config['endpoint'] = $_ENV["{$prefix}ENDPOINT"] ?? null;
            $config['use_path_style_endpoint'] = isset($_ENV["{$prefix}USE_PATH_STYLE_ENDPOINT"]) ?
                filter_var($_ENV["{$prefix}USE_PATH_STYLE_ENDPOINT"], FILTER_VALIDATE_BOOLEAN) :
                false;
        }

        return array_filter($config, fn ($value) => ! is_null($value));
    }
}
