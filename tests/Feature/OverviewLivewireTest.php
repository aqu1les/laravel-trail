<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

it('switches the chart granularity in demo mode', function () {
    Livewire::test(Overview::class, ['demo' => true])
        ->assertSet('granularity', 'Dia')
        ->assertSee('1.24M')
        ->set('granularity', 'Hora')
        ->assertSee('142k');
});

it('builds sparkline geometry', function () {
    expect(Overview::spark([1, 5, 3])['line'])->toContain(',');
});
