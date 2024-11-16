<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SetupCommand extends Command
{
    protected $signature = 'flatlayer:setup
        {--force : Force setup even if already configured}
        {--quick : Skip optional configurations}
        {--env= : Path to .env file}';

    protected $description = 'Interactive setup wizard for Flatlayer CMS';

    protected array $progress = [];
    protected string $envPath;
    protected array $currentEnv = [];
    protected array $exampleEnv = [];

    // Define keys that should be treated as sensitive
    protected array $sensitiveKeys = [
        'DB_PASSWORD',
        'REDIS_PASSWORD',
        'MAIL_PASSWORD',
        'AWS_SECRET_ACCESS_KEY',
        'OPENAI_API_KEY',
        'GITHUB_WEBHOOK_SECRET',
        'POSTMARK_TOKEN',
        'SES_SECRET',
        'PUSHER_APP_SECRET',
        'VITE_PUSHER_APP_SECRET',
    ];

    public function handle()
    {
        $this->envPath = $this->option('env') ?? base_path('.env');

        // Load current and example environment files
        $this->loadEnvironmentFiles();

        $this->info('🚀 Welcome to Flatlayer CMS Setup!');
        $this->line('This wizard will help you configure your CMS.');

        if (File::exists($this->envPath) && !$this->option('force')) {
            if (!$this->confirm('⚠️  Configuration already exists. Continue setup?', false)) {
                return 1;
            }
        }

        // Create .env if it doesn't exist
        if (!File::exists($this->envPath)) {
            File::copy(base_path('.env.example'), $this->envPath);
            $this->loadEnvironmentFiles(); // Reload after copying
        }

        $this->configureEssentials();

        if (!$this->option('quick')) {
            $this->configureOptionals();
        }

        $this->configureContent();
        $this->runFinalSteps();
        $this->showNextSteps();

        return 0;
    }

    protected function loadEnvironmentFiles(): void
    {
        // Load .env.example
        $examplePath = base_path('.env.example');
        if (File::exists($examplePath)) {
            $this->exampleEnv = $this->parseEnvFile($examplePath);
        }

        // Load current .env
        if (File::exists($this->envPath)) {
            $this->currentEnv = $this->parseEnvFile($this->envPath);
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
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                $value = trim($value, '"\'');

                if (!str_starts_with($key, 'FLATLAYER_SYNC_')) {
                    $env[$key] = $value;
                }
            }
        }

        return $env;
    }

    protected function askWithDefault(string $question, string $key, $default = null): string
    {
        // Get current value if it exists
        $currentValue = $this->currentEnv[$key] ?? null;

        // Get example value if no current value
        $exampleValue = $this->exampleEnv[$key] ?? $default;

        // Determine display value for prompt
        $displayValue = $currentValue ?? $exampleValue;

        // For sensitive values, mask the current value if it exists
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

            if (!$this->confirm('Would you like to change it?', false)) {
                return $currentValue;
            }
        }

        return $this->secret($question);
    }

    protected function configureEssentials()
    {
        $this->info('📝 Essential Configuration');
        $this->line('------------------------');

        $env = [];

        // Application Configuration
        $env['APP_NAME'] = $this->askWithDefault('What is your application name?', 'APP_NAME');
        $env['APP_URL'] = $this->askWithDefault('What is your application URL?', 'APP_URL');

        // Environment
        $env['APP_ENV'] = $this->choice(
            'Which environment is this?',
            ['production', 'staging', 'local'],
            array_search($this->currentEnv['APP_ENV'] ?? 'local', ['production', 'staging', 'local']) ?? 2
        );

        $env['APP_DEBUG'] = $env['APP_ENV'] !== 'production'
            ? $this->confirm('Enable debug mode?', true)
            : false;

        // Generate app key if not exists
        if (!env('APP_KEY')) {
            $this->call('key:generate', ['--force' => true]);
        }

        // Timezone Configuration
        $env['APP_TIMEZONE'] = $this->askWithDefault(
            'What timezone should the application use?',
            'APP_TIMEZONE',
            'UTC'
        );

        // Database Configuration
        $this->info('🗄️  Database Configuration');

        if ($this->confirm('Are you using Laravel Forge?', true)) {
            $this->line('✨ Great! Database will be configured by Forge automatically.');
        } else {
            $dbConnection = $this->choice(
                'Which database would you like to use?',
                ['PostgreSQL', 'SQLite'],
                array_search($this->currentEnv['DB_CONNECTION'] ?? 'pgsql', ['pgsql', 'sqlite']) ?? 0
            );

            if ($dbConnection === 'PostgreSQL') {
                $env['DB_CONNECTION'] = 'pgsql';
                $env['DB_HOST'] = $this->askWithDefault('Database host?', 'DB_HOST');
                $env['DB_PORT'] = $this->askWithDefault('Database port?', 'DB_PORT');
                $env['DB_DATABASE'] = $this->askWithDefault('Database name?', 'DB_DATABASE');
                $env['DB_USERNAME'] = $this->askWithDefault('Database username?', 'DB_USERNAME');
                $env['DB_PASSWORD'] = $this->secretWithDefault('Database password?', 'DB_PASSWORD');
            } else {
                $env['DB_CONNECTION'] = 'sqlite';
                $this->createSqliteDatabase();
            }
        }

        // Cache and Session Configuration
        $this->info('📦 Cache & Session Configuration');

        $env['CACHE_DRIVER'] = $this->choice(
            'Which cache driver would you like to use?',
            ['database', 'file', 'redis'],
            array_search($this->currentEnv['CACHE_DRIVER'] ?? 'database', ['database', 'file', 'redis']) ?? 0
        );

        $env['SESSION_DRIVER'] = $this->choice(
            'Which session driver would you like to use?',
            ['database', 'file', 'redis'],
            array_search($this->currentEnv['SESSION_DRIVER'] ?? 'database', ['database', 'file', 'redis']) ?? 0
        );

        // Queue Configuration
        $this->info('⚡ Queue Configuration');

        $env['QUEUE_CONNECTION'] = $this->choice(
            'Which queue connection would you like to use?',
            ['database', 'redis', 'sync'],
            array_search($this->currentEnv['QUEUE_CONNECTION'] ?? 'database', ['database', 'redis', 'sync']) ?? 0
        );

        // Search Configuration
        $this->info('🔍 Search Configuration');
        if ($this->confirm('Would you like to enable AI-powered search?', true)) {
            $env['OPENAI_API_KEY'] = $this->secretWithDefault('OpenAI API Key?', 'OPENAI_API_KEY');
            if ($this->confirm('Do you have an OpenAI Organization ID?', false)) {
                $env['OPENAI_ORGANIZATION'] = $this->askWithDefault('OpenAI Organization ID?', 'OPENAI_ORGANIZATION');
            }
            $env['OPENAI_SEARCH_EMBEDDING_MODEL'] = $this->askWithDefault(
                'Which OpenAI embedding model would you like to use?',
                'OPENAI_SEARCH_EMBEDDING_MODEL',
                'text-embedding-3-small'
            );
        }

        $this->updateEnv($env);
        $this->progress['essentials'] = true;
    }

    protected function configureOptionals()
    {
        // Redis Configuration (if selected for any service)
        if (in_array('redis', [$this->currentEnv['CACHE_DRIVER'], $this->currentEnv['SESSION_DRIVER'], $this->currentEnv['QUEUE_CONNECTION']])) {
            $this->configureRedis();
        }

        // Mail Configuration
        if ($this->confirm('📧 Would you like to configure email?', false)) {
            $this->configureMail();
        }

        // Image Configuration
        $this->configureImages();

        // Error Tracking
        if ($this->confirm('🐛 Would you like to configure error tracking?', false)) {
            $this->configureErrorTracking();
        }
    }

    protected function configureRedis()
    {
        $this->info('📊 Redis Configuration');

        $env = [];
        $env['REDIS_HOST'] = $this->askWithDefault('Redis Host?', 'REDIS_HOST');
        $env['REDIS_PASSWORD'] = $this->secretWithDefault('Redis Password?', 'REDIS_PASSWORD');
        $env['REDIS_PORT'] = $this->askWithDefault('Redis Port?', 'REDIS_PORT');

        $this->updateEnv($env);
    }

    protected function configureMail()
    {
        $env = [];
        $provider = $this->choice(
            'Which email provider would you like to use?',
            ['SMTP', 'Mailgun', 'Postmark', 'SES', 'None'],
            array_search($this->currentEnv['MAIL_MAILER'] ?? 'smtp', ['smtp', 'mailgun', 'postmark', 'ses', 'log']) ?? 0
        );

        switch ($provider) {
            case 'SMTP':
                $env['MAIL_MAILER'] = 'smtp';
                $env['MAIL_HOST'] = $this->askWithDefault('SMTP Host?', 'MAIL_HOST');
                $env['MAIL_PORT'] = $this->askWithDefault('SMTP Port?', 'MAIL_PORT');
                $env['MAIL_USERNAME'] = $this->askWithDefault('SMTP Username?', 'MAIL_USERNAME');
                $env['MAIL_PASSWORD'] = $this->secretWithDefault('SMTP Password?', 'MAIL_PASSWORD');
                $env['MAIL_ENCRYPTION'] = $this->askWithDefault('SMTP Encryption?', 'MAIL_ENCRYPTION');
                break;

            case 'Mailgun':
                $env['MAIL_MAILER'] = 'mailgun';
                $env['MAILGUN_DOMAIN'] = $this->askWithDefault('Mailgun Domain?', 'MAILGUN_DOMAIN');
                $env['MAILGUN_SECRET'] = $this->secretWithDefault('Mailgun Secret?', 'MAILGUN_SECRET');
                break;

            case 'Postmark':
                $env['MAIL_MAILER'] = 'postmark';
                $env['POSTMARK_TOKEN'] = $this->secretWithDefault('Postmark Token?', 'POSTMARK_TOKEN');
                break;

            case 'SES':
                $env['MAIL_MAILER'] = 'ses';
                $env['AWS_ACCESS_KEY_ID'] = $this->askWithDefault('AWS Access Key ID?', 'AWS_ACCESS_KEY_ID');
                $env['AWS_SECRET_ACCESS_KEY'] = $this->secretWithDefault('AWS Secret Access Key?', 'AWS_SECRET_ACCESS_KEY');
                $env['AWS_DEFAULT_REGION'] = $this->askWithDefault('AWS Region?', 'AWS_DEFAULT_REGION');
                break;

            default:
                $env['MAIL_MAILER'] = 'log';
        }

        if ($provider !== 'None') {
            $env['MAIL_FROM_ADDRESS'] = $this->askWithDefault('From Email Address?', 'MAIL_FROM_ADDRESS');
            $env['MAIL_FROM_NAME'] = $this->askWithDefault('From Name?', 'MAIL_FROM_NAME');
        }

        $this->updateEnv($env);
    }

    protected function configureImages()
    {
        $this->info('🖼️  Image Configuration');

        $env = [];

        // Configure image signing
        if ($this->confirm('Would you like to enable signed image URLs?', $this->currentEnv['APP_ENV'] === 'production')) {
            $env['FLATLAYER_MEDIA_USE_SIGNATURES'] = true;
        }

        // Configure max dimensions
        if ($this->confirm('Would you like to configure maximum image dimensions?', false)) {
            $env['FLATLAYER_MEDIA_MAX_WIDTH'] = $this->askWithDefault('Maximum image width?', 'FLATLAYER_MEDIA_MAX_WIDTH', '8192');
            $env['FLATLAYER_MEDIA_MAX_HEIGHT'] = $this->askWithDefault('Maximum image height?', 'FLATLAYER_MEDIA_MAX_HEIGHT', '8192');
        }

        $this->updateEnv($env);
    }

    protected function configureErrorTracking()
    {
        $this->info('Error Tracking Configuration');

        $env = [];
        $provider = $this->choice(
            'Which error tracking service would you like to use?',
            ['Sentry', 'Flare', 'None'],
            0
        );

        switch ($provider) {
            case 'Sentry':
                $env['SENTRY_LARAVEL_DSN'] = $this->secretWithDefault('Sentry DSN?', 'SENTRY_LARAVEL_DSN');
                $env['SENTRY_TRACES_SAMPLE_RATE'] = $this->askWithDefault('Sentry Sample Rate (0-1)?', 'SENTRY_TRACES_SAMPLE_RATE', '0.1');
                break;

            case 'Flare':
                $env['FLARE_KEY'] = $this->secretWithDefault('Flare API Key?', 'FLARE_KEY');
                break;
        }

        $this->updateEnv($env);
    }

    protected function configureContent()
    {
        $this->info('📚 Content Configuration');
        $this->line('----------------------');

        $env = [];

        // Configure GitHub webhook secret
        if ($this->confirm('Would you like to configure GitHub webhook integration?', true)) {
            $env['GITHUB_WEBHOOK_SECRET'] = $this->secretWithDefault(
                'GitHub Webhook Secret (or press enter to generate one)?',
                'GITHUB_WEBHOOK_SECRET'
            ) ?? $this->generateWebhookSecret();
        }

        // Configure content sources
        $sources = [];
        while ($this->confirm('Would you like to add a content source?', true)) {
            $type = $this->askContentType();
            $path = $this->ask("What is the local path for {$type}?");

            // Suggest pattern based on type
            $defaultPattern = match($type) {
                'docs' => '**/*.md',  // Recursive for documentation
                'posts' => '*.md',    // Flat structure for blog posts
                'pages' => '*.md',    // Flat structure for pages
                default => '*.md'
            };

            $pattern = $this->ask(
                'What file pattern should be used?',
                $defaultPattern
            );

            $webhook = $this->confirm('Enable webhook updates for this source?', true);

            $sources[$type] = [
                'path' => $path,
                'pattern' => $pattern,
                'webhook' => $webhook,
            ];

            if ($webhook && isset($env['GITHUB_WEBHOOK_SECRET'])) {
                $this->info("Webhook URL will be: {$this->getWebhookUrl($type)}");
                $this->line("Add this URL to your GitHub repository's webhook settings");
                $this->line("Set the webhook secret to the value configured above");
                $this->line("Set the content type to 'application/json'");
            }
        }

        foreach ($sources as $type => $config) {
            $prefix = "FLATLAYER_SYNC_".strtoupper($type)."_";
            $env[$prefix.'PATH'] = $config['path'];
            $env[$prefix.'PATTERN'] = $config['pattern'];
            if ($config['webhook']) {
                $env[$prefix.'WEBHOOK'] = $this->getWebhookUrl($type);
                $env[$prefix.'PULL'] = true;
            }
        }

        $this->updateEnv($env);
        $this->progress['content'] = true;
    }

    protected function askContentType(): string
    {
        $commonTypes = ['docs', 'posts', 'pages'];
        $type = $this->choice(
            'What type of content is this?',
            array_merge($commonTypes, ['custom']),
            0
        );

        if ($type === 'custom') {
            return $this->ask('Enter the custom content type');
        }

        return $type;
    }

    protected function runFinalSteps()
    {
        $this->info('🔧 Running final setup steps...');

        // Run migrations if database is configured
        if ($this->confirm('Would you like to run database migrations?', true)) {
            $this->call('migrate');
        }

        // Clear cache
        $this->call('config:clear');
        $this->call('cache:clear');

        // Create storage link
        if (!file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        $this->info('✅ Setup completed successfully!');
    }

    protected function showNextSteps()
    {
        $this->info('📋 Next Steps:');

        $steps = [];

        // Content repositories
        if (isset($this->progress['content'])) {
            $steps[] = '1. Set up your content repositories:';
            $steps[] = '   For each content type, run:';
            $steps[] = '   php artisan flatlayer:sync --type=<type> --pull';
        }

        // Web server
        $steps[] = (empty($steps) ? '1' : '2').'. Configure your web server';

        // Webhooks
        if (!empty($this->currentEnv['GITHUB_WEBHOOK_SECRET'])) {
            $steps[] = (empty($steps) ? '1' : '3').'. Set up GitHub webhooks using the URLs provided above';
            $steps[] = '   Content type: application/json';
            $steps[] = '   Secret: '.$this->currentEnv['GITHUB_WEBHOOK_SECRET'];
        }

        // Queue worker (if not using sync)
        if (($this->currentEnv['QUEUE_CONNECTION'] ?? 'sync') !== 'sync') {
            $steps[] = '4. Start the queue worker:';
            $steps[] = '   php artisan queue:work';
        }

        // Output steps
        foreach ($steps as $step) {
            $this->line($step);
        }

        $this->line('');
        $this->line('Visit the documentation at: https://docs.flatlayer.io');

        // Show reminder for production environment
        if (($this->currentEnv['APP_ENV'] ?? 'local') === 'production') {
            $this->line('');
            $this->info('🚨 Production Environment Checklist:');
            $this->line('- Ensure APP_DEBUG is set to false');
            $this->line('- Configure error tracking');
            $this->line('- Set up SSL/TLS certificates');
            $this->line('- Configure backup strategy');
            $this->line('- Set up monitoring');
        }
    }

    protected function createSqliteDatabase()
    {
        $path = database_path('database.sqlite');
        if (!File::exists($path)) {
            File::put($path, '');
            $this->info("Created SQLite database at: {$path}");
        }
    }

    protected function getWebhookUrl($type)
    {
        $appUrl = rtrim($this->currentEnv['APP_URL'] ?? 'http://localhost', '/');
        return "{$appUrl}/webhook/{$type}";
    }

    protected function generateWebhookSecret()
    {
        return bin2hex(random_bytes(32));
    }

    protected function updateEnv(array $values)
    {
        $content = File::exists($this->envPath) ? File::get($this->envPath) : '';

        foreach ($values as $key => $value) {
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            // Escape any quotes
            $value = str_replace('"', '\"', $value);

            // Wrap value in quotes if it contains spaces
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

        // Reload environment
        $this->loadEnvironmentFiles();
    }
}
