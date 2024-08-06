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
        Log::info("Starting ProcessGitHubWebhookJob for {$this->modelClass}");

        $config = config("flatlayer.models.{$this->modelClass}");

        if (!$config) {
            Log::error("Configuration not found for model: {$this->modelClass}");
            return;
        }

        Log::info("Configuration found for model: {$this->modelClass}");
        Log::info("Processing webhook for model: {$this->modelClass}");

        try {
            $this->processModelRepository($config);
        } catch (\Exception $e) {
            Log::error("Exception in processModelRepository: " . $e->getMessage());
            throw $e;
        }

        Log::info("Finished ProcessGitHubWebhookJob for {$this->modelClass}");
    }

    protected function processModelRepository($modelConfig)
    {
        $path = $modelConfig['path'];
        Log::info("Repository path: {$path}");

        $git = app()->make('CzProject\GitPhp\Git');
        Log::info("Git instance created");

        try {
            $repo = $git->open($path);
            Log::info("Opened Git repository");
        } catch (\Exception $e) {
            Log::error("Failed to open Git repository: " . $e->getMessage());
            throw $e;
        }

        $beforeHash = $repo->getCurrentBranchName();
        Log::info("Current branch before pull: {$beforeHash}");

        try {
            $repo->pull();
            Log::info("Pull completed successfully");
        } catch (\Exception $e) {
            Log::error("Failed to pull repository for {$this->modelClass}: " . $e->getMessage());
            throw $e;
        }

        $afterHash = $repo->getCurrentBranchName();
        Log::info("Current branch after pull: {$afterHash}");

        if ($beforeHash !== $afterHash) {
            Log::info("Changes detected for {$this->modelClass}, running MarkdownSync");
            Artisan::call('flatlayer:markdown-sync', ['model' => $this->modelClass]);
            Log::info("MarkdownSync command called");
        } else {
            Log::info("No changes detected for {$this->modelClass}");
        }
    }

    public function getModelClass()
    {
        return $this->modelClass;
    }
}
