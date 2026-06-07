<?php

declare(strict_types=1);

namespace Trail\Trail;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Trail\Trail\Http\Middleware\Authorize;

class TrailServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/trail.php', 'trail');

        $this->app->singleton(RecorderManager::class, fn (Application $app) => new RecorderManager($app));

        $this->app->singleton(Trail::class, fn (Application $app) => new Trail(
            $app->make(RecorderManager::class)
        ));
    }

    /**
     * Bootstrap the package services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the package's auto-discovered routes.
     */
    private function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('trail.path', 'trail'),
            'middleware' => array_merge((array) config('trail.middleware', ['web']), [Authorize::class]),
            'as' => 'trail.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register the package's views.
     */
    private function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'trail');
    }

    /**
     * Register the package's publishable resources.
     */
    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/trail.php' => config_path('trail.php'),
        ], 'trail-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'trail-migrations');

        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/trail'),
        ], 'trail-assets');
    }

    /**
     * Register the package's Artisan commands.
     */
    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            //
        ]);
    }
}
