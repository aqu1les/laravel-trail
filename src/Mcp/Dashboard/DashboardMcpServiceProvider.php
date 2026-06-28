<?php

declare(strict_types=1);

namespace Trail\Trail\Mcp\Dashboard;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Trail\Trail\Http\Middleware\Authorize;

class DashboardMcpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $path = (string) config('trail.mcp.dashboard.path', 'mcp/trail');

        $middleware = array_merge(
            (array) config('trail.mcp.dashboard.middleware', []),
            [Authorize::class.':mcp'],
        );

        // Stateless pipeline: no web group, session, cookies, or CSRF. The
        // Authorize:mcp ability resolves the independent Trail::canAccessMcp gate.
        Mcp::web('/'.ltrim($path, '/'), DashboardMcpServer::class)
            ->middleware($middleware);
    }
}
