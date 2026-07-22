<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Queries\PathQuery;
use Trail\Trail\Tests\Fixtures\Team;
use Trail\Trail\Tests\Fixtures\User;

/** Seed one event for a User subject at an exact instant. */
function seedPathEvent(int $subjectId, string $name, string $at): TrailEvent
{
    return seedPathEventFor(User::class, $subjectId, $name, $at);
}

/** Seed one event for an arbitrary subject type at an exact instant. */
function seedPathEventFor(string $subjectType, int|string $subjectId, string $name, string $at): TrailEvent
{
    return TrailEvent::create([
        'name' => $name,
        'subject_type' => $subjectType,
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
    $last = seedPathEvent(1, 'order.placed', '-2 days +5 minutes');
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
        ->and($result['rows'][0]['truncated'])->toBeFalse()
        ->and($result['rows'][0]['last_at']->eq($last->occurred_at))->toBeTrue();
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
    $steps = $collapsed['rows'][0]['steps'];

    expect(array_column($steps, 'name'))
        ->toBe(['register', 'cart.updated', 'order.placed'])
        // The gap after a collapsed run is measured from the run's FIRST
        // occurrence, not its last: gaps sum back to the total path duration
        // (60 + 180 = 240 seconds from register to order.placed) rather than
        // hiding the 120 seconds the run itself spanned.
        ->and(array_column($steps, 'gap_seconds'))->toBe([null, 60, 180]);

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

it('treats an empty end event the same as none at all', function () {
    // endingAt('') must normalise to unset, the same as startingAt('') does -
    // otherwise every subject is compared against a terminus that can never
    // match and the whole screen empties out.
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'order.placed', '-2 days +1 minute');

    $result = pathQuery()->startingAt('register')->endingAt('')->sequences();

    expect($result['total'])->toBe(1)
        ->and($result['rows'][0]['completed'])->toBeFalse()
        ->and(array_column($result['rows'][0]['steps'], 'name'))->toBe(['register', 'order.placed']);
});

it('anchors on the LAST occurrence of the start event, not the first', function () {
    // Subject registers twice. The first journey (session.started) is stale;
    // the second (cart.updated -> order.placed) is what actually happened
    // after the subject's most recent start, which is what cohort()'s
    // max(occurred_at) desc ordering assumes the row represents.
    seedPathEvent(1, 'register', '-6 days');
    seedPathEvent(1, 'session.started', '-6 days +1 minute');

    seedPathEvent(1, 'register', '-1 day');
    seedPathEvent(1, 'cart.updated', '-1 day +1 minute');
    seedPathEvent(1, 'order.placed', '-1 day +2 minutes');

    $result = pathQuery()->startingAt('register')->sequences();

    expect($result['total'])->toBe(1)
        ->and(array_column($result['rows'][0]['steps'], 'name'))
        ->toBe(['register', 'cart.updated', 'order.placed']);
});

it('walks the anchor back over a repeated start event when collapsing repeats', function () {
    // register fires twice in a row, then order.placed. With collapseRepeats
    // on, the run's dating rule says the run is dated at its FIRST event - so
    // the anchor must walk back to the first register, making this a 120
    // second journey rather than a 60 second one that started late.
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'register', '-2 days +60 seconds');
    $last = seedPathEvent(1, 'order.placed', '-2 days +120 seconds');

    $result = pathQuery()->startingAt('register')->sequences();

    $steps = $result['rows'][0]['steps'];

    expect(array_column($steps, 'name'))->toBe(['register', 'order.placed'])
        ->and(array_column($steps, 'gap_seconds'))->toBe([null, 120])
        ->and($result['rows'][0]['last_at']->eq($last->occurred_at))->toBeTrue();
});

it('does not walk back past a separate, non-consecutive earlier start occurrence', function () {
    // The subject fires register, then other events, then register again
    // later. The walk-back must only span an unbroken run of the same event
    // immediately before the anchor - it must not undo the last-occurrence
    // decision and reach back to the first, separate register.
    seedPathEvent(1, 'register', '-6 days');
    seedPathEvent(1, 'session.started', '-6 days +1 minute');

    seedPathEvent(1, 'register', '-1 day');
    seedPathEvent(1, 'cart.updated', '-1 day +1 minute');

    $result = pathQuery()->startingAt('register')->sequences();

    expect(array_column($result['rows'][0]['steps'], 'name'))
        ->toBe(['register', 'cart.updated']);
});

it('keeps scanning past maxSteps for the end event, and renders it as the final step', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'step.1', '-2 days +1 minute');
    seedPathEvent(1, 'step.2', '-2 days +2 minutes');
    seedPathEvent(1, 'step.3', '-2 days +3 minutes');
    seedPathEvent(1, 'step.4', '-2 days +4 minutes');
    seedPathEvent(1, 'step.5', '-2 days +5 minutes');
    seedPathEvent(1, 'step.6', '-2 days +6 minutes');
    seedPathEvent(1, 'step.7', '-2 days +7 minutes'); // 8th event, fills maxSteps
    seedPathEvent(1, 'step.8', '-2 days +9 minutes'); // 9th event, past maxSteps
    seedPathEvent(1, 'invoice.paid', '-2 days +14 minutes'); // 10th event, the terminus

    $result = pathQuery()->startingAt('register')->maxSteps(8)->endingAt('invoice.paid')->sequences();

    expect($result['total'])->toBe(1);

    $row = $result['rows'][0];
    $names = array_column($row['steps'], 'name');

    expect($names)->toHaveCount(9)
        ->and($names[8])->toBe('invoice.paid')
        ->and($row['completed'])->toBeTrue()
        ->and($row['truncated'])->toBeTrue()
        // The terminus's gap is measured from step.8, the event that actually
        // preceded it in the source stream (5 minutes later), not from the
        // last rendered step, step.7, which would read 7 minutes.
        ->and($row['steps'][8]['gap_seconds'])->toBe(300);
});

it('flags no elision when the terminus sits exactly at maxSteps + 1', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'step.1', '-2 days +1 minute');
    seedPathEvent(1, 'checkout', '-2 days +2 minutes'); // the 3rd event, maxSteps(2) + 1

    $result = pathQuery()->startingAt('register')->maxSteps(2)->endingAt('checkout')->sequences();

    expect($result['rows'][0]['truncated'])->toBeTrue()
        ->and($result['rows'][0]['completed'])->toBeTrue()
        ->and($result['rows'][0]['elided'])->toBe(0);
});

it('dates the terminus gap from the first event of a collapsed run, not the last', function () {
    // A, B(t1), B(t2), B(t3), checkout(t4) with maxSteps(2): steps render as
    // [A, B], the three B's collapse into one run so nothing is elided, and
    // checkout is appended past the cap. Its gap must be measured from t1 (B's
    // first occurrence), the same convention every other gap in this method
    // follows - not from t3 (the raw last-scanned event), which is only
    // correct when an ellipsis chip actually sits between the run and the
    // terminus (i.e. when something was elided).
    seedPathEvent(1, 'A', '-2 days');
    seedPathEvent(1, 'B', '-2 days +1 minute');
    seedPathEvent(1, 'B', '-2 days +2 minutes');
    seedPathEvent(1, 'B', '-2 days +3 minutes');
    seedPathEvent(1, 'checkout', '-2 days +4 minutes');

    $result = pathQuery()->startingAt('A')->maxSteps(2)->endingAt('checkout')->sequences();

    $row = $result['rows'][0];

    expect(array_column($row['steps'], 'name'))->toBe(['A', 'B', 'checkout'])
        ->and($row['elided'])->toBe(0)
        ->and($row['steps'][2]['gap_seconds'])->toBe(180); // t4 - t1, not t4 - t3 (which would be 60)
});

it('counts the elided events when the terminus sits further past maxSteps', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'step.1', '-2 days +1 minute'); // fills maxSteps(2)
    seedPathEvent(1, 'step.2', '-2 days +2 minutes'); // elided
    seedPathEvent(1, 'step.3', '-2 days +3 minutes'); // elided
    seedPathEvent(1, 'step.4', '-2 days +4 minutes'); // elided
    seedPathEvent(1, 'step.5', '-2 days +5 minutes'); // elided
    seedPathEvent(1, 'checkout', '-2 days +6 minutes'); // the terminus, maxSteps(2) + 5

    $result = pathQuery()->startingAt('register')->maxSteps(2)->endingAt('checkout')->sequences();

    expect($result['rows'][0]['truncated'])->toBeTrue()
        ->and($result['rows'][0]['completed'])->toBeTrue()
        ->and($result['rows'][0]['elided'])->toBe(4);
});

it('flags no elision on an untruncated path', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'order.placed', '-2 days +1 minute');

    $result = pathQuery()->startingAt('register')->sequences();

    expect($result['rows'][0]['truncated'])->toBeFalse()
        ->and($result['rows'][0]['elided'])->toBe(0);
});

it('drops a subject that never reaches the end event, even after scanning past maxSteps', function () {
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'step.1', '-2 days +1 minute');
    seedPathEvent(1, 'step.2', '-2 days +2 minutes');
    seedPathEvent(1, 'step.3', '-2 days +3 minutes');
    seedPathEvent(1, 'step.4', '-2 days +4 minutes');
    seedPathEvent(1, 'step.5', '-2 days +5 minutes');
    seedPathEvent(1, 'step.6', '-2 days +6 minutes');
    seedPathEvent(1, 'step.7', '-2 days +7 minutes');
    seedPathEvent(1, 'step.8', '-2 days +8 minutes'); // never converts

    $result = pathQuery()->startingAt('register')->maxSteps(8)->endingAt('invoice.paid')->sequences();

    expect($result['total'])->toBe(0);
});

it('groups events by subject_type so a second morph type does not cross-match ids', function () {
    // Both a User and a Team share subject_id 1. This does not guard against a
    // degenerate whereIn('subject_id', ...) across types: eventsFor() groups
    // rows by each row's own stored subject_type, so ids would still land in
    // the correct group even then. What it DOES guard is that the per-type
    // constraint is built with orWhere, not where: an AND across two
    // different subject_type clauses can never match a row, so a regression
    // to `where` would return zero rows and silently drop both subjects.
    seedPathEventFor(User::class, 1, 'register', '-2 days');
    seedPathEventFor(User::class, 1, 'order.placed', '-2 days +1 minute');

    seedPathEventFor(Team::class, 1, 'register', '-2 days');
    seedPathEventFor(Team::class, 1, 'invoice.paid', '-2 days +1 minute');

    $result = pathQuery()->startingAt('register')->sequences();

    expect($result['total'])->toBe(2);

    $byType = [];

    foreach ($result['rows'] as $row) {
        $byType[$row['key']->type] = array_column($row['steps'], 'name');
    }

    expect($byType[User::class])->toBe(['register', 'order.placed'])
        ->and($byType[Team::class])->toBe(['register', 'invoice.paid']);
});

it('ignores page views inside the window', function () {
    config()->set('trail.auto_track.event_name', 'page.viewed');

    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'page.viewed', '-2 days +1 minute');
    seedPathEvent(1, 'order.placed', '-2 days +2 minutes');

    $result = pathQuery()->startingAt('register')->sequences();

    expect(array_column($result['rows'][0]['steps'], 'name'))->toBe(['register', 'order.placed']);
});

it('excludes a subject whose start event happened entirely outside the window', function () {
    // window() only ever imposes a floor (occurred_at >= since); there is no
    // ceiling. So an out-of-window event can never sort chronologically after
    // an in-window anchor - anything before "since" necessarily sorts before
    // anything at or after it, and the anchor (whichever register occurrence
    // wins under the "last occurrence" rule) is always inside the window.
    // Consequently the one place the window bound is actually load-bearing,
    // rather than redundant with the anchor cutting everything before it, is
    // cohort selection: a subject whose ONLY start event is outside the
    // window must not enter the cohort at all.
    seedPathEvent(1, 'register', '-2 days');
    seedPathEvent(1, 'order.placed', '-2 days +1 minute');

    seedPathEvent(2, 'register', '-30 days'); // outside the 7 day window entirely
    seedPathEvent(2, 'order.placed', '-30 days +1 minute');

    $result = pathQuery()->startingAt('register')->sequences();

    expect($result['total'])->toBe(1)
        ->and((string) $result['rows'][0]['key'])->toBe(User::class.'|1');
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

it('breaks a tie in mostFrequentName by name, alphabetically first', function () {
    seedPathEvent(1, 'zebra.event', '-2 days');
    seedPathEvent(2, 'zebra.event', '-2 days');
    seedPathEvent(1, 'apple.event', '-2 days');
    seedPathEvent(2, 'apple.event', '-2 days');

    expect(pathQuery()->mostFrequentName())->toBe('apple.event');
});

it('returns nothing when no start event is set', function () {
    seedPathEvent(1, 'register', '-2 days');

    expect(pathQuery()->sequences())->toBe(['rows' => [], 'total' => 0, 'truncated' => false]);
});

it('returns nothing when the configured start event never happened in the window', function () {
    seedPathEvent(1, 'register', '-2 days');

    expect(pathQuery()->startingAt('never.happened')->sequences()['total'])->toBe(0);
});

it('returns nothing when the window is empty', function () {
    expect(pathQuery()->startingAt('register')->sequences())
        ->toBe(['rows' => [], 'total' => 0, 'truncated' => false]);
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

it('drops a subject whose terminus sits beyond SCAN_CAP in-window events', function () {
    // Bulk-inserted so the assertion stays about the bound rather than about
    // insert speed, same style as the SUBJECT_CAP tests above.
    seedPathEvent(1, 'register', '-2 days');

    $at = now()->subDays(2)->addSecond();
    $rows = [];

    foreach (range(1, PathQuery::SCAN_CAP + 5) as $offset) {
        $rows[] = [
            'uuid' => (string) Str::uuid(),
            'name' => 'noise.event',
            'subject_type' => User::class,
            'subject_id' => 1,
            'occurred_at' => $at->copy()->addSeconds($offset),
        ];
    }

    TrailEvent::insert($rows);

    // The terminus sits past the SCAN_CAP-th in-window event scanned after
    // the anchor, so assemble() never reaches it and the subject is dropped.
    seedPathEvent(1, 'invoice.paid', $at->copy()->addSeconds(PathQuery::SCAN_CAP + 10)->toIso8601String());

    $result = pathQuery()->startingAt('register')->endingAt('invoice.paid')->sequences();

    expect($result['total'])->toBe(0);
});

it('does not flag truncation for a cohort at exactly the cap', function () {
    // Regression for reading count($cohort) >= SUBJECT_CAP: a cohort with
    // exactly SUBJECT_CAP subjects and not one more is not truncated.
    $at = now()->subDays(2);
    $rows = [];

    foreach (range(1, PathQuery::SUBJECT_CAP) as $id) {
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
        ->and($result['truncated'])->toBeFalse();
});
