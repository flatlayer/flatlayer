<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Manages synchronization configurations for different content types.
 *
 * This service is responsible for loading, parsing, and managing configurations
 * for content synchronization. It reads configurations from environment variables,
 * parses them into a structured format, and provides methods to access and
 * manipulate these configurations.
 *
 * Configurations are stored in the format:
 * FLATLAYER_SYNC_{TYPE}_{SETTING} = "value"
 *
 * Where:
 * {TYPE} is the content type (e.g., POSTS, PAGES, etc.)
 * {SETTING} is one of PATH, PATTERN, WEBHOOK, or PULL
 *
 * Example:
 * FLATLAYER_SYNC_POSTS_PATH="/path/to/posts"
 * FLATLAYER_SYNC_POSTS_PATTERN="*.md"
 * FLATLAYER_SYNC_POSTS_WEBHOOK="http://deploy.com/webhook"
 * FLATLAYER_SYNC_POSTS_PULL=true
 */
class SyncConfigurationService
{
    private const CONFIG_PREFIX = 'FLATLAYER_SYNC_';
    private const VALID_SETTINGS = ['PATH', 'PATTERN', 'WEBHOOK', 'PULL'];
    private const DEFAULT_PATTERN = '**/*.md';

    /**
     * @var array<string, array> Stored configurations
     */
    protected array $configs = [];

    public function __construct()
    {
        $this->loadConfigsFromEnv();
    }

    /**
     * Load configurations from environment variables.
     */
    protected function loadConfigsFromEnv(): void
    {
        $envVars = array_filter($_ENV, fn($key) => str_starts_with($key, self::CONFIG_PREFIX), ARRAY_FILTER_USE_KEY);

        foreach ($envVars as $key => $value) {
            $parts = explode('_', Str::after($key, self::CONFIG_PREFIX));
            $type = Str::lower($parts[0]);
            $setting = $parts[1] ?? null;

            if (in_array($setting, self::VALID_SETTINGS)) {
                $this->configs[$type][$setting] = $this->parseValue($setting, $value);
            }
        }

        // Set default pattern if not specified
        foreach ($this->configs as &$config) {
            $config['PATTERN'] = $config['PATTERN'] ?? self::DEFAULT_PATTERN;
        }
    }

    /**
     * Parse the value based on the setting type.
     *
     * @param string $setting
     * @param string $value
     * @return string|bool
     */
    protected function parseValue(string $setting, string $value): string|bool
    {
        return match ($setting) {
            'PULL' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }

    /**
     * Get the configuration for a specific type.
     *
     * @param string $type The configuration type
     * @return array The configuration array, or an empty array if not found
     */
    public function getConfig(string $type): array
    {
        return $this->configs[Str::lower($type)] ?? [];
    }

    /**
     * Get the configuration for a specific type as command-line arguments.
     *
     * @param string $type The configuration type
     * @return array The configuration array as command-line arguments
     */
    public function getConfigAsArgs(string $type): array
    {
        $config = $this->getConfig($type);
        $args = [];

        foreach ($config as $key => $value) {
            $argKey = '--' . strtolower($key);
            if (is_bool($value)) {
                if ($value) {
                    $args[$argKey] = true;
                }
            } else {
                $args[$argKey] = $value;
            }
        }

        return $args;
    }

    /**
     * Set the configuration for a specific type.
     *
     * @param string $type The configuration type
     * @param array $config The configuration array
     */
    public function setConfig(string $type, array $config): void
    {
        $this->configs[Str::lower($type)] = array_merge(
            $this->configs[Str::lower($type)] ?? [],
            array_change_key_case($config, CASE_UPPER)
        );
    }

    /**
     * Check if a configuration exists for a specific type.
     *
     * @param string $type The configuration type
     * @return bool True if the configuration exists, false otherwise
     */
    public function hasConfig(string $type): bool
    {
        return isset($this->configs[Str::lower($type)]);
    }

    /**
     * Get a specific setting for a configuration type.
     *
     * @param string $type The configuration type
     * @param string $setting The setting name (PATH, PATTERN, WEBHOOK, or PULL)
     * @return string|bool|null The setting value, or null if not found
     */
    public function getSetting(string $type, string $setting): string|bool|null
    {
        return Arr::get($this->configs, [Str::lower($type), Str::upper($setting)]);
    }

    /**
     * Set a specific setting for a configuration type.
     *
     * @param string $type The configuration type
     * @param string $setting The setting name (PATH, PATTERN, WEBHOOK, or PULL)
     * @param string|bool $value The setting value
     */
    public function setSetting(string $type, string $setting, string|bool $value): void
    {
        $type = Str::lower($type);
        $setting = Str::upper($setting);

        if (!isset($this->configs[$type])) {
            $this->configs[$type] = [];
        }

        $this->configs[$type][$setting] = $value;
    }

    /**
     * Get all stored configurations.
     *
     * @return array<string, array> All configurations
     */
    public function getAllConfigs(): array
    {
        return $this->configs;
    }
}
