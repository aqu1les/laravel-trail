<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Trail\Trail\Mcp\Dashboard\DashboardMcpServer;
use Trail\Trail\Mcp\Dashboard\Tools\MetricsTool;
use Trail\Trail\Models\TrailAggregate;
use Trail\Trail\Models\TrailEvent;

beforeEach(function () {
    Carbon::setTestNow('2026-06-14T12:00:00Z');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reads from events and reports the events source when no aggregates cover the range', function () {
    TrailEvent::create(['name' => 'a', 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'b', 'occurred_at' => now()]);

    DashboardMcpServer::tool(MetricsTool::class, [
        'from' => now()->subDay()->toIso8601String(),
        'to' => now()->toIso8601String(),
        'period' => 'day',
    ])
        ->assertOk()
        ->assertSee('"total_events":2')
        ->assertSee('"source":"events"');
});

it('reads from aggregates and reports the aggregates source when covered', function () {
    TrailAggregate::create([
        'period' => 'day',
        'bucket' => now()->startOfDay(),
        'name' => 'order.placed',
        'count' => 7,
        'unique_subjects' => 3,
        'sum_value' => 100,
    ]);

    DashboardMcpServer::tool(MetricsTool::class, [
        'from' => now()->subDay()->toIso8601String(),
        'to' => now()->toIso8601String(),
        'period' => 'day',
    ])
        ->assertOk()
        ->assertSee('"total_events":7')
        ->assertSee('"source":"aggregates"')
        ->assertSee('order.placed');
});
