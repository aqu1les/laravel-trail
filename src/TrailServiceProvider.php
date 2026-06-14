<?php

declare(strict_types=1);

namespace Trail\Trail;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server;
use Trail\Trail\Console\AggregateCommand;
use Trail\Trail\Console\InstallCommand;
use Trail\Trail\Console\PruneCommand;
use Trail\Trail\Contracts\ContextCaptureContract;
use Trail\Trail\Contracts\EventBuffer;
use Trail\Trail\Http\Middleware\Authorize;
use Trail\Trail\Http\Middleware\TrackPageView;
use Trail\Trail\Mcp\Dashboard\DashboardMcpServiceProvider;
use Trail\Trail\Support\ConfigMerge;
use Trail\Trail\Support\ContextCapture;
use Trail\Trail\Support\MemoryEventBuffer;
use Trail\Trail\Support\RedisEventBuffer;

class TrailServiceProvider extends ServiceProvider
{
    /**
     * Recursively merge the package config under any published user config,
     * so newly added nested keys reach users who published an older config.
     */
    protected function mergeConfigFrom($path, $key)
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');

        $config->set($key, ConfigMerge::merge(
            require $path,
            (array) $config->get($key, [])
        ));
    }

    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/trail.php', 'trail');

        $this->app->singleton(RecorderManager::class, fn (Application $app) => new RecorderManager($app));

        $this->app->singleton(ContextCaptureContract::class, function (Application $app): ContextCaptureContract {
            $class = config('trail.context_capture') ?? ContextCapture::class;

            return $app->make($class);
        });

        $this->app->singleton(Trail::class, fn (Application $app) => new Trail(
            $app->make(RecorderManager::class)
        ));

        $this->app->singleton(EventBuffer::class, function (Application $app): EventBuffer {
            $flushAt = (int) config('trail.ingest.flush_at', 100);

            if (config('trail.ingest.buffer') === 'redis') {
                return new RedisEventBuffer(
                    $app->make(Redis::class),
                    $flushAt,
                    config('trail.ingest.connection'),
                );
            }

            return new MemoryEventBuffer($flushAt);
        });
    }

    /**
     * Bootstrap the package services.
     */
    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerSchedule();
        $this->registerIngestFlush();
        $this->registerPageViewTracking();
        $this->registerDashboardMcp();
    }

    /**
     * Conditionally boot the Dashboard MCP sub-provider.
     *
     * Off by default. Boots only when explicitly enabled and the optional
     * laravel/mcp package is installed; otherwise logs a one-time hint.
     */
    private function registerDashboardMcp(): void
    {
        if (! config('trail.mcp.dashboard.enabled', false)) {
            return;
        }

        if (! $this->mcpServerAvailable()) {
            Log::warning('Trail: trail.mcp.dashboard.enabled is true but laravel/mcp is not installed. Run `composer require laravel/mcp` to use the Dashboard MCP, or set TRAIL_MCP_DASHBOARD=false.');

            return;
        }

        $this->app->register(DashboardMcpServiceProvider::class);
    }

    /**
     * Whether the optional laravel/mcp package is installed. Seam for testing.
     */
    protected function mcpServerAvailable(): bool
    {
        return class_exists(Server::class);
    }

    /**
     * Auto-load the package migrations so a plain `php artisan migrate` works.
     *
     * Skipped when the consumer has published the migrations into their app -
     * then theirs run instead of ours, avoiding a duplicate table.
     */
    private function registerMigrations(): void
    {
        if ($this->migrationsPublished()) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    private function migrationsPublished(): bool
    {
        return count(glob(database_path('migrations/*_create_trail_events_table.php'))) > 0;
    }

    /**
     * Auto-register the maintenance commands on the scheduler.
     */
    private function registerSchedule(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (config('trail.schedule.aggregate', true)) {
                $schedule->command('trail:aggregate')->hourly();
            }

            if (config('trail.schedule.prune', true)) {
                $schedule->command('trail:prune')->daily();
            }
        });
    }

    /**
     * Opt-in: push the page-view middleware onto the app's web group.
     */
    public function registerPageViewTracking(): void
    {
        if (! config('trail.auto_track.page_views', false)) {
            return;
        }

        // Push onto the HTTP kernel's group, not the router's. The kernel is the
        // source of truth: any later syncMiddlewareToRouter() (triggered when other
        // providers or bootstrap/app.php mutate middleware after we boot) overwrites
        // the router's groups from the kernel, which would drop a router-only push.
        $kernel = $this->app->make(HttpKernel::class);
        $kernel->appendMiddlewareToGroup('web', TrackPageView::class);
    }

    /**
     * Flush any buffered events at the end of the request/command lifecycle.
     */
    private function registerIngestFlush(): void
    {
        $this->app->terminating(function (): void {
            if ($this->app->resolved(EventBuffer::class)) {
                $this->app->make(EventBuffer::class)->flush();
            }
        });
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

        // Design-system source (tokens + components). Consumers running a
        // Tailwind v4 build `@import "trail/styles.css"` from here and may
        // override any --trail-* token afterwards to retheme the dashboard.
        $this->publishes([
            __DIR__.'/../resources/css/trail' => resource_path('css/trail'),
        ], 'trail-styles');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'trail-migrations');

        // Overridable dashboard views (e.g. the sidebar footer).
        $this->publishes([
            __DIR__.'/../resources/views/partials/sidebar-footer.blade.php' => resource_path('views/vendor/trail/partials/sidebar-footer.blade.php'),
        ], 'trail-views');

        // Agent skill: drops a Trail usage guide into the consumer's repo so AI
        // assistants know how to use the package.
        $this->publishes([
            __DIR__.'/../resources/skills/trail/SKILL.md' => base_path('.claude/skills/trail/SKILL.md'),
        ], 'trail-skill');
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
            AggregateCommand::class,
            PruneCommand::class,
            InstallCommand::class,
        ]);
    }
}
