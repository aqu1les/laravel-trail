<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Models\TrailAggregate;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

it('switches the chart granularity in demo mode', function () {
    Livewire::test(Overview::class, ['demo' => true])
        ->assertSet('granularity', 'day')
        ->assertSee('1.24M')
        ->set('granularity', 'hour')
        ->assertSee('142k');
});

it('hydrates the chart range from the query string', function () {
    Livewire::withQueryParams(['range' => 'week'])
        ->test(Overview::class, ['demo' => true])
        ->assertSet('granularity', 'week')
        ->assertSee('5.9M');
});

it('falls back to the day range when the range token is unknown', function () {
    Livewire::withQueryParams(['range' => 'xyz'])
        ->test(Overview::class, ['demo' => true])
        ->assertSet('granularity', 'day')
        ->assertSee('1.24M');
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
        ->set('granularity', 'hour')->assertSee('Eventos ao longo do tempo', false)
        ->set('granularity', 'week')->assertSee('Eventos ao longo do tempo', false);
});

it('counts actors with the same id but different type as two distinct unique subjects', function () {
    TrailEvent::create(['name' => 'login', 'subject_type' => 'App\\Models\\User', 'subject_id' => 1, 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'login', 'subject_type' => 'App\\Models\\Team', 'subject_id' => 1, 'occurred_at' => now()]);

    Livewire::test(Overview::class)
        ->assertSeeInOrder(['Atores únicos ativos', '2']);
});

it('serves the series and top events from rollups when available', function () {
    // Only a pre-computed daily rollup exists - no raw events with this name.
    TrailAggregate::create([
        'period' => 'day',
        'bucket' => now()->startOfDay(),
        'name' => 'rollup.only',
        'count' => 123,
        'unique_subjects' => 5,
        'sum_value' => null,
    ]);

    Livewire::test(Overview::class) // real mode, default range 'day' => rollup period 'day'
        ->assertSee('rollup.only', false)
        ->assertSee('123', false);
});
