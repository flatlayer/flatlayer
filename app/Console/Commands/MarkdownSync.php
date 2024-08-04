<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class MarkdownSync extends Command
{
    protected $signature = 'flatlayer:markdown-sync {model}';

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

        $source = config("flatlayer.models.{$modelClass}.source");
        if (!$source) {
            $this->error("Source not configured for model {$modelClass}.");
            return 1;
        }

        $files = File::glob($source);
        $existingModels = $modelClass::all()->keyBy(function ($model) {
            return $model->slug;
        });

        foreach ($files as $file) {
            $slug = $this->getSlugFromFilename($file);
            $content = File::get($file);

            if ($existingModels->has($slug)) {
                $model = $existingModels->get($slug);
                $model->syncFromMarkdown($file);
                $this->info("Updated: {$slug}");
                $existingModels->forget($slug);
            } else {
                $newModel = $modelClass::fromMarkdown($file);
                $newModel->save();
                $this->info("Created: {$slug}");
            }
        }

        foreach ($existingModels as $slug => $model) {
            $model->delete();
            $this->info("Deleted: {$slug}");
        }

        // Once we're done with the sync, make a request to the hook
        $hook = config("flatlayer.models.{$modelClass}.hook");
        if ($hook) {
            $response = Http::post($hook);
            $this->info("Hook response: {$response->status()}");
        }

        $this->info('File sync completed successfully.');
    }

    private function getSlugFromFilename($filename)
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
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
