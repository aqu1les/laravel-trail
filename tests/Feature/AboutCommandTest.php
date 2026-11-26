<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function trailAbout(): array
{
    Artisan::call('about', ['--only' => 'trail', '--json' => true]);

    return json_decode(Artisan::output(), true)['trail'];
}

it('adds a Trail section to artisan about', function () {
    expect(trailAbout())->toHaveKeys([
        'version',
        'recorder',
        'connection',
        'dashboard',
        'api',
        'page_view_tracking',
        'mcp_dashboard',
        'mcp_token',
        'retention',
    ]);
});

it('reflects the current config', function () {
    config([
        'trail.recorder' => 'ingest',
        'trail.path' => 'analytics',
        'trail.api.enabled' => false,
        'trail.auto_track.page_views' => true,
        'trail.retention.events_days' => 30,
    ]);

    expect(trailAbout())
        ->recorder->toBe('ingest')
        ->dashboard->toBe('/analytics')
        ->api->toBeFalse()
        ->page_view_tracking->toBeTrue()
        ->retention->toBe('30 days');
});

it('never reveals the MCP token', function () {
    config(['trail.mcp.dashboard.token' => 'super-secret-token']);

    $about = trailAbout();

    expect($about['mcp_token'])->toBe('set')
        ->and(json_encode($about))->not->toContain('super-secret-token');
});

it('reports the MCP token as not set when empty', function () {
    config(['trail.mcp.dashboard.token' => null]);

    expect(trailAbout()['mcp_token'])->toBe('not set');
});
