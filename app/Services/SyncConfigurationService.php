<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;

/**
 * Manages synchronization configurations for different content types.
 *
 * This service is responsible for loading, parsing, and managing configurations
 * for content synchronization. It reads configurations from environment variables,
 * parses them into a structured format, and provides methods to access and
 * manipulate these configurations.
 *
 * Configurations are stored in the format:
 * FLATLAYER_SYNC_{TYPE} = "path/to/content --pattern=*.md"
 *
 * Where {TYPE} is the content type (e.g., POST, PAGE, etc.), and the value
 * contains the path and optional pattern for file matching.
 */
class SyncConfigurationService
{
    /**
     * @var array<string, array> Stored configurations
     */
    protected array $configs = [];

    private const CONFIG_PREFIX = 'FLATLAYER_SYNC_';
    private const DEFAULT_PATTERN = '**/*.md';

    public function __construct()
    {
        $this->loadConfigsFromEnv();
    }

    /**
     * Load configurations from environment variables.
     */
    protected function loadConfigsFromEnv(): void
    {
        $this->configs = collect($_ENV)
            ->filter(fn($_, $key) => str_starts_with($key, self::CONFIG_PREFIX))
            ->map(fn($value, $key) => [
                'type' => Str::kebab(Str::lower(Str::after($key, self::CONFIG_PREFIX))),
                'config' => $this->parseConfig($value)
            ])
            ->pluck('config', 'type')
            ->all();
    }

    /**
     * Get the configuration for a specific type.
     *
     * @param string $type The configuration type
     * @return array|null The configuration array, or null if not found
     */
    public function getConfig(string $type): ?array
    {
        return $this->configs[$type] ?? null;
    }

    /**
     * Set the configuration for a specific type.
     *
     * @param string $type The configuration type
     * @param string $config The configuration string
     */
    public function setConfig(string $type, string $config): void
    {
        $this->configs[$type] = $this->parseConfig($config);
    }

    /**
     * Check if a configuration exists for a specific type.
     *
     * @param string $type The configuration type
     * @return bool True if the configuration exists, false otherwise
     */
    public function hasConfig(string $type): bool
    {
        return isset($this->configs[$type]);
    }

    /**
     * Parse a configuration string into an array.
     *
     * @param string $config The configuration string
     * @return array The parsed configuration
     */
    protected function parseConfig(string $config): array
    {
        $definition = new InputDefinition([
            new InputArgument('path', InputArgument::REQUIRED),
            new InputOption('pattern', null, InputOption::VALUE_OPTIONAL, '', self::DEFAULT_PATTERN),
        ]);

        $input = new StringInput($config);
        $input->bind($definition);

        $args = ['path' => $input->getArgument('path')];

        $pattern = $input->getOption('pattern');
        if ($pattern !== self::DEFAULT_PATTERN) {
            $args['--pattern'] = $pattern;
        }

        return $args;
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
