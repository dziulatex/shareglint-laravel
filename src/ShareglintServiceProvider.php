<?php

declare(strict_types=1);

namespace Shareglint\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Shareglint\Laravel\View\Components\OgImage;
use Shareglint\Shareglint;

final class ShareglintServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shareglint.php', 'shareglint');

        $this->app->singleton(
            ShareglintManager::class,
            static fn ($app): ShareglintManager => new ShareglintManager((array) $app['config']->get('shareglint', [])),
        );

        // Type-hinting the SDK client in a job or controller gets the configured one.
        $this->app->bind(Shareglint::class, static fn ($app): Shareglint => $app->make(ShareglintManager::class)->client());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shareglint');

        Blade::component('og-image', OgImage::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/shareglint.php' => config_path('shareglint.php')], 'shareglint-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/shareglint')], 'shareglint-views');
        }
    }
}
