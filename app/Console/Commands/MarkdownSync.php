<?php

namespace App\Console\Commands;

use App\Jobs\MarkdownSyncJob;
use App\Services\ModelResolverService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class MarkdownSync extends Command
{
    protected $signature = 'flatlayer:markdown-sync {model} {--dispatch : Dispatch the job to the queue}';

    protected $description = 'Sync files from source to models.';

    public function handle()
    {
        $modelInput = $this->argument('model');

        $resolver = app(ModelResolverService::class);
        $modelClass = $resolver->resolve($modelInput);

        if (!$modelClass) {
            $this->error("Model class for '{$modelInput}' does not exist.");
            return 1;
        }

        $model = new $modelClass;
        if (!$model instanceof Model) {
            $this->error("The provided class must be an Eloquent model.");
            return 1;
        }

        $job = new MarkdownSyncJob($modelClass);

        if ($this->option('dispatch')) {
            dispatch($job);
            $this->info("MarkdownSync job for {$modelClass} has been dispatched to the queue.");
        } else {
            $job->handle();
            $this->info("MarkdownSync for {$modelClass} completed successfully.");
        }

        return Command::SUCCESS;
    }

    private function resolveModelClass($input)
    {

    }
}
