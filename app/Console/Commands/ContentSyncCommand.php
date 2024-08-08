<?php

namespace App\Console\Commands;

use App\Jobs\ContentSyncJob;
use App\Services\SyncConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use CzProject\GitPhp\Git;

class ContentSyncCommand extends Command
{
    protected $signature = 'flatlayer:content-sync
                            {path? : Path to the content folder}
                            {--type= : Content type (required if path is not provided)}
                            {--pattern= : Glob pattern for finding content files}
                            {--pull : Pull latest changes from Git repository before syncing}
                            {--skip : Skip syncing if no changes are detected}
                            {--dispatch : Dispatch the job to the queue}';

    protected $description = 'Sync files from source folder to ContentItems, optionally pulling latest changes.';

    protected $syncConfigService;

    public function __construct(SyncConfigurationService $syncConfigService)
    {
        parent::__construct();
        $this->syncConfigService = $syncConfigService;
    }

    public function handle(Git $git)
    {
        $path = $this->argument('path');
        $type = $this->option('type');

        if (!$path && !$type) {
            $this->error("Either 'path' argument or '--type' option must be provided.");
            return Command::FAILURE;
        }

        if (!$path) {
            if (!$this->syncConfigService->hasConfig($type)) {
                $this->error("Configuration for type '{$type}' not found.");
                return Command::FAILURE;
            }
            $config = $this->syncConfigService->getConfig($type);
            $path = $config['path'];
            $pattern = $config['--pattern'] ?? '*.md';
        } else {
            $type = $type ?? Str::singular(basename($path));
            $pattern = $this->option('pattern') ?? '*.md';
        }

        $fullPath = realpath($path);

        if (!$fullPath || !is_dir($fullPath)) {
            $this->error("The provided path does not exist or is not a directory.");
            return Command::FAILURE;
        }

        $shouldPull = $this->option('pull');
        $skipIfNoChanges = $this->option('skip');

        $job = new ContentSyncJob($fullPath, $type, $pattern, $shouldPull, $skipIfNoChanges);

        if ($this->option('dispatch')) {
            dispatch($job);
            $this->info("ContentSyncCommand job for type '{$type}' has been dispatched to the queue.");
        } else {
            $job->handle($git);
            $this->info("ContentSyncCommand for type '{$type}' completed successfully.");
        }

        return Command::SUCCESS;
    }
}
