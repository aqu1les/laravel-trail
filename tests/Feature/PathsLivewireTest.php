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
        ->call('setEnd', 'register')
        ->set('endEvent', 'never.happened')
        ->assertSee('Nenhum ator neste caminho');
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
});
