<?php

declare(strict_types=1);

namespace ImageApi\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use ImageApi\ImageApi;
use ImageApi\Laravel\View\Components\OgImage;

final class ImageApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/imageapi.php', 'imageapi');

        $this->app->singleton(
            ImageApiManager::class,
            static fn ($app): ImageApiManager => new ImageApiManager((array) $app['config']->get('imageapi', [])),
        );

        // Type-hinting the SDK client in a job or controller gets the configured one.
        $this->app->bind(ImageApi::class, static fn ($app): ImageApi => $app->make(ImageApiManager::class)->client());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'imageapi');

        Blade::component('og-image', OgImage::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/imageapi.php' => config_path('imageapi.php')], 'imageapi-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/imageapi')], 'imageapi-views');
        }
    }
}
