<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Trail\Trail\Livewire\Paths;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

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

    $this->get('/trail/paths')->assertOk()->assertSee('Paths', false);
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
    seedPath(1, ['register' => '-2 days', 'order.placed' => '-1 day']);

    Livewire::test(Paths::class)
        ->call('setEnd', 'order.placed')
        ->assertSet('endEvent', 'order.placed')
        ->call('setStart', 'order.placed')
        ->assertSet('startEvent', 'order.placed')
        ->assertSet('endEvent', null);
});

it('resets to the first page whenever the selection changes', function () {
    seedPath(1, ['register' => '-2 days']);

    Livewire::test(Paths::class)
        ->set('page', 3)
        ->call('setEnd', 'register')
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
        ->call('setEnd', 'register')
        ->set('endEvent', 'never.happened')
        ->assertSee('Nenhum ator neste caminho');
});

it('links each row to that actor timeline', function () {
    seedPath(7, ['register' => '-2 days', 'order.placed' => '-1 day']);

    Livewire::test(Paths::class)
        ->assertViewHas('rows', fn (array $rows) => str_contains($rows[0]['href'], urlencode(User::class.'|7')));
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

    Carbon::setTestNow();
});
