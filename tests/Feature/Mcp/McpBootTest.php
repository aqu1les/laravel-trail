<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Trail\Trail\Tests\Fixtures\TrailServiceProviderWithoutMcp;

it('does not register the MCP route when disabled (default)', function () {
    // Default config: trail.mcp.dashboard.enabled = false.
    $this->postJson('/mcp/trail')->assertNotFound();
});

it('logs a warning and does not crash when enabled without laravel/mcp', function () {
    Log::spy();

    $provider = new TrailServiceProviderWithoutMcp($this->app);
    config()->set('trail.mcp.dashboard.enabled', true);

    // Calling the guarded method directly exercises the missing-package branch.
    $method = new ReflectionMethod($provider, 'registerDashboardMcp');
    $method->setAccessible(true);
    $method->invoke($provider);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'laravel/mcp'))
        ->once();
});
