<?php

namespace App\Console\Commands;

use App\Jobs\ContentSyncJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ContentSync extends Command
{
    protected $signature = 'flatlayer:content-sync
                            {path : Path to the content folder}
                            {--type= : Content type (defaults to singular form of folder name)}
                            {--pattern=**/*.md : Glob pattern for finding content files}
                            {--pull : Pull latest changes from Git repository before syncing}
                            {--dispatch : Dispatch the job to the queue}';

    protected $description = 'Sync files from source folder to ContentItems, optionally pulling latest changes.';

    public function handle()
    {
        $relativePath = $this->argument('path');
        $fullPath = realpath($relativePath);

        if (!$fullPath || !is_dir($fullPath)) {
            $this->error("The provided path does not exist or is not a directory.");
            return 1;
        }

        $type = $this->option('type') ?? Str::singular(basename($fullPath));
        $pattern = $this->option('pattern');
        $shouldPull = $this->option('pull');

        $job = new ContentSyncJob($fullPath, $type, $pattern, $shouldPull);

        if ($this->option('dispatch')) {
            dispatch($job);
            $this->info("ContentSync job for type '{$type}' has been dispatched to the queue.");
        } else {
            $job->handle();
            $this->info("ContentSync for type '{$type}' completed successfully.");
        }

        return Command::SUCCESS;
    }
}
