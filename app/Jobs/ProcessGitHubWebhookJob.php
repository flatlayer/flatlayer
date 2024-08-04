<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use CzProject\GitPhp\Git;

class ProcessGitHubWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;
    protected $modelClass;

    public function __construct($payload, $modelClass)
    {
        $this->payload = $payload;
        $this->modelClass = $modelClass;
    }

    public function handle()
    {
        $config = config("flatlayer.models.{$this->modelClass}");

        if (!$config) {
            Log::error("Configuration not found for model: {$this->modelClass}");
            return;
        }

        $this->processModelRepository($config);
    }

    protected function processModelRepository($modelConfig)
    {
        $path = $modelConfig['path'];
        $git = new Git;
        $repo = $git->open($path);

        $beforeHash = $repo->getCurrentBranchName();

        try {
            $repo->pull();
        } catch (\Exception $e) {
            Log::error("Failed to pull repository for {$this->modelClass}: " . $e->getMessage());
            return;
        }

        $afterHash = $repo->getCurrentBranchName();

        if ($beforeHash !== $afterHash) {
            Log::info("Changes detected for {$this->modelClass}, running MarkdownSync");
            Artisan::call('flatlayer:markdown-sync', ['model' => $this->modelClass]);
        } else {
            Log::info("No changes detected for {$this->modelClass}");
        }
    }
}
