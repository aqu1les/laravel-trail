<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\FunnelReport;
use Trail\Trail\Tests\Fixtures\User;

it('restricts the funnel to events inside the given window', function () {
    $user = User::create(['name' => 'Ada']);

    // In-window signup; out-of-window purchase.
    TrailEvent::create([
        'name' => 'signup',
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->getKey(),
        'occurred_at' => Carbon::parse('2026-06-10T00:00:00Z'),
    ]);
    TrailEvent::create([
        'name' => 'purchase',
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->getKey(),
        'occurred_at' => Carbon::parse('2026-06-01T00:00:00Z'),
    ]);

    $report = app(FunnelReport::class)->build(
        ['signup', 'purchase'],
        Carbon::parse('2026-06-05T00:00:00Z'),
        Carbon::parse('2026-06-14T00:00:00Z'),
    );

    expect($report['steps'][0]['count'])->toBe(1); // signup in window
    expect($report['steps'][1]['count'])->toBe(0); // purchase outside window
});

it('still works with no window (backward compatible)', function () {
    $user = User::create(['name' => 'Ada']);
    TrailEvent::create([
        'name' => 'signup',
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->getKey(),
        'occurred_at' => now(),
    ]);

    $report = app(FunnelReport::class)->build(['signup']);

    expect($report['steps'][0]['count'])->toBe(1);
});
