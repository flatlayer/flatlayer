<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;

class SyncConfigurationService
{
    protected $configs = [];

    public function __construct()
    {
        $this->loadConfigsFromEnv();
    }

    protected function loadConfigsFromEnv()
    {
        foreach ($_ENV as $key => $value) {
            if (Str::startsWith($key, 'FLATLAYER_SYNC_')) {
                $type = Str::kebab(Str::lower(Str::after($key, 'FLATLAYER_SYNC_')));
                $this->configs[$type] = $this->parseConfig($value);
            }
        }
    }

    public function getConfig(string $type): ?array
    {
        return $this->configs[$type] ?? null;
    }

    public function setConfig(string $type, string $config)
    {
        $this->configs[$type] = $this->parseConfig($config);
    }

    public function hasConfig(string $type): bool
    {
        return isset($this->configs[$type]);
    }

    protected function parseConfig(string $config): array
    {
        $definition = new InputDefinition([
            new InputArgument('path', InputArgument::REQUIRED),
            new InputOption('pattern', null, InputOption::VALUE_OPTIONAL, '', '**/*.md'),
        ]);

        $input = new StringInput($config);
        $input->bind($definition);

        $args = [
            'path' => $input->getArgument('path'),
        ];

        if ($input->getOption('pattern') !== '**/*.md') {
            $args['--pattern'] = $input->getOption('pattern');
        }

        return $args;
    }

    public function getAllConfigs(): array
    {
        return $this->configs;
    }
}
