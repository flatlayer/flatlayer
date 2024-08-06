<?php

namespace App\Console\Commands;

use App\Jobs\MarkdownSyncJob;
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
        $modelClass = $this->resolveModelClass($modelInput);

        if (!$modelClass) {
            $this->error("Model class for '{$modelInput}' does not exist.");
            return 1;
        }

        $model = new $modelClass;
        if (!$model instanceof Model || !method_exists($model, 'getSlugOptions')) {
            $this->error("The provided class must be a sluggable Eloquent model.");
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
        // Check if the input is already a valid class name
        if (class_exists($input)) {
            return $input;
        }

        // Convert kebab-case to StudlyCase and prepend the namespace
        $studlyName = Str::studly($input);
        $fullClassName = "App\\Models\\{$studlyName}";

        return class_exists($fullClassName) ? $fullClassName : null;
    }
}
