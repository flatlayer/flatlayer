<?php

namespace App\Providers;

use App\Services\JinaSearchService;
use App\Services\MarkdownContentProcessingService;
use App\Services\ModelResolverService;
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

        $this->app->singleton(MarkdownContentProcessingService::class, function ($app) {
            return new MarkdownContentProcessingService();
        });

        $this->app->singleton(ModelResolverService::class, function ($app) {
            return new ModelResolverService();
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
