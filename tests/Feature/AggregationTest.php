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
    $this->travelTo(now()->startOfDay()->addHours(12));

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

it('counts actors with the same id but different type as two distinct unique subjects when aggregating', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $day = now()->startOfDay()->addHours(9);
    TrailEvent::insert([
        ['uuid' => (string) Str::uuid(), 'name' => 'login', 'subject_type' => 'App\\Models\\User', 'subject_id' => 1, 'value' => null, 'occurred_at' => $day, 'created_at' => $day],
        ['uuid' => (string) Str::uuid(), 'name' => 'login', 'subject_type' => 'App\\Models\\Team', 'subject_id' => 1, 'value' => null, 'occurred_at' => $day, 'created_at' => $day],
    ]);

    (new Aggregator)->aggregate('day', now()->subDay(), now());

    expect(TrailAggregate::firstWhere('name', 'login')->unique_subjects)->toBe(2);
});

it('runs the aggregate command for a period', function () {
    TrailEvent::create(['name' => 'a', 'occurred_at' => now()]);

    $this->artisan('trail:aggregate', ['--period' => 'day'])->assertSuccessful();

    expect(TrailAggregate::where('name', 'a')->exists())->toBeTrue();
});

// The default schedule runs `trail:aggregate --since="2 days"` hourly. That
// window starts mid-day, and the upsert replaces the stored row, so the oldest
// bucket must still be recomputed from its first event, not from the window.
it('recomputes the oldest bucket whole when the window starts mid-bucket', function () {
    $day = now()->subDays(5)->startOfDay();
    foreach (range(1, 10) as $_) {
        TrailEvent::create(['name' => 'order.placed', 'occurred_at' => $day->copy()->addHour()]);
    }
    TrailEvent::create(['name' => 'order.placed', 'occurred_at' => $day->copy()->setTime(23, 30)]);

    $this->travelTo($day->copy()->addDay()); // midnight right after: the whole day is in the window
    $this->artisan('trail:aggregate')->assertSuccessful();
    expect(TrailAggregate::where('period', 'day')->value('count'))->toBe(11);

    $this->travelTo($day->copy()->addDays(2)->setTime(12, 0)); // window now starts at 12:00 of that day
    $this->artisan('trail:aggregate')->assertSuccessful();
    expect(TrailAggregate::where('period', 'day')->where('bucket', $day)->value('count'))->toBe(11);
});

it('aligns the window to the bucket for every period', function (string $period, string $since) {
    $this->travelTo(now()->startOfMonth()->addDays(20)->setTime(12, 30));

    $at = now()->sub($since)->startOf(match ($period) {
        'hour' => 'hour', 'week' => 'week', 'month' => 'month', default => 'day',
    })->addSecond();
    TrailEvent::create(['name' => 'edge', 'occurred_at' => $at]);

    (new Aggregator)->aggregate($period, now()->sub($since), now());

    expect(TrailAggregate::where('period', $period)->where('name', 'edge')->value('count'))->toBe(1);
})->with([
    'hour' => ['hour', '3 hours'],
    'day' => ['day', '2 days'],
    'week' => ['week', '1 week'],
    'month' => ['month', '1 month'],
]);

it('aggregates more events than one chunk', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    $at = now()->subHour();

    TrailEvent::insert(collect(range(1, 2500))->map(fn (int $i) => [
        'uuid' => (string) Str::uuid(), 'name' => 'bulk', 'subject_type' => 'U', 'subject_id' => $i % 7,
        'value' => 1, 'occurred_at' => $at, 'created_at' => $at,
    ])->all());

    (new Aggregator)->aggregate('day', now()->subDay(), now());

    $agg = TrailAggregate::firstWhere('name', 'bulk');
    expect($agg->count)->toBe(2500)
        ->and($agg->unique_subjects)->toBe(7)
        ->and((float) $agg->sum_value)->toBe(2500.0);
});
