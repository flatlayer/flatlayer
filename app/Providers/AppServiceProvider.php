<?php

namespace App\Providers;

use App\Services\SearchRerankingService;
use App\Services\MarkdownMediaService;
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

        $this->app->singleton(MarkdownMediaService::class, function ($app) {
            return new MarkdownMediaService();
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
