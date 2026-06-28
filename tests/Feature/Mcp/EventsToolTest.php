<?php

declare(strict_types=1);

use Trail\Trail\Mcp\Dashboard\DashboardMcpServer;
use Trail\Trail\Mcp\Dashboard\Tools\EventsTool;
use Trail\Trail\Models\TrailEvent;

function seedEvents(int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        TrailEvent::create([
            'name' => 'product.viewed',
            'properties' => ['sku' => "SKU-{$i}"],
            'occurred_at' => now()->subSeconds($i),
        ]);
    }
}

it('respects an explicit limit', function () {
    seedEvents(5);

    DashboardMcpServer::tool(EventsTool::class, ['limit' => 2])
        ->assertOk()
        ->assertSee('"source":"events"')
        ->assertSee('"truncated":true'); // 5 available, returned 2
});

it('clamps above events_max and marks truncated', function () {
    config()->set('trail.mcp.dashboard.events_max', 3);
    seedEvents(10);

    DashboardMcpServer::tool(EventsTool::class, ['limit' => 999])
        ->assertOk()
        ->assertSee('"truncated":true')
        ->assertSee('"limit":3');
});

it('omits properties and context by default', function () {
    seedEvents(1);

    DashboardMcpServer::tool(EventsTool::class, ['include_properties' => true])
        ->assertOk()
        ->assertSee('product.viewed')
        ->assertDontSee('SKU-0'); // exposure disabled by config
});

it('exposes properties only when the config switch is on', function () {
    config()->set('trail.mcp.dashboard.expose_properties', true);
    seedEvents(1);

    DashboardMcpServer::tool(EventsTool::class, ['include_properties' => true])
        ->assertOk()
        ->assertSee('SKU-0');
});
