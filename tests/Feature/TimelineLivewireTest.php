<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\Sample;
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

/**
 * Fill the actor's stream with rows that survive the default filters, so a test
 * only passes if the filter under test reaches past the 300-row cap.
 */
function buryActor(string $name = 'order.placed', int $rows = 300): void
{
    for ($i = 0; $i < $rows; $i++) {
        TrailEvent::create([
            'name' => $name,
            'subject_type' => User::class,
            'subject_id' => 7,
            'occurred_at' => now()->subMinutes($i),
        ]);
    }
}

it('filters by type beyond the actor\'s newest 300 rows', function () {
    buryActor();
    TrailEvent::create([
        'name' => 'user.registered',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now()->subDays(10),
    ]);

    $events = Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|7')
        ->call('toggleType', 'user.registered')
        ->viewData('events');

    expect($events)->toHaveCount(1)
        ->and($events[0]['name'])->toBe('user.registered');
});

it('hides page views beyond the actor\'s newest 300 rows', function () {
    buryActor('page.viewed');
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now()->subDays(10),
    ]);

    $events = Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|7')
        ->viewData('events');

    expect($events)->toHaveCount(1)
        ->and($events[0]['name'])->toBe('order.placed');
});

it('offers every type the actor ever emitted as a chip', function () {
    buryActor();
    TrailEvent::create([
        'name' => 'user.registered',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now()->subDays(10),
    ]);

    $types = Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|7')
        ->viewData('types');

    expect(collect($types)->pluck('name')->all())->toBe(['order.placed', 'user.registered']);
});

it('reports the actor stats over their whole history', function () {
    buryActor();
    TrailEvent::create([
        'name' => 'user.registered',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now()->subDays(90),
    ]);

    $stats = Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|7')
        ->viewData('stats');

    expect($stats['total'])->toBe(301)
        ->and($stats['top_event'])->toBe('order.placed')
        ->and($stats['first'])->toBe(Sample::fullDate(now()->subDays(90)->getTimestampMs()));
});

it('keeps the stats panel on the whole history while a chip narrows the timeline', function () {
    buryActor();
    TrailEvent::create([
        'name' => 'user.registered',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now()->subDays(90),
    ]);

    $component = Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|7')
        ->call('toggleType', 'user.registered');

    expect($component->viewData('events'))->toHaveCount(1)
        ->and($component->viewData('stats')['total'])->toBe(301);
});

it('counts the daily bars over the whole history', function () {
    // 300 rows today, plus one yesterday that the row cap used to swallow.
    buryActor();
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => User::class,
        'subject_id' => 7,
        'occurred_at' => now()->subDay(),
    ]);

    $stats = Livewire::test(SubjectTimeline::class)
        ->set('actorId', User::class.'|7')
        ->viewData('stats');

    // bars run oldest-first over 7 days, so yesterday is the second-to-last.
    expect($stats['bars'][5])->toBe(1)
        ->and(array_sum($stats['bars']))->toBe(301);
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
