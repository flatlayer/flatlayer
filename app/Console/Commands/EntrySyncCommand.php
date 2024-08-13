<?php

namespace App\Console\Commands;

use App\Jobs\EntrySyncJob;
use App\Services\SyncConfigurationService;
use CzProject\GitPhp\Git;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EntrySyncCommand extends Command
{
    protected $signature = 'flatlayer:entry-sync
                            {--type= : Content type (required)}
                            {--path= : Override the path to the content folder}
                            {--pattern= : Override the glob pattern for finding content files}
                            {--pull : Pull latest changes from Git repository before syncing}
                            {--skip : Skip syncing if no changes are detected}
                            {--dispatch : Dispatch the job to the queue}
                            {--webhook= : URL of the webhook to trigger after sync}';

    protected $description = 'Sync files from source folder to Entries, optionally pulling latest changes and triggering a webhook.';

    protected SyncConfigurationService $syncConfigService;

    public function __construct(SyncConfigurationService $syncConfigService)
    {
        parent::__construct();
        $this->syncConfigService = $syncConfigService;
    }

    public function handle()
    {
        $type = $this->option('type');

        if (! $type) {
            $this->error("The '--type' option is required.");
            return Command::FAILURE;
        }

        $config = $this->syncConfigService->getConfig($type);

        $path = $this->option('path') ?? $config['PATH'] ?? null;
        $pattern = $this->option('pattern') ?? $config['PATTERN'] ?? '*.md';
        $shouldPull = (bool) ( $config['PULL'] ?? $this->option('pull') ?? false );
        $skipIfNoChanges = $this->option('skip') ?? false;
        $webhookUrl = $this->option('webhook') ?? $config['WEBHOOK'] ?? null;

        if (! $path) {
            $this->error("Path not provided in configuration or command line for type '{$type}'.");
            return Command::FAILURE;
        }

        $fullPath = realpath($path);

        if (! $fullPath || ! is_dir($fullPath)) {
            $this->error("The provided path '{$path}' does not exist or is not a directory.");
            return Command::FAILURE;
        }

        $job = new EntrySyncJob(
            path: $fullPath,
            type: $type,
            pattern: $pattern,
            shouldPull: $shouldPull,
            skipIfNoChanges: $skipIfNoChanges,
            webhookUrl: $webhookUrl
        );

        if ($this->option('dispatch')) {
            dispatch($job);
            $this->info("EntrySyncJob for type '{$type}' has been dispatched to the queue.");
        } else {
            $job->handle(app(Git::class));
            $this->info("EntrySyncJob for type '{$type}' completed successfully.");
        }

        if ($webhookUrl) {
            $this->info("Webhook URL set: {$webhookUrl}");
        }

        return Command::SUCCESS;
    }
}
