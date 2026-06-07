<?php

declare(strict_types=1);

use Trail\Trail\Models\TrailAggregate;
use Trail\Trail\Models\TrailEvent;

it('prunes events older than the retention window', function () {
    config()->set('trail.retention.events_days', 30);

    TrailEvent::create(['name' => 'old', 'occurred_at' => now()->subDays(40)]);
    TrailEvent::create(['name' => 'fresh', 'occurred_at' => now()->subDays(5)]);

    $this->artisan('trail:prune')->assertSuccessful();

    expect(TrailEvent::where('name', 'old')->exists())->toBeFalse()
        ->and(TrailEvent::where('name', 'fresh')->exists())->toBeTrue();
});

it('prunes aggregates older than their retention window', function () {
    config()->set('trail.retention.aggregates_days', 100);

    TrailAggregate::create(['period' => 'day', 'bucket' => now()->subDays(200), 'name' => 'old', 'count' => 1, 'unique_subjects' => 1]);
    TrailAggregate::create(['period' => 'day', 'bucket' => now()->subDays(10), 'name' => 'fresh', 'count' => 1, 'unique_subjects' => 1]);

    $this->artisan('trail:prune')->assertSuccessful();

    expect(TrailAggregate::where('name', 'old')->exists())->toBeFalse()
        ->and(TrailAggregate::where('name', 'fresh')->exists())->toBeTrue();
});
