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

it('switcher search finds an actor that ranks below the top activity list', function () {
    // Flood the top of the activity ranking so a quiet subject cannot ride
    // along in the default switcher list; the search must hit the database.
    foreach (range(1, 60) as $i) {
        $u = User::create(['name' => "Noise $i"]);
        foreach (range(1, 5) as $j) {
            TrailEvent::create([
                'name' => 'order.placed',
                'subject_type' => $u->getMorphClass(),
                'subject_id' => $u->getKey(),
                'occurred_at' => now(),
            ]);
        }
    }
    $marina = User::create(['name' => 'Marina Rocha']);
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => $marina->getMorphClass(),
        'subject_id' => $marina->getKey(),
        'occurred_at' => now(),
    ]);

    $results = Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|1')
        ->set('actorSearch', 'Marina')
        ->viewData('results');

    expect(collect($results)->pluck('name'))->toContain('Marina Rocha');
});

it('resolves a selected actor that ranks below the top activity list', function () {
    foreach (range(1, 60) as $i) {
        $u = User::create(['name' => "Noise $i"]);
        foreach (range(1, 5) as $j) {
            TrailEvent::create([
                'name' => 'order.placed',
                'subject_type' => $u->getMorphClass(),
                'subject_id' => $u->getKey(),
                'occurred_at' => now(),
            ]);
        }
    }
    $marina = User::create(['name' => 'Marina Rocha']);
    TrailEvent::create([
        'name' => 'signup',
        'subject_type' => $marina->getMorphClass(),
        'subject_id' => $marina->getKey(),
        'occurred_at' => now(),
    ]);

    $actor = Livewire::test(SubjectTimeline::class)
        ->set('actorId', $marina->getMorphClass().'|'.$marina->getKey())
        ->assertSee('Marina Rocha')
        ->assertSee('signup', false)
        ->viewData('actor');

    expect($actor['name'])->toBe('Marina Rocha');
});

it('excludes hidden page views from the timeline stats and counts them when revealed', function () {
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now(),
    ]);
    TrailEvent::create([
        'name' => 'page.viewed',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now(),
    ]);
    TrailEvent::create([
        'name' => 'page.viewed',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now(),
    ]);

    Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|7')
        ->assertSee('1 eventos', false)
        ->call('togglePageViews')
        ->assertSee('3 eventos', false);
});
