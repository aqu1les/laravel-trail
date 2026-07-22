<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Trail\Trail\Livewire\Paths;
use Trail\Trail\Livewire\Sample;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));
afterEach(fn () => Carbon::setTestNow());

function seedPath(int $subjectId, array $steps): void
{
    foreach ($steps as $name => $at) {
        TrailEvent::create([
            'name' => $name,
            'subject_type' => User::class,
            'subject_id' => $subjectId,
            'occurred_at' => Carbon::parse($at),
        ]);
    }
}

it('renders as a full-page route', function () {
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day']);

    $this->get('/trail/paths')->assertOk()->assertSee('atores', false);
});

it('defaults the start event to the busiest name in the window', function () {
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day']);
    seedPath(2, ['register' => '-2 days']);

    Livewire::test(Paths::class)->assertSet('startEvent', 'register');
});

it('honours the start and end events from the URL', function () {
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day', 'invoice.paid' => '-12 hours']);
    seedPath(2, ['register' => '-2 days', 'order.placed' => '-1 day']);

    // Only subject 1 reaches invoice.paid, so only one row survives.
    Livewire::withQueryParams(['start' => 'register', 'end' => 'invoice.paid'])
        ->test(Paths::class)
        ->assertSet('startEvent', 'register')
        ->assertSet('endEvent', 'invoice.paid')
        ->assertViewHas('total', 1);
});

it('falls back to a 7 day window when since is not a known period', function () {
    // Only the URL path exercises mount(), which is where the guard lives.
    Livewire::withQueryParams(['since' => 'nonsense'])
        ->test(Paths::class)
        ->assertSet('since', '7d');
});

it('clears the end event when the start is set to the same name', function () {
    // register and order.placed both occur once, so mostFrequentName()'s
    // alphabetical tie-break would otherwise land the default start on
    // order.placed itself: pin the start so setEnd has a real terminus to set.
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day']);

    Livewire::withQueryParams(['start' => 'register'])
        ->test(Paths::class)
        ->call('setEnd', 'order.placed')
        ->assertSet('endEvent', 'order.placed')
        ->call('setStart', 'order.placed')
        ->assertSet('startEvent', 'order.placed')
        ->assertSet('endEvent', null);
});

it('clears the end event when setEnd is given the same name as start', function () {
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day']);

    Livewire::test(Paths::class)
        ->call('setStart', 'register')
        ->call('setEnd', 'register')
        ->assertSet('startEvent', 'register')
        ->assertSet('endEvent', null);
});

it('drops a same-name terminus supplied via the URL', function () {
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day']);

    Livewire::withQueryParams(['start' => 'register', 'end' => 'register'])
        ->test(Paths::class)
        ->assertSet('startEvent', 'register')
        ->assertSet('endEvent', null);
});

it('drops a terminus that matches the default start event resolved in mount', function () {
    // Only one name in the window, so mostFrequentName() resolves the default
    // start to 'register' too, and the URL-supplied end must not survive.
    seedPath(1, ['register' => '-2 days']);

    Livewire::withQueryParams(['end' => 'register'])
        ->test(Paths::class)
        ->assertSet('startEvent', 'register')
        ->assertSet('endEvent', null);
});

it('resets the page to 1 when setStart changes the selection', function () {
    foreach (range(1, 20) as $id) {
        seedPath($id, ['register' => '-'.$id.' hours', 'order.placed' => '-'.$id.' hours']);
        seedPath($id, ['login' => '-'.$id.' hours', 'order.placed' => '-'.$id.' hours']);
    }

    Livewire::withQueryParams(['start' => 'register'])
        ->test(Paths::class)
        ->call('gotoPage', 2)
        ->assertSet('page', 2)
        ->call('setStart', 'login')
        ->assertSet('page', 1);
});

it('resets the page to 1 when setEnd changes the selection', function () {
    foreach (range(1, 20) as $id) {
        seedPath($id, ['register' => '-'.$id.' hours', 'order.placed' => '-'.$id.' hours', 'invoice.paid' => '-'.$id.' hours']);
    }

    Livewire::withQueryParams(['start' => 'register'])
        ->test(Paths::class)
        ->call('gotoPage', 2)
        ->assertSet('page', 2)
        ->call('setEnd', 'order.placed')
        ->assertSet('page', 1);
});

it('resets the page to 1 when clearEnd is called', function () {
    foreach (range(1, 20) as $id) {
        seedPath($id, ['register' => '-'.$id.' hours', 'order.placed' => '-'.$id.' hours']);
    }

    Livewire::withQueryParams(['start' => 'register', 'end' => 'order.placed'])
        ->test(Paths::class)
        ->call('gotoPage', 2)
        ->assertSet('page', 2)
        ->call('clearEnd')
        ->assertSet('page', 1);
});

it('resets the page to 1 when the since window changes', function () {
    foreach (range(1, 20) as $id) {
        seedPath($id, ['register' => '-'.$id.' hours']);
    }

    Livewire::withQueryParams(['start' => 'register'])
        ->test(Paths::class)
        ->call('gotoPage', 2)
        ->assertSet('page', 2)
        ->set('since', '30d')
        ->assertSet('page', 1);
});

it('paginates at fifteen rows per page', function () {
    foreach (range(1, 20) as $id) {
        seedPath($id, ['register' => '-'.$id.' hours']);
    }

    Livewire::test(Paths::class)
        ->assertViewHas('total', 20)
        ->assertViewHas('totalPages', 2)
        ->assertViewHas('rows', fn (array $rows) => count($rows) === 15)
        ->call('gotoPage', 2)
        ->assertViewHas('rows', fn (array $rows) => count($rows) === 5);
});

it('shows the empty state when no actor completes the path', function () {
    seedPath(1, ['register' => '-2 days']);

    Livewire::test(Paths::class)
        ->set('endEvent', 'never.happened')
        ->assertSee('Nenhum ator neste caminho');
});

it('normalises an empty ?end= to null in mount', function () {
    seedPath(1, ['register' => '-2 days']);

    Livewire::withQueryParams(['end' => ''])
        ->test(Paths::class)
        ->assertSet('endEvent', null);
});

it('shows a dedicated empty state when the window has no events at all', function () {
    Livewire::test(Paths::class)
        ->assertSet('startEvent', '')
        ->assertSee('Nenhum evento nesta janela')
        ->assertDontSee('<b></b>', false);
});

it('falls back to a 7 day window when an update sets an unknown since', function () {
    Livewire::test(Paths::class)
        ->set('since', 'nonsense')
        ->assertSet('since', '7d');
});

it('renders the ellipsis chip with the elided count on a completed-and-truncated row', function () {
    seedPath(1, [
        'register' => '-2 days',
        'step.1' => '-2 days +1 minute',
        'step.2' => '-2 days +2 minutes',
        'step.3' => '-2 days +3 minutes',
        'step.4' => '-2 days +4 minutes',
        'step.5' => '-2 days +5 minutes',
        'step.6' => '-2 days +6 minutes',
        'step.7' => '-2 days +7 minutes', // 8th event, fills the default maxSteps(8)
        'step.8' => '-2 days +9 minutes', // 9th event, elided
        'invoice.paid' => '-2 days +14 minutes', // the terminus
    ]);

    Livewire::withQueryParams(['start' => 'register', 'end' => 'invoice.paid'])
        ->test(Paths::class)
        // Fixed for pt-BR pluralization: a count of exactly one elided run
        // reads "evento" (singular), not "eventos".
        ->assertSee('+1 evento')
        ->assertViewHas('rows', fn (array $rows) => $rows[0]['elided'] === 1);
});

it('renders no ellipsis chip when the terminus sits exactly at maxSteps + 1', function () {
    seedPath(1, [
        'register' => '-2 days',
        'step.1' => '-2 days +1 minute',
        'step.2' => '-2 days +2 minutes',
        'step.3' => '-2 days +3 minutes',
        'step.4' => '-2 days +4 minutes',
        'step.5' => '-2 days +5 minutes',
        'step.6' => '-2 days +6 minutes',
        'step.7' => '-2 days +7 minutes', // 8th event, fills the default maxSteps(8)
        'invoice.paid' => '-2 days +8 minutes', // the 9th event: maxSteps + 1, nothing elided
    ]);

    Livewire::withQueryParams(['start' => 'register', 'end' => 'invoice.paid'])
        ->test(Paths::class)
        ->assertDontSee('trail-step-exit', false)
        ->assertViewHas('rows', fn (array $rows) => $rows[0]['elided'] === 0 && $rows[0]['truncated'] === true);
});

it('renders no ellipsis chip on a non-truncated row', function () {
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day']);

    Livewire::withQueryParams(['start' => 'register'])
        ->test(Paths::class)
        ->assertDontSee('trail-step-exit', false)
        ->assertViewHas('rows', fn (array $rows) => $rows[0]['elided'] === 0 && $rows[0]['truncated'] === false);
});

it('renders a singular "+1 evento" chip when exactly one run is elided', function () {
    seedPath(1, [
        'register' => '-2 days',
        'step.1' => '-2 days +1 minute',
        'step.2' => '-2 days +2 minutes',
        'step.3' => '-2 days +3 minutes',
        'step.4' => '-2 days +4 minutes',
        'step.5' => '-2 days +5 minutes',
        'step.6' => '-2 days +6 minutes',
        'step.7' => '-2 days +7 minutes', // 8th event, fills the default maxSteps(8)
        'step.8' => '-2 days +9 minutes', // 9th event, elided (1 run)
        'invoice.paid' => '-2 days +14 minutes', // the terminus
    ]);

    Livewire::withQueryParams(['start' => 'register', 'end' => 'invoice.paid'])
        ->test(Paths::class)
        ->assertSee('+1 evento')
        ->assertDontSee('+1 eventos');
});

it('renders a trailing open-ended marker when a path is truncated with no terminus set', function () {
    seedPath(1, [
        'register' => '-2 days',
        'step.1' => '-2 days +1 minute',
        'step.2' => '-2 days +2 minutes',
        'step.3' => '-2 days +3 minutes',
        'step.4' => '-2 days +4 minutes',
        'step.5' => '-2 days +5 minutes',
        'step.6' => '-2 days +6 minutes',
        'step.7' => '-2 days +7 minutes', // 8th event, fills the default maxSteps(8)
        'step.8' => '-2 days +8 minutes', // 9th event, truncated away, no terminus configured
    ]);

    Livewire::withQueryParams(['start' => 'register'])
        ->test(Paths::class)
        ->assertSee('trail-step-exit', false)
        ->assertViewHas('rows', fn (array $rows) => $rows[0]['truncated'] === true && $rows[0]['completed'] === false);
});

it('renders neither marker on a short, untruncated path', function () {
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day']);

    Livewire::withQueryParams(['start' => 'register'])
        ->test(Paths::class)
        ->assertDontSee('trail-step-exit', false);
});

it('renders a safely-quoted picker entry for an event name containing an apostrophe', function () {
    // A name with an apostrophe would break `wire:click="setStart('{{ $n }}')"`
    // (a malformed JS string literal once Blade escapes the quote inside the
    // HTML attribute), leaving the menu entry dead. The data-* + $wire.setStart
    // pattern sidesteps that entirely, so the escaped name only ever needs to
    // be well-formed as an HTML attribute value, not as JS source.
    seedPath(1, ["user's.signup" => '-2 days', 'order.placed' => '-1 day']);

    Livewire::withQueryParams(['start' => 'order.placed'])
        ->test(Paths::class)
        ->assertSee('data-name="user&#039;s.signup"', false)
        ->call('setStart', "user's.signup")
        ->assertSet('startEvent', "user's.signup");
});

it('links each row to that actor timeline', function () {
    seedPath(7, ['register' => '-2 days', 'order.placed' => '-1 day']);

    Livewire::test(Paths::class)
        ->assertViewHas('rows', fn (array $rows) => str_contains($rows[0]['href'], urlencode(User::class.'|7')));
});

it('renders sample paths in demo mode without touching the database', function () {
    expect(TrailEvent::count())->toBe(0);

    Livewire::test(Paths::class, ['demo' => true])
        ->assertViewHas('rows', fn (array $rows) => count($rows) > 0 && $rows[0]['href'] === null)
        ->assertSee('register', false);

    expect(TrailEvent::count())->toBe(0);
});

it('runs zero database queries while rendering demo mode', function () {
    // TrailEvent::count() only proves nothing was WRITTEN; it cannot detect a
    // SELECT. Assert on the actual query log instead.
    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    Livewire::test(Paths::class, ['demo' => true])
        ->call('setStart', 'register')
        ->call('setEnd', 'invoice.paid')
        ->call('clearEnd');

    $queries = DB::connection()->getQueryLog();
    DB::connection()->disableQueryLog();

    expect($queries)->toBe([]);
});

it('returns demo path rows in non-increasing recency order, newest first', function () {
    // Parse each row's "há X min/h/d" label back into a common unit (minutes)
    // rather than hardcoding the internal minute list, so this genuinely
    // checks the ordering property PathQuery::cohort() also enforces.
    $minutesAgo = array_map(function (array $row) {
        $when = $row['when'];

        if ($when === 'agora' || str_ends_with($when, 's')) {
            return 0;
        }

        $number = (int) filter_var($when, FILTER_SANITIZE_NUMBER_INT);

        return match (true) {
            str_ends_with($when, 'min') => $number,
            str_ends_with($when, 'h') => $number * 60,
            str_ends_with($when, 'd') => $number * 60 * 24,
            default => $number,
        };
    }, Sample::paths());

    $sorted = $minutesAgo;
    sort($sorted);

    expect($minutesAgo)->toBe($sorted);
});

it('filters the demo rows by the selected start and end events', function () {
    Livewire::test(Paths::class, ['demo' => true])
        ->call('setStart', 'register')
        ->call('setEnd', 'invoice.paid')
        ->assertViewHas('rows', function (array $rows) {
            foreach ($rows as $row) {
                $names = array_column($row['steps'], 'name');
                if ($names[0] !== 'register' || end($names) !== 'invoice.paid') {
                    return false;
                }
            }

            return count($rows) > 0;
        });
});

it('marks demo rows completed only when a terminus was requested and matched', function () {
    // Without a terminus, no demo row is ever "completed", and elision only
    // ever applies once a terminus is set - but one sample path is
    // deliberately longer than the step cap, so it alone renders truncated.
    Livewire::test(Paths::class, ['demo' => true])
        ->call('setStart', 'register')
        ->assertViewHas('rows', function (array $rows) {
            if (! collect($rows)->every(fn (array $row) => $row['completed'] === false && $row['elided'] === 0)) {
                return false;
            }

            return collect($rows)->filter(fn (array $row) => $row['truncated'])->count() === 1;
        });

    // With a terminus set, every surviving row completed - and the one path
    // long enough to run past the cap before reaching it renders truncated
    // with exactly one elided run; every other completing path stays whole.
    Livewire::test(Paths::class, ['demo' => true])
        ->call('setStart', 'register')
        ->call('setEnd', 'invoice.paid')
        ->assertViewHas('rows', function (array $rows) {
            if (count($rows) === 0 || ! collect($rows)->every(fn (array $row) => $row['completed'] === true)) {
                return false;
            }

            $truncated = collect($rows)->filter(fn (array $row) => $row['truncated']);

            return $truncated->count() === 1 && $truncated->first()['elided'] === 1;
        });
});

it('shows the open-ended marker for the one demo path longer than the step cap', function () {
    Livewire::test(Paths::class, ['demo' => true])
        ->call('setStart', 'register')
        ->assertSee('mais eventos', false);
});

it('shows the elided-count chip when a demo terminus is found past the step cap', function () {
    Livewire::test(Paths::class, ['demo' => true])
        ->call('setStart', 'register')
        ->call('setEnd', 'invoice.paid')
        ->assertSee('+1 evento', false);
});

it('labels the gap between steps in compact units', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-02 12:00:00'));

    seedPath(1, [
        'register' => '2026-01-01 10:00:00',
        'number_verified' => '2026-01-01 10:00:38',
        'order.placed' => '2026-01-01 10:05:00',
        'invoice.paid' => '2026-01-01 11:05:00',
    ]);

    // Every seeded name occurs once, so mostFrequentName() would tie-break
    // alphabetically to invoice.paid rather than register: pin the start
    // explicitly so this test exercises gap labelling, not default selection.
    Livewire::withQueryParams(['since' => '30d', 'start' => 'register'])
        ->test(Paths::class)
        ->assertViewHas('rows', function (array $rows) {
            return array_column($rows[0]['steps'], 'gap') === [null, '+38s', '+4min', '+1h'];
        });
});
