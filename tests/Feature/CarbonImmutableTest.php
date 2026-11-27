<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Trail\Trail\Facades\Trail as TrailFacade;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Livewire\Paths;
use Trail\Trail\Livewire\SubjectTimeline;
use Trail\Trail\Models\TrailAggregate;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\Aggregator;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

// Apps that call Date::use(CarbonImmutable::class) get CarbonImmutable back from
// now(), Date::now() and every Eloquent date cast. The package must accept it
// and must never rely on mutating a date in place.
beforeEach(function () {
    Date::use(CarbonImmutable::class);
    Trail::auth(fn () => true);
});

afterEach(function () {
    Date::useDefault();
    Trail::auth(null);
});

function seedImmutableEvents(): void
{
    $base = now()->startOfDay()->addHours(9);

    foreach ([['register', 1, '-2 days'], ['order.placed', 1, '-1 day'], ['register', 2, '-1 day'], ['order.placed', 2, 'now']] as [$name, $id, $offset]) {
        TrailEvent::create([
            'name' => $name,
            'subject_type' => User::class,
            'subject_id' => $id,
            'value' => 10,
            'occurred_at' => $offset === 'now' ? $base : $base->modify($offset),
        ]);
    }
}

it('hands back CarbonImmutable from the model casts', function () {
    seedImmutableEvents();

    expect(TrailEvent::first()->occurred_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('renders every dashboard screen on real data', function (string $uri) {
    $this->travelTo(now()->startOfDay()->addHours(12));
    seedImmutableEvents();

    $this->get($uri)->assertOk();
})->with(['/trail', '/trail/events', '/trail/paths', '/trail/timeline']);

it('renders the subject timeline', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    seedImmutableEvents();

    Livewire::test(SubjectTimeline::class)->assertOk();
});

it('renders the overview across granularities, live and from rollups', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    seedImmutableEvents();

    $live = Livewire::test(Overview::class);
    foreach (['hour', 'day', 'week'] as $granularity) {
        $live->set('granularity', $granularity)->assertOk();
    }

    $this->artisan('trail:aggregate', ['--period' => 'day'])->assertSuccessful();
    $this->artisan('trail:aggregate', ['--period' => 'hour'])->assertSuccessful();
    $this->artisan('trail:aggregate', ['--period' => 'week', '--since' => '30 days'])->assertSuccessful();

    $rollup = Livewire::test(Overview::class);
    foreach (['hour', 'day', 'week'] as $granularity) {
        $rollup->set('granularity', $granularity)->assertOk();
    }
});

it('renders the events and paths screens for every period', function (string $since) {
    $this->travelTo(now()->startOfDay()->addHours(12));
    seedImmutableEvents();

    Livewire::test(Events::class)->set('since', $since)->assertOk()->assertSee('order.placed', false);
    Livewire::test(Paths::class)->set('since', $since)->assertOk();
})->with(['today', '7d', '30d']);

it('aggregates into the right buckets', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    $at = now()->startOfDay()->addHours(9)->addMinutes(41);
    TrailEvent::create(['name' => 'order.placed', 'value' => 5, 'occurred_at' => $at]);
    TrailEvent::create(['name' => 'order.placed', 'value' => 7, 'occurred_at' => $at]);

    (new Aggregator)->aggregate('hour', now()->subDay(), now());
    (new Aggregator)->aggregate('day', now()->subDay(), now());

    $hour = TrailAggregate::where('period', 'hour')->first();
    $day = TrailAggregate::where('period', 'day')->first();

    expect($hour->bucket->format('Y-m-d H:i:s'))->toBe($at->startOfHour()->format('Y-m-d H:i:s'))
        ->and($day->bucket->format('Y-m-d H:i:s'))->toBe($at->startOfDay()->format('Y-m-d H:i:s'))
        ->and($hour->count)->toBe(2)
        ->and((float) $hour->sum_value)->toBe(12.0);
});

it('prunes with an immutable clock', function () {
    config()->set('trail.retention.events_days', 30);
    TrailEvent::create(['name' => 'old', 'occurred_at' => now()->subDays(40)]);
    TrailEvent::create(['name' => 'fresh', 'occurred_at' => now()]);

    $this->artisan('trail:prune')->assertSuccessful();

    expect(TrailEvent::pluck('name')->all())->toBe(['fresh']);
});

it('reads through the query helpers', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    seedImmutableEvents();

    expect(TrailFacade::events()->today())->toBe(1)
        ->and(TrailFacade::events()->between(now()->subDays(3), now())->count())->toBe(4);
});
