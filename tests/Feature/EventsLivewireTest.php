<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

it('renders as a full-page route on real data', function () {
    $this->get('/trail/events')->assertOk()->assertSee('Events', false);
});

it('seeds a demo stream on mount', function () {
    Livewire::test(Events::class, ['demo' => true])->assertCount('events', 50);
});

it('toggles the live stream', function () {
    Livewire::test(Events::class, ['demo' => true])
        ->assertSet('live', true)
        ->call('toggleLive')
        ->assertSet('live', false);
});

it('filters by event name', function () {
    Livewire::test(Events::class, ['demo' => true])
        ->call('toggleEvent', 'order.placed')
        ->assertSet('eventFilter', ['order.placed'])
        ->call('toggleEvent', 'order.placed')
        ->assertSet('eventFilter', []);
});

it('shows the empty state when nothing matches', function () {
    Livewire::test(Events::class, ['demo' => true])
        ->set('search', 'zzz-no-such-event')
        ->assertSee('Nenhum evento corresponde');
});

it('opens the drawer for a selected event', function () {
    Livewire::test(Events::class, ['demo' => true])
        ->call('select', 1)
        ->assertSet('selectedId', 1)
        ->assertDispatched('drawer-open');
});

it('hides page-view events by default and reveals them on toggle', function () {
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => User::class,
        'subject_id' => 1,
        'occurred_at' => now(),
    ]);
    TrailEvent::create([
        'name' => 'page.viewed',
        'subject_type' => User::class,
        'subject_id' => 1,
        'occurred_at' => now(),
    ]);

    Livewire::test(Events::class)
        ->assertSet('showPageViews', false)
        ->assertSee('order.placed', false)
        ->assertDontSee('page.viewed', false)
        ->call('togglePageViews')
        ->assertSet('showPageViews', true)
        ->assertSee('page.viewed', false);
});

it('windows the real stream by the selected period', function () {
    TrailEvent::create([
        'name' => 'event.today',
        'occurred_at' => now()->subHours(2),
    ]);
    TrailEvent::create([
        'name' => 'event.three.days.ago',
        'occurred_at' => now()->subDays(3),
    ]);
    TrailEvent::create([
        'name' => 'event.ten.days.ago',
        'occurred_at' => now()->subDays(10),
    ]);

    Livewire::test(Events::class)
        ->assertSet('since', '7d')
        ->assertSee('event.today', false)
        ->assertSee('event.three.days.ago', false)
        ->assertDontSee('event.ten.days.ago', false)
        ->set('since', 'today')
        ->assertSee('event.today', false)
        ->assertDontSee('event.three.days.ago', false)
        ->set('since', '30d')
        ->assertSee('event.today', false)
        ->assertSee('event.ten.days.ago', false);
});

it('hydrates the filters from the query string', function () {
    TrailEvent::create(['name' => 'event.today', 'occurred_at' => now()->subHours(2)]);
    TrailEvent::create(['name' => 'event.ten.days.ago', 'occurred_at' => now()->subDays(10)]);

    // 'ten' matches only the older event, which the default 7d window would hide.
    Livewire::withQueryParams(['since' => '30d', 'q' => 'ten', 'page_views' => true])
        ->test(Events::class)
        ->assertSet('since', '30d')
        ->assertSet('search', 'ten')
        ->assertSet('showPageViews', true)
        ->assertSee('event.ten.days.ago', false)
        ->assertSee('há 10d', false)
        ->assertDontSee('há 2 h', false);
});

it('hydrates the event-name filter from a query-string array', function () {
    TrailEvent::create(['name' => 'order.placed', 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'cart.updated', 'occurred_at' => now()]);

    Livewire::withQueryParams(['events' => ['order.placed']])
        ->test(Events::class)
        ->assertSet('eventFilter', ['order.placed'])
        ->assertSee('order.placed', false);
});

it('hydrates the actor filter from the query string', function () {
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => User::class,
        'subject_id' => 1,
        'occurred_at' => now(),
    ]);

    Livewire::withQueryParams(['actor' => User::class.'|1'])
        ->test(Events::class)
        ->assertSet('actorFilter', User::class.'|1')
        ->assertSee('order.placed', false);
});

it('falls back to the 7d window when the period token is unknown', function () {
    TrailEvent::create(['name' => 'event.three.days.ago', 'occurred_at' => now()->subDays(3)]);
    TrailEvent::create(['name' => 'event.ten.days.ago', 'occurred_at' => now()->subDays(10)]);

    Livewire::withQueryParams(['since' => 'xyz'])
        ->test(Events::class)
        ->assertSet('since', '7d')
        ->assertSee('event.three.days.ago', false)
        ->assertDontSee('event.ten.days.ago', false);
});

/**
 * Fill the top of the window with rows that survive the default filters, so a
 * test only passes if the filter under test reaches past the 200-row cap.
 */
function bury(int $rows = 200): void
{
    for ($i = 0; $i < $rows; $i++) {
        TrailEvent::create(['name' => 'order.placed', 'occurred_at' => now()->subMinutes($i)]);
    }
}

it('searches by event name beyond the newest 200 rows of the window', function () {
    bury();
    TrailEvent::create(['name' => 'user.registered', 'occurred_at' => now()->subDays(10)]);

    $visible = Livewire::withQueryParams(['since' => '30d', 'q' => 'user.registered'])
        ->test(Events::class)
        ->viewData('visible');

    expect($visible)->toHaveCount(1)
        ->and($visible[0]['name'])->toBe('user.registered');
});

it('filters by event name beyond the newest 200 rows of the window', function () {
    bury();
    TrailEvent::create(['name' => 'user.registered', 'occurred_at' => now()->subDays(10)]);

    $visible = Livewire::withQueryParams(['since' => '30d', 'events' => ['user.registered']])
        ->test(Events::class)
        ->viewData('visible');

    expect($visible)->toHaveCount(1)
        ->and($visible[0]['name'])->toBe('user.registered');
});

it('filters by actor beyond the newest 200 rows of the window', function () {
    $user = User::create(['name' => 'Doralice Marques', 'email' => 'doralice@example.com']);
    bury();
    TrailEvent::create([
        'name' => 'user.registered',
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'occurred_at' => now()->subDays(10),
    ]);

    $visible = Livewire::withQueryParams(['since' => '30d', 'actor' => User::class.'|'.$user->id])
        ->test(Events::class)
        ->viewData('visible');

    expect($visible)->toHaveCount(1)
        ->and($visible[0]['name'])->toBe('user.registered');
});

it('searches the actor identity beyond the newest 200 rows of the window', function () {
    $user = User::create(['name' => 'Doralice Marques', 'email' => 'doralice@example.com']);
    bury();
    TrailEvent::create([
        'name' => 'user.registered',
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'occurred_at' => now()->subDays(10),
    ]);

    // 'Doralice' lives on the user row, not on the event, so this only matches
    // if the actor lookup feeds the SQL filter.
    $visible = Livewire::withQueryParams(['since' => '30d', 'q' => 'Doralice'])
        ->test(Events::class)
        ->viewData('visible');

    expect($visible)->toHaveCount(1)
        ->and($visible[0]['actor']['name'])->toBe('Doralice Marques');
});

it('hides page views beyond the newest 200 rows of the window', function () {
    for ($i = 0; $i < 200; $i++) {
        TrailEvent::create(['name' => 'page.viewed', 'occurred_at' => now()->subMinutes($i)]);
    }
    TrailEvent::create(['name' => 'user.registered', 'occurred_at' => now()->subDays(10)]);

    $visible = Livewire::withQueryParams(['since' => '30d'])
        ->test(Events::class)
        ->viewData('visible');

    expect($visible)->toHaveCount(1)
        ->and($visible[0]['name'])->toBe('user.registered');
});

it('offers every event name in the window in the filter menu', function () {
    bury();
    TrailEvent::create(['name' => 'user.registered', 'occurred_at' => now()->subDays(10)]);

    // The menu keeps the older name even while the search empties the table.
    $names = Livewire::withQueryParams(['since' => '30d', 'q' => 'zzz-no-such-event'])
        ->test(Events::class)
        ->assertSee('Nenhum evento corresponde')
        ->viewData('names');

    expect($names)->toBe(['order.placed', 'user.registered']);
});

it('offers actors from the whole window in the filter menu', function () {
    $user = User::create(['name' => 'Doralice Marques', 'email' => 'doralice@example.com']);
    bury();
    TrailEvent::create([
        'name' => 'user.registered',
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'occurred_at' => now()->subDays(10),
    ]);

    $actors = Livewire::withQueryParams(['since' => '30d'])
        ->test(Events::class)
        ->viewData('actors');

    expect(collect($actors)->pluck('name'))->toContain('Doralice Marques');
});

it('searches case-insensitively', function () {
    $user = User::create(['name' => 'Doralice Marques', 'email' => 'doralice@example.com']);
    TrailEvent::create([
        'name' => 'User.Registered',
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'occurred_at' => now(),
    ]);

    foreach (['user.registered', 'USER.REGISTERED', 'doralice', 'DORALICE'] as $term) {
        $visible = Livewire::withQueryParams(['q' => $term])
            ->test(Events::class)
            ->viewData('visible');

        expect($visible)->toHaveCount(1, "term: {$term}");
    }
});

it('keeps the period window while filtering', function () {
    bury();
    TrailEvent::create(['name' => 'user.registered', 'occurred_at' => now()->subDays(10)]);

    // Same filter, narrower window: the 10-day-old match must drop out.
    $visible = Livewire::withQueryParams(['since' => '7d', 'q' => 'user.registered'])
        ->test(Events::class)
        ->viewData('visible');

    expect($visible)->toBe([]);
});

it('only refreshes the real stream when new events arrive', function () {
    $component = Livewire::test(Events::class)->assertSet('lastSeenId', 0);

    // Idle tick: nothing new, lastSeenId stays put.
    $component->call('tick')->assertSet('lastSeenId', 0);

    // A new event lands: the tick advances and the table re-renders.
    $event = TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => User::class,
        'subject_id' => 1,
        'occurred_at' => now(),
    ]);

    $component->call('tick')
        ->assertSet('lastSeenId', $event->id)
        ->assertSee('order.placed', false);
});

it('keeps the open drawer populated when a filter excludes the selected row', function () {
    $event = TrailEvent::create(['name' => 'order.placed', 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'cart.updated', 'occurred_at' => now()]);

    // Open the drawer, then narrow the table so the selected row drops out of it.
    // The drawer stays open client-side, so it must still have its event.
    $component = Livewire::test(Events::class)
        ->call('select', $event->id)
        ->set('search', 'cart.updated');

    expect($component->viewData('visible'))->toHaveCount(1)
        ->and($component->viewData('selected'))->not->toBeNull()
        ->and($component->viewData('selected')['name'])->toBe('order.placed');
});
