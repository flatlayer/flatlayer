<?php

namespace App\Providers;

use App\Services\SearchRerankingService;
use App\Services\MarkdownContentProcessor;
use App\Services\ModelResolverService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SearchRerankingService::class, function ($app) {
            $apiKey = config('flatlayer.search.jina.key');
            $model = config('flatlayer.search.jina.model');
            return new SearchRerankingService($apiKey, $model);
        });

        $this->app->singleton(MarkdownContentProcessor::class, function ($app) {
            return new MarkdownContentProcessor();
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
