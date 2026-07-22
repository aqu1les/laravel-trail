<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Queries\PathQuery;
use Trail\Trail\Tests\Fixtures\User;

/** Seed one event for a subject at an exact instant. */
function seedPathEvent(int $subjectId, string $name, string $at): TrailEvent
{
    return TrailEvent::create([
        'name' => $name,
        'subject_type' => User::class,
        'subject_id' => $subjectId,
        'occurred_at' => Carbon::parse($at),
    ]);
}

/** The window every test in this file reads. */
function pathQuery(): PathQuery
{
    return PathQuery::inWindow(now()->subDays(7));
}

it('reconstructs a path in occurred_at order, with the gap between steps', function () {
    // Seeded out of order on purpose: the engine must sort, not trust insertion.
    seedPathEvent(1, 'order.placed', '-2 days +5 minutes');
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'number_verified', '-2 days +30 seconds');

    $result = pathQuery()->startingAt('register')->sequences();

    expect($result['total'])->toBe(1)
        ->and($result['truncated'])->toBeFalse();

    $steps = $result['rows'][0]['steps'];

    expect(array_column($steps, 'name'))->toBe(['register', 'number_verified', 'order.placed'])
        ->and(array_column($steps, 'gap_seconds'))->toBe([null, 30, 270])
        ->and((string) $result['rows'][0]['key'])->toBe(User::class.'|1')
        ->and($result['rows'][0]['completed'])->toBeFalse()
        ->and($result['rows'][0]['truncated'])->toBeFalse();
});

it('keeps only subjects that fired the start event, and drops what preceded it', function () {
    // Subject 1 qualifies, but its session.started predates the anchor.
    seedPathEvent(1, 'session.started', '-3 days');
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'order.placed', '-1 day');

    // Subject 2 never registered, so it is not in the cohort at all.
    seedPathEvent(2, 'session.started', '-2 days');
    seedPathEvent(2, 'order.placed', '-1 day');

    $result = pathQuery()->startingAt('register')->sequences();

    expect($result['total'])->toBe(1)
        ->and(array_column($result['rows'][0]['steps'], 'name'))->toBe(['register', 'order.placed']);
});

it('collapses identical consecutive events, and leaves them when asked not to', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'cart.updated', '-2 days +1 minute');
    seedPathEvent(1, 'cart.updated', '-2 days +2 minutes');
    seedPathEvent(1, 'cart.updated', '-2 days +3 minutes');
    seedPathEvent(1, 'order.placed', '-2 days +4 minutes');

    $collapsed = pathQuery()->startingAt('register')->sequences();
    expect(array_column($collapsed['rows'][0]['steps'], 'name'))
        ->toBe(['register', 'cart.updated', 'order.placed']);

    $raw = pathQuery()->startingAt('register')->collapseRepeats(false)->sequences();
    expect(array_column($raw['rows'][0]['steps'], 'name'))
        ->toBe(['register', 'cart.updated', 'cart.updated', 'cart.updated', 'order.placed']);
});

it('truncates a long path at maxSteps and flags the row', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'number_verified', '-2 days +1 minute');
    seedPathEvent(1, 'cart.updated', '-2 days +2 minutes');
    seedPathEvent(1, 'order.placed', '-2 days +3 minutes');

    $result = pathQuery()->startingAt('register')->maxSteps(2)->sequences();

    expect(array_column($result['rows'][0]['steps'], 'name'))->toBe(['register', 'number_verified'])
        ->and($result['rows'][0]['truncated'])->toBeTrue();
});

it('cuts at the end event and discards subjects that never reach it', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'order.placed', '-2 days +1 minute');
    seedPathEvent(1, 'invoice.paid', '-2 days +2 minutes');
    seedPathEvent(1, 'cart.updated', '-2 days +3 minutes'); // after the end, must be cut

    seedPathEvent(2, 'register', '-2 days');
    seedPathEvent(2, 'cart.updated', '-2 days +1 minute'); // never pays

    $result = pathQuery()->startingAt('register')->endingAt('invoice.paid')->sequences();

    expect($result['total'])->toBe(1)
        ->and((string) $result['rows'][0]['key'])->toBe(User::class.'|1')
        ->and(array_column($result['rows'][0]['steps'], 'name'))
        ->toBe(['register', 'order.placed', 'invoice.paid'])
        ->and($result['rows'][0]['completed'])->toBeTrue();
});

it('ignores page views and events outside the window', function () {
    config()->set('trail.auto_track.event_name', 'page.viewed');

    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'page.viewed', '-2 days +1 minute');
    seedPathEvent(1, 'order.placed', '-2 days +2 minutes');
    seedPathEvent(1, 'invoice.paid', '-30 days'); // outside the 7 day window

    $result = pathQuery()->startingAt('register')->sequences();

    expect(array_column($result['rows'][0]['steps'], 'name'))->toBe(['register', 'order.placed']);
});

it('orders rows by how recently the subject started, newest first', function () {
    seedPathEvent(1, 'register', '-5 days');
    seedPathEvent(2, 'register', '-1 day');
    seedPathEvent(3, 'register', '-3 days');

    $result = pathQuery()->startingAt('register')->sequences();

    expect(array_map(fn (array $row) => $row['key']->id, $result['rows']))->toBe(['2', '3', '1']);
});

it('offers the window vocabulary and its most frequent name', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(2, 'register', '-2 days');
    seedPathEvent(1, 'order.placed', '-1 day');

    expect(pathQuery()->namesInWindow())->toBe(['order.placed', 'register'])
        ->and(pathQuery()->mostFrequentName())->toBe('register');
});

it('returns nothing when no start event is set or the window is empty', function () {
    seedPathEvent(1, 'register', '-2 days');

    expect(pathQuery()->sequences())->toBe(['rows' => [], 'total' => 0, 'truncated' => false])
        ->and(pathQuery()->startingAt('never.happened')->sequences()['total'])->toBe(0)
        ->and(PathQuery::inWindow(now()->subDays(7))->mostFrequentName())->toBe('register');
});

it('caps the cohort and flags the result as truncated', function () {
    // One subject over the cap, bulk-inserted so the assertion stays about the
    // bound rather than about insert speed.
    $at = now()->subDays(2);
    $rows = [];

    foreach (range(1, PathQuery::SUBJECT_CAP + 1) as $id) {
        $rows[] = [
            'uuid' => (string) Str::uuid(),
            'name' => 'register',
            'subject_type' => User::class,
            'subject_id' => $id,
            'occurred_at' => $at,
        ];
    }

    TrailEvent::insert($rows);

    $result = pathQuery()->startingAt('register')->sequences();

    expect($result['total'])->toBe(PathQuery::SUBJECT_CAP)
        ->and($result['truncated'])->toBeTrue();
});

it('does not flag truncation for a cohort under the cap', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(2, 'register', '-2 days');

    expect(pathQuery()->startingAt('register')->sequences()['truncated'])->toBeFalse();
});
