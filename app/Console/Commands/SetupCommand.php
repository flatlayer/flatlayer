<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupCommand extends Command
{
    protected $signature = 'flatlayer:setup
        {--force : Force setup even if already configured}
        {--env= : Path to .env file}';

    protected $description = 'Interactive setup wizard for Flatlayer CMS';

    protected string $envPath;

    protected array $currentEnv = [];

    protected array $exampleEnv = [];

    // Keys that should be treated as sensitive
    protected array $sensitiveKeys = [
        'OPENAI_API_KEY',
        'GITHUB_WEBHOOK_SECRET',
        'FLATLAYER_GIT_TOKEN',
    ];

    public function handle()
    {
        $this->envPath = $this->option('env') ?? base_path('.env');

        // Load current and example environment files
        $this->loadEnvironmentFiles();

        $this->info('🚀 Welcome to Flatlayer CMS Setup!');
        $this->line('This wizard will help you configure Flatlayer CMS.');

        if (File::exists($this->envPath) && ! $this->option('force')) {
            if (! $this->confirm('⚠️  Configuration already exists. Continue setup?', false)) {
                return 1;
            }
        }

        // Create .env if it doesn't exist
        if (! File::exists($this->envPath)) {
            File::copy(base_path('.env.example'), $this->envPath);
            $this->loadEnvironmentFiles();
        }

        $this->configureSearch();
        $this->configureImages();
        $this->configureGit();
        $this->configureWebhooks();
        $this->configureRepositories();
        $this->runFinalSteps();

        return 0;
    }

    protected function loadEnvironmentFiles(): void
    {
        if (File::exists($this->envPath)) {
            $this->currentEnv = $this->parseEnvFile($this->envPath);
        }

        $examplePath = base_path('.env.example');
        if (File::exists($examplePath)) {
            $this->exampleEnv = $this->parseEnvFile($examplePath);
        }
    }

    protected function parseEnvFile(string $path): array
    {
        $contents = file_get_contents($path);
        $env = [];

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = trim(trim($value), '"\'');
            }
        }

        return $env;
    }

    protected function configureSearch(): void
    {
        $this->info('🔍 AI Search Configuration');
        $this->line('------------------------');

        $env = [];

        if ($this->confirm('Would you like to enable AI-powered search?', true)) {
            $env['OPENAI_API_KEY'] = $this->secretWithDefault(
                'OpenAI API Key?',
                'OPENAI_API_KEY'
            );

            if ($this->confirm('Do you have an OpenAI Organization ID?', false)) {
                $env['OPENAI_ORGANIZATION'] = $this->askWithDefault(
                    'OpenAI Organization ID?',
                    'OPENAI_ORGANIZATION'
                );
            }

            $env['OPENAI_SEARCH_EMBEDDING_MODEL'] = $this->choice(
                'Which OpenAI embedding model would you like to use?',
                [
                    'text-embedding-3-small',
                    'text-embedding-3-large',
                ],
                0
            );
        }

        $this->updateEnv($env);
    }

    protected function configureImages(): void
    {
        $this->info('🖼️  Image Processing Configuration');
        $this->line('----------------------------');

        $env = [];

        // Image signing
        $env['FLATLAYER_MEDIA_USE_SIGNATURES'] = $this->confirm(
            'Would you like to enable signed image URLs?',
            $this->currentEnv['APP_ENV'] === 'production'
        );

        // Image dimensions
        if ($this->confirm('Would you like to configure maximum image dimensions?', false)) {
            $env['FLATLAYER_MEDIA_MAX_WIDTH'] = $this->askWithDefault(
                'Maximum image width?',
                'FLATLAYER_MEDIA_MAX_WIDTH',
                '8192'
            );
            $env['FLATLAYER_MEDIA_MAX_HEIGHT'] = $this->askWithDefault(
                'Maximum image height?',
                'FLATLAYER_MEDIA_MAX_HEIGHT',
                '8192'
            );
        }

        $this->updateEnv($env);
    }

    protected function configureGit(): void
    {
        $this->info('🔄 Git Configuration');
        $this->line('------------------');

        $env = [];

        $authMethod = $this->choice(
            'Which Git authentication method would you like to use?',
            ['token', 'ssh'],
            0
        );

        $env['FLATLAYER_GIT_AUTH_METHOD'] = $authMethod;

        if ($authMethod === 'token') {
            $env['FLATLAYER_GIT_USERNAME'] = $this->askWithDefault(
                'Git username?',
                'FLATLAYER_GIT_USERNAME'
            );
            $env['FLATLAYER_GIT_TOKEN'] = $this->secretWithDefault(
                'Git access token?',
                'FLATLAYER_GIT_TOKEN'
            );
        } else {
            $env['FLATLAYER_GIT_SSH_KEY_PATH'] = $this->askWithDefault(
                'Path to SSH private key?',
                'FLATLAYER_GIT_SSH_KEY_PATH',
                '~/.ssh/id_rsa'
            );
        }

        $env['FLATLAYER_GIT_TIMEOUT'] = $this->askWithDefault(
            'Git operation timeout (seconds)?',
            'FLATLAYER_GIT_TIMEOUT',
            '60'
        );

        $env['FLATLAYER_GIT_COMMIT_NAME'] = $this->askWithDefault(
            'Git commit author name?',
            'FLATLAYER_GIT_COMMIT_NAME',
            'Flatlayer CMS'
        );

        $env['FLATLAYER_GIT_COMMIT_EMAIL'] = $this->askWithDefault(
            'Git commit author email?',
            'FLATLAYER_GIT_COMMIT_EMAIL',
            'cms@flatlayer.io'
        );

        $this->updateEnv($env);
    }

    protected function configureWebhooks(): void
    {
        $this->info('🔗 Webhook Configuration');
        $this->line('---------------------');

        $env = [];

        if ($this->confirm('Would you like to configure GitHub webhook integration?', true)) {
            $env['GITHUB_WEBHOOK_SECRET'] = $this->secretWithDefault(
                'GitHub Webhook Secret (press enter to generate one)?',
                'GITHUB_WEBHOOK_SECRET'
            ) ?? $this->generateWebhookSecret();
        }

        $this->updateEnv($env);
    }

    protected function configureRepositories(): void
    {
        $this->info('📚 Content Repositories');
        $this->line('-------------------');

        $env = [];
        $sources = [];

        while ($this->confirm('Would you like to add a content repository?', true)) {
            $type = $this->askContentType();

            $driver = $this->choice(
                "What storage driver should be used for {$type}?",
                ['local', 's3'],
                0
            );

            if ($driver === 'local') {
                $path = $this->ask("What is the local path for {$type}?");
            } else {
                $env["CONTENT_REPOSITORY_{$type}_KEY"] = $this->askWithDefault(
                    'AWS Access Key ID?',
                    "CONTENT_REPOSITORY_{$type}_KEY"
                );
                $env["CONTENT_REPOSITORY_{$type}_SECRET"] = $this->secretWithDefault(
                    'AWS Secret Access Key?',
                    "CONTENT_REPOSITORY_{$type}_SECRET"
                );
                $env["CONTENT_REPOSITORY_{$type}_REGION"] = $this->askWithDefault(
                    'AWS Region?',
                    "CONTENT_REPOSITORY_{$type}_REGION",
                    'us-east-1'
                );
                $env["CONTENT_REPOSITORY_{$type}_BUCKET"] = $this->askWithDefault(
                    'S3 Bucket?',
                    "CONTENT_REPOSITORY_{$type}_BUCKET"
                );
                $path = $this->ask("What is the path within the bucket for {$type}?", '/');
            }

            $sources[$type] = [
                'driver' => $driver,
                'path' => $path,
            ];

            if (isset($env['GITHUB_WEBHOOK_SECRET']) &&
                $this->confirm("Enable webhook updates for {$type}?", true)
            ) {
                $webhookUrl = $this->getWebhookUrl($type);
                $env["CONTENT_REPOSITORY_{$type}_WEBHOOK_URL"] = $webhookUrl;
                $env["CONTENT_REPOSITORY_{$type}_PULL"] = true;

                $this->info("Webhook URL: {$webhookUrl}");
                $this->line("Add this URL to your GitHub repository's webhook settings");
                $this->line('Content type: application/json');
                $this->line('Secret: '.($env['GITHUB_WEBHOOK_SECRET'] ?? '[configured secret]'));
            }
        }

        // Add repository configurations
        foreach ($sources as $type => $config) {
            $env["CONTENT_REPOSITORY_{$type}_DRIVER"] = $config['driver'];
            $env["CONTENT_REPOSITORY_{$type}_PATH"] = $config['path'];
        }

        $this->updateEnv($env);
    }

    protected function askContentType(): string
    {
        $commonTypes = ['docs', 'posts', 'pages'];
        $type = $this->choice(
            'What type of content is this?',
            [...$commonTypes, 'custom'],
            0
        );

        if ($type === 'custom') {
            return $this->ask('Enter the custom content type');
        }

        return $type;
    }

    protected function runFinalSteps(): void
    {
        $this->info('✅ Setup completed!');
        $this->showNextSteps();
    }

    protected function showNextSteps(): void
    {
        $this->info('📋 Next Steps:');

        $steps = [
            '1. Configure your database connection in .env',
            '2. Run database migrations:',
            '   php artisan migrate',
            '',
            '3. Set up your content repositories:',
            '   For each repository, run:',
            '   php artisan flatlayer:sync --type=<type> --pull',
            '',
            '4. Configure your web server',
        ];

        if (! empty($this->currentEnv['GITHUB_WEBHOOK_SECRET'])) {
            $steps[] = '';
            $steps[] = '5. Set up GitHub webhooks using the URLs provided above';
            $steps[] = '   Content type: application/json';
            $steps[] = '   Secret: '.$this->currentEnv['GITHUB_WEBHOOK_SECRET'];
        }

        foreach ($steps as $step) {
            $this->line($step);
        }

        $this->line('');
        $this->line('For more information, visit: https://docs.flatlayer.io');
    }

    protected function askWithDefault(string $question, string $key, ?string $default = null): string
    {
        $currentValue = $this->currentEnv[$key] ?? null;
        $exampleValue = $this->exampleEnv[$key] ?? $default;
        $displayValue = $currentValue ?? $exampleValue;

        if (in_array($key, $this->sensitiveKeys) && $currentValue) {
            $displayValue = str_repeat('*', strlen($currentValue));
        }

        return $this->ask($question, $displayValue);
    }

    protected function secretWithDefault(string $question, string $key): ?string
    {
        $currentValue = $this->currentEnv[$key] ?? null;

        if ($currentValue) {
            $maskedValue = str_repeat('*', strlen($currentValue));
            $this->line("Current value: $maskedValue");

            if (! $this->confirm('Would you like to change it?', false)) {
                return $currentValue;
            }
        }

        return $this->secret($question);
    }

    protected function getWebhookUrl(string $type): string
    {
        $appUrl = rtrim($this->currentEnv['APP_URL'] ?? 'http://localhost', '/');

        return "{$appUrl}/webhook/{$type}";
    }

    protected function generateWebhookSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    protected function updateEnv(array $values): void
    {
        $content = File::exists($this->envPath) ? File::get($this->envPath) : '';

        foreach ($values as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $value = str_replace('"', '\"', $value);

            if (strpos($value, ' ') !== false) {
                $value = '"'.$value.'"';
            }

            $key = strtoupper($key);

            if (strpos($content, $key.'=') !== false) {
                $content = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$value}",
                    $content
                );
            } else {
                $content .= PHP_EOL."{$key}={$value}";
            }
        }

        File::put($this->envPath, $content);
        $this->loadEnvironmentFiles();
    }
}
