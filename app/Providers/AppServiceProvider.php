<?php

namespace App\Providers;

use App\Services\ImageService;
use App\Services\MarkdownProcessingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MarkdownProcessingService::class, function ($app) {
            return new MarkdownProcessingService($app->make(ImageService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
