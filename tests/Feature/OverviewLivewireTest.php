<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
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

it('aggregates the real series across granularities via SQL', function () {
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => User::class,
        'subject_id' => 1,
        'occurred_at' => now(),
    ]);

    Livewire::test(Overview::class) // real mode
        ->assertSee('Eventos ao longo do tempo', false)
        ->set('granularity', 'Hora')->assertSee('Eventos ao longo do tempo', false)
        ->set('granularity', 'Semana')->assertSee('Eventos ao longo do tempo', false);
});

it('serves the series and top events from rollups when available', function () {
    // Only a pre-computed daily rollup exists - no raw events with this name.
    \Trail\Trail\Models\TrailAggregate::create([
        'period' => 'day',
        'bucket' => now()->startOfDay(),
        'name' => 'rollup.only',
        'count' => 123,
        'unique_subjects' => 5,
        'sum_value' => null,
    ]);

    Livewire::test(Overview::class) // real mode, default 'Dia' => period 'day'
        ->assertSee('rollup.only', false)
        ->assertSee('123', false);
});
