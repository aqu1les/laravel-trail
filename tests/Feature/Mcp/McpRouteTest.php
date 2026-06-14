<?php

declare(strict_types=1);

use Trail\Trail\Mcp\Dashboard\DashboardMcpServiceProvider;
use Trail\Trail\Trail;

beforeEach(function () {
    // The app is already booted, so registering the sub-provider here runs its
    // boot() immediately and mounts the MCP route on the router.
    config()->set('trail.mcp.dashboard.enabled', true);
    config()->set('trail.mcp.dashboard.path', 'trail-mcp');
    config()->set('trail.mcp.dashboard.token', 'secret-token');

    $this->app->register(DashboardMcpServiceProvider::class);
});

afterEach(function () {
    Trail::mcpUsing(null);
});

it('denies the MCP endpoint by default in a non-local environment', function () {
    // testbench runs as the "testing" environment, so the default gate denies.
    $this->postJson('/trail-mcp')->assertForbidden();
});

it('passes the gate when a registered token matches', function () {
    Trail::mcpUsing(fn ($request) => hash_equals(
        (string) config('trail.mcp.dashboard.token'),
        (string) $request->bearerToken(),
    ));

    $response = $this->postJson('/trail-mcp', [], [
        'Authorization' => 'Bearer secret-token',
    ]);

    // The gate passed; the MCP handler takes over (any non-403 status).
    expect($response->status())->not->toBe(403);
});

it('rejects a wrong token with 403', function () {
    Trail::mcpUsing(fn ($request) => hash_equals(
        (string) config('trail.mcp.dashboard.token'),
        (string) $request->bearerToken(),
    ));

    $this->postJson('/trail-mcp', [], [
        'Authorization' => 'Bearer wrong',
    ])->assertForbidden();
});

it('serves the MCP route on a stateless pipeline (no CSRF 419)', function () {
    Trail::mcpUsing(fn () => true);

    $response = $this->post('/trail-mcp', [], [
        'Authorization' => 'Bearer secret-token',
    ]);

    // Not under the web group: no VerifyCsrfToken, so never a 419.
    expect($response->status())->not->toBe(419);
    expect($response->status())->not->toBe(403);
});
