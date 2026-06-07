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
        // Trail's migrations are auto-loaded by its service provider.

        // Open the dashboard in the local harness.
        Trail::auth(fn () => $this->app->environment('local', 'testing'));

        // Point the default actor at the workbench User and write synchronously.
        config([
            'trail.recorder' => 'sync',
            'trail.subject.model' => User::class,
        ]);
    }
}
