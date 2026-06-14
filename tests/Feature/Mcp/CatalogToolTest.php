<?php

declare(strict_types=1);

use Trail\Trail\Mcp\Dashboard\DashboardMcpServer;
use Trail\Trail\Mcp\Dashboard\Tools\CatalogTool;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;

function seedCatalogEvents(): void
{
    $user = User::create(['name' => 'Ada']);

    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->getKey(),
        'value' => 10,
        'occurred_at' => now()->subDay(),
    ]);
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->getKey(),
        'value' => 30,
        'occurred_at' => now(),
    ]);
    TrailEvent::create([
        'name' => 'product.viewed',
        'occurred_at' => now(),
    ]);
}

it('returns per-name aggregates over seeded events', function () {
    seedCatalogEvents();

    DashboardMcpServer::tool(CatalogTool::class, [])
        ->assertOk()
        ->assertSee('order.placed')
        ->assertSee('product.viewed')
        ->assertSee('"count":2')          // order.placed count
        ->assertSee('"sum":40')           // 10 + 30
        ->assertSee('"unique_subjects":1')
        ->assertSee('"source":"events"');
});

it('reports events that carry no value with a null value summary', function () {
    seedCatalogEvents();

    DashboardMcpServer::tool(CatalogTool::class, [])
        ->assertOk()
        ->assertSee('"value":null');
});
