<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Trail\Trail\Trail;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The real provider only publishes migrations; load them here for the harness.
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

        // Open the dashboard in the local harness.
        Trail::auth(fn () => $this->app->environment('local', 'testing'));

        // Point the default actor at the workbench User and write synchronously.
        config([
            'trail.recorder' => 'sync',
            'trail.subject.model' => User::class,
        ]);
    }
}
