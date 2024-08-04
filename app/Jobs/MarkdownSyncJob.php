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

    protected $modelClass;

    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function handle()
    {
        $modelClass = $this->modelClass;

        $source = config("flatlayer.models.{$modelClass}.source");
        if (!$source) {
            throw new \Exception("Source not configured for model {$modelClass}.");
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
                $existingModels->forget($slug);
            } else {
                $newModel = $modelClass::fromMarkdown($file);
                $newModel->save();
            }
        }

        foreach ($existingModels as $slug => $model) {
            $model->delete();
        }

        // Make a request to the hook (possibly to trigger a build)
        $hook = config("flatlayer.models.{$modelClass}.hook");
        if ($hook) {
            Http::post($hook);
        }
    }

    private function getSlugFromFilename($filename)
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME));
    }
}
