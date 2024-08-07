<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        Log::info("Starting markdown sync for model: {$modelClass}");

        $config = config("flatlayer.models.{$modelClass}");
        if (!isset($config['path']) || !isset($config['source'])) {
            Log::error("Path or source not configured for model {$modelClass}.");
            throw new \Exception("Path or source not configured for model {$modelClass}.");
        }

        $path = $config['path'];
        $source = $config['source'];
        $fullPattern = $path . '/' . $source;

        Log::info("Scanning directory: {$fullPattern}");
        $files = File::glob($fullPattern);
        Log::info("Found " . count($files) . " files to process");

        $existingSlugs = $modelClass::pluck('slug')->flip();
        $processedSlugs = [];

        foreach ($files as $file) {
            $slug = $this->getSlugFromFilename($file);
            $processedSlugs[] = $slug;

            if ($existingSlugs->has($slug)) {
                Log::info("Updating existing model: {$slug}");
                $model = $modelClass::where('slug', $slug)->first();
                $model->syncFromMarkdown($file);
            } else {
                Log::info("Creating new model: {$slug}");
                $newModel = $modelClass::fromMarkdown($file);
                $newModel->save();
            }
        }

        $slugsToDelete = $existingSlugs->diffKeys(array_flip($processedSlugs));
        $deleteCount = $slugsToDelete->count();
        Log::info("Deleting {$deleteCount} models that no longer have corresponding files");

        $slugsToDelete->chunk(self::CHUNK_SIZE)->each(function ($chunk) use ($modelClass) {
            $deletedCount = $modelClass::whereIn('slug', $chunk->keys())->delete();
            Log::info("Deleted {$deletedCount} models");
        });

        $hook = $config['hook'] ?? null;
        if ($hook) {
            Log::info("Sending request to hook: {$hook}");
            $response = Http::post($hook);
            if ($response->successful()) {
                Log::info("Hook request successful");
            } else {
                Log::warning("Hook request failed with status: " . $response->status());
            }
        }

        Log::info("Markdown sync completed for model: {$modelClass}");
    }

    private function getSlugFromFilename($filename)
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
    }
}
