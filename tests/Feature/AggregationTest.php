<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Trail\Trail\Models\TrailAggregate;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\Aggregator;

it('persists an aggregate row with the unique tuple', function () {
    $agg = TrailAggregate::create([
        'period' => 'day',
        'bucket' => now()->startOfDay(),
        'name' => 'order.placed',
        'count' => 5,
        'unique_subjects' => 3,
        'sum_value' => 100.0,
    ]);

    expect($agg->count)->toBe(5);
    $this->assertDatabaseHas('trail_aggregates', ['name' => 'order.placed', 'period' => 'day']);
});

it('aggregates events into day buckets idempotently', function () {
    $day = now()->startOfDay()->addHours(9);
    TrailEvent::insert([
        ['uuid' => (string) Str::uuid(), 'name' => 'order.placed', 'subject_type' => 'U', 'subject_id' => 1, 'value' => 10, 'occurred_at' => $day, 'created_at' => $day],
        ['uuid' => (string) Str::uuid(), 'name' => 'order.placed', 'subject_type' => 'U', 'subject_id' => 1, 'value' => 5, 'occurred_at' => $day, 'created_at' => $day],
        ['uuid' => (string) Str::uuid(), 'name' => 'order.placed', 'subject_type' => 'U', 'subject_id' => 2, 'value' => 7, 'occurred_at' => $day, 'created_at' => $day],
    ]);

    (new Aggregator)->aggregate('day', now()->subDay(), now());
    (new Aggregator)->aggregate('day', now()->subDay(), now()); // idempotent (upsert)

    $agg = TrailAggregate::firstWhere('name', 'order.placed');
    expect($agg->count)->toBe(3)
        ->and($agg->unique_subjects)->toBe(2)
        ->and((float) $agg->sum_value)->toBe(22.0);
});

it('runs the aggregate command for a period', function () {
    TrailEvent::create(['name' => 'a', 'occurred_at' => now()]);

    $this->artisan('trail:aggregate', ['--period' => 'day'])->assertSuccessful();

    expect(TrailAggregate::where('name', 'a')->exists())->toBeTrue();
});
