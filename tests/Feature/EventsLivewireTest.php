<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

it('renders as a full-page route', function () {
    $this->get('/trail/events')->assertOk()->assertSee('Events', false);
});

it('seeds a stream on mount', function () {
    Livewire::test(Events::class)->assertCount('events', 50);
});

it('toggles the live stream', function () {
    Livewire::test(Events::class)
        ->assertSet('live', true)
        ->call('toggleLive')
        ->assertSet('live', false);
});

it('filters by event name', function () {
    Livewire::test(Events::class)
        ->call('toggleEvent', 'order.placed')
        ->assertSet('eventFilter', ['order.placed'])
        ->call('toggleEvent', 'order.placed')
        ->assertSet('eventFilter', []);
});

it('shows the empty state when nothing matches', function () {
    Livewire::test(Events::class)
        ->set('search', 'zzz-no-such-event')
        ->assertSee('Nenhum evento corresponde');
});

it('opens the drawer for a selected event', function () {
    Livewire::test(Events::class)
        ->call('select', 1)
        ->assertSet('selectedId', 1)
        ->assertDispatched('drawer-open');
});
