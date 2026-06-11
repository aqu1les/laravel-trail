<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\SubjectTimeline;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

it('loads the first actor on mount in demo mode', function () {
    Livewire::test(SubjectTimeline::class, ['demo' => true])
        ->assertSet('actorId', 'ator_8821')
        ->assertSee('Marina Rocha');
});

it('switches actor and resets type filters', function () {
    Livewire::test(SubjectTimeline::class, ['demo' => true])
        ->call('toggleType', 'order.placed')
        ->assertSet('activeTypes', ['order.placed'])
        ->call('selectActor', 'ator_3390')
        ->assertSet('actorId', 'ator_3390')
        ->assertSet('activeTypes', []);
});

it('shows the empty state when a type filter matches nothing', function () {
    Livewire::test(SubjectTimeline::class, ['demo' => true])
        ->call('toggleType', 'no.such.event')
        ->assertSee('Nenhum evento com esse filtro');
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

    Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|1')
        ->assertSet('showPageViews', false)
        ->assertSee('order.placed', false)
        ->assertDontSee('page.viewed', false)
        ->call('togglePageViews')
        ->assertSet('showPageViews', true)
        ->assertSee('page.viewed', false);
});
