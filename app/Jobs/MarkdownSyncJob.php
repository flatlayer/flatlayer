<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MarkdownSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const CHUNK_SIZE = 100;

    protected $modelClass;

    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function handle()
    {
        $modelClass = $this->modelClass;

        $config = config("flatlayer.models.{$modelClass}");
        if (!isset($config['path']) || !isset($config['source'])) {
            throw new \Exception("Path or source not configured for model {$modelClass}.");
        }

        $path = $config['path'];
        $source = $config['source'];
        $fullPattern = $path . '/' . $source;

        $files = File::glob($fullPattern);

        // Get only the slugs of existing models
        $existingSlugs = $modelClass::pluck('slug')->flip();

        $processedSlugs = [];

        foreach ($files as $file) {
            $slug = $this->getSlugFromFilename($file);
            $processedSlugs[] = $slug;

            if ($existingSlugs->has($slug)) {
                // Update existing model
                $model = $modelClass::where('slug', $slug)->first();
                $model->syncFromMarkdown($file);
            } else {
                // Create new model
                $newModel = $modelClass::fromMarkdown($file);
                $newModel->save();
            }
        }

        // Delete models that no longer have corresponding files
        $slugsToDelete = $existingSlugs->diffKeys(array_flip($processedSlugs));
        $slugsToDelete->chunk(self::CHUNK_SIZE)->each(function ($chunk) use ($modelClass) {
            $modelClass::whereIn('slug', $chunk->keys())->delete();
        });

        // Make a request to the hook (possibly to trigger a build)
        $hook = $config['hook'] ?? null;
        if ($hook) {
            Http::post($hook);
        }
    }

    private function getSlugFromFilename($filename)
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
    }
}
