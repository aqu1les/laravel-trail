<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Trail;

/**
 * The chart series is the densest logic on the Overview and the only part of it
 * that emits per-driver SQL (strftime / DATE_FORMAT / to_char in groupedCounts).
 * Time is frozen so a run just before midnight cannot shift a bucket.
 */
beforeEach(function () {
    Trail::auth(fn () => true);
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:30:00'));
});

afterEach(function () {
    Trail::auth(null);
    Carbon::setTestNow();
});

function seedAt(string $when, int $times = 1): void
{
    foreach (range(1, $times) as $i) {
        TrailEvent::create(['name' => 'order.placed', 'occurred_at' => Carbon::parse($when)]);
    }
}

/** @return array{0: list<string>, 1: list<int>, 2: int} [labels, counts, total] */
function liveSeriesFor(string $granularity): array
{
    $overview = new Overview;
    $overview->granularity = $granularity;

    return (new ReflectionMethod(Overview::class, 'liveSeries'))->invoke($overview);
}

it('buckets the daily series and drops what falls outside the window', function () {
    seedAt('2026-07-15 09:00:00', 3);   // today
    seedAt('2026-07-14 23:00:00', 2);   // yesterday
    seedAt('2026-07-08 10:00:00', 7);   // 7 days back: the window starts on the 9th

    [$labels, $counts, $total] = liveSeriesFor('day');

    expect($counts)->toHaveCount(7)
        ->and($labels)->toHaveCount(7)
        ->and($counts[6])->toBe(3)          // today is the last bucket
        ->and($counts[5])->toBe(2)          // yesterday
        ->and($total)->toBe(5);             // the 7 older events are excluded
});

it('buckets the hourly series over the last 12 hours', function () {
    seedAt('2026-07-15 12:05:00', 4);   // current hour
    seedAt('2026-07-15 10:59:00', 1);   // two hours back
    seedAt('2026-07-15 00:00:00', 9);   // 12 hours back: outside the window

    [$labels, $counts, $total] = liveSeriesFor('hour');

    expect($counts)->toHaveCount(12)
        ->and($counts[11])->toBe(4)
        ->and($counts[9])->toBe(1)
        ->and($total)->toBe(5)
        ->and($labels[11])->toBe('12');
});

it('rolls daily counts up into weekly buckets', function () {
    // 2026-07-15 is a Wednesday, so the current week starts on the 13th.
    seedAt('2026-07-13 08:00:00', 2);
    seedAt('2026-07-15 08:00:00', 3);
    seedAt('2026-07-06 08:00:00', 4);   // the previous week

    [$labels, $counts, $total] = liveSeriesFor('week');

    expect($counts)->toHaveCount(6)
        ->and($counts[5])->toBe(5)          // current week: 2 + 3
        ->and($counts[4])->toBe(4)          // S-1
        ->and($total)->toBe(9)
        ->and($labels[5])->toBe('Atual')
        ->and($labels[4])->toBe('S-1');
});

it('returns zeroed buckets when there are no events', function () {
    [, $counts, $total] = liveSeriesFor('day');

    expect($counts)->toBe([0, 0, 0, 0, 0, 0, 0])->and($total)->toBe(0);
});

it('counts an event landing exactly on the window boundary', function () {
    // The window starts at the beginning of the day 6 days back.
    seedAt('2026-07-09 00:00:00', 1);

    [, $counts, $total] = liveSeriesFor('day');

    expect($counts[0])->toBe(1)->and($total)->toBe(1);
});
