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
