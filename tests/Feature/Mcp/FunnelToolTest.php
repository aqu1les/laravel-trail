<?php

declare(strict_types=1);

use Trail\Trail\Mcp\Dashboard\DashboardMcpServer;
use Trail\Trail\Mcp\Dashboard\Tools\FunnelTool;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;

it('matches FunnelReport output for the same steps', function () {
    $u1 = User::create(['name' => 'A']);
    $u2 = User::create(['name' => 'B']);

    foreach ([$u1, $u2] as $u) {
        TrailEvent::create(['name' => 'signup', 'subject_type' => $u->getMorphClass(), 'subject_id' => $u->getKey(), 'occurred_at' => now()]);
    }
    TrailEvent::create(['name' => 'purchase', 'subject_type' => $u1->getMorphClass(), 'subject_id' => $u1->getKey(), 'occurred_at' => now()]);

    DashboardMcpServer::tool(FunnelTool::class, [
        'steps' => ['signup', 'purchase'],
    ])
        ->assertOk()
        ->assertSee('"overall_conversion":0.5')
        ->assertSee('"source":"events"');
});
