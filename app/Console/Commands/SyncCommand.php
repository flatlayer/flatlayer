<?php

namespace App\Console\Commands;

use App\Jobs\EntrySyncJob;
use App\Services\Content\ContentSyncManager;
use App\Services\Storage\StorageResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class SyncCommand extends Command
{
    protected $signature = 'flatlayer:sync
                            {--type= : Content type (required)}
                            {--disk= : Optional disk name to use instead of configured repository}
                            {--pull : Pull latest changes from Git repository before syncing}
                            {--skip : Skip syncing if no changes are detected}
                            {--dispatch : Dispatch the job to the queue}
                            {--webhook= : URL of the webhook to trigger after sync}';

    protected $description = 'Sync files from source to Entries, optionally pulling latest changes and triggering a webhook.';

    public function __construct(
        protected ContentSyncManager $syncService,
        protected StorageResolver $diskResolver,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $type = $this->option('type');

        if (! $type) {
            $this->error("The '--type' option is required.");

            return Command::FAILURE;
        }

        try {
            // If disk is specified, use it directly
            if ($diskName = $this->option('disk')) {
                // Try to resolve the disk
                try {
                    $disk = $this->diskResolver->resolve($diskName, $type);
                    $webhookUrl = $this->option('webhook');
                    $shouldPull = $this->option('pull');
                } catch (\InvalidArgumentException $e) {
                    $this->error("Invalid disk specified: {$diskName}");

                    return Command::FAILURE;
                }
            }
            // Otherwise use repository configuration
            else {
                if (! Config::has("flatlayer.repositories.{$type}")) {
                    $this->error("No repository configuration found for type: {$type}");

                    return Command::FAILURE;
                }

                $config = Config::get("flatlayer.repositories.{$type}");
                $disk = $this->diskResolver->resolve($config['disk'], $type);
                $shouldPull = $this->option('pull') ?? $config['pull'] ?? false;
                $webhookUrl = $this->option('webhook') ?? $config['webhook_url'] ?? null;
            }

            $skipIfNoChanges = $this->option('skip') ?? false;

            $job = new EntrySyncJob(
                type: $type,
                disk: $disk,
                shouldPull: $shouldPull,
                skipIfNoChanges: $skipIfNoChanges,
                webhookUrl: $webhookUrl
            );

            if ($this->option('dispatch')) {
                dispatch($job);
                $this->info("EntrySyncJob for type '{$type}' has been dispatched to the queue.");
            } else {
                $job->handle($this->syncService);
                $this->info("EntrySyncJob for type '{$type}' completed successfully.");
            }

            if ($webhookUrl) {
                $this->info("Webhook URL set: {$webhookUrl}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error running sync: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
