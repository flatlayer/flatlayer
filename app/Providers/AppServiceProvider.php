<?php

namespace App\Providers;

use App\Services\JinaSearchService;
use App\Services\MarkdownProcessingService;
use App\Services\MediaFileService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JinaSearchService::class, function ($app) {
            $apiKey = config('flatlayer.search.jina.key');
            $rerankModel = config('flatlayer.search.jina.rerank');
            $embeddingModel = config('flatlayer.search.jina.embed');
            return new JinaSearchService($apiKey, $rerankModel, $embeddingModel);
        });

        $this->app->singleton(MarkdownProcessingService::class, function ($app) {
            return new MarkdownProcessingService($app->make(MediaFileService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
