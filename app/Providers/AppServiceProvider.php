<?php

namespace App\Providers;

use App\Services\JinaRerankService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JinaRerankService::class, function ($app) {
            $apiKey = config('flatlayer.search.jina.key');
            $model = config('flatlayer.search.jina.model');
            return new JinaRerankService($apiKey, $model);
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
