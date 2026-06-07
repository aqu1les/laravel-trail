<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\SubjectTimeline;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

it('loads the first actor on mount', function () {
    Livewire::test(SubjectTimeline::class)
        ->assertSet('actorId', 'ator_8821')
        ->assertSee('Marina Rocha');
});

it('switches actor and resets type filters', function () {
    Livewire::test(SubjectTimeline::class)
        ->call('toggleType', 'order.placed')
        ->assertSet('activeTypes', ['order.placed'])
        ->call('selectActor', 'ator_3390')
        ->assertSet('actorId', 'ator_3390')
        ->assertSet('activeTypes', []);
});

it('shows the empty state when a type filter matches nothing', function () {
    Livewire::test(SubjectTimeline::class)
        ->call('toggleType', 'no.such.event')
        ->assertSee('Nenhum evento com esse filtro');
});
