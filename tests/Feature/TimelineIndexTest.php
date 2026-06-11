<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Trail\Trail\Livewire\SubjectTimeline;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

function seedSubjectEvent(string $type, int|string $id, string $when = 'now'): void
{
    TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => $type,
        'subject_id' => $id,
        'occurred_at' => Carbon::parse($when),
    ]);
}

it('orders the index by most recent activity', function () {
    $old = User::create(['name' => 'Old User']);
    seedSubjectEvent($old->getMorphClass(), $old->getKey(), now()->subDays(10)->toDateTimeString());

    $recent = User::create(['name' => 'Recent User']);
    seedSubjectEvent($recent->getMorphClass(), $recent->getKey(), now()->toDateTimeString());

    $actors = Livewire::test(SubjectTimeline::class)->viewData('actors');

    expect($actors[0]['name'])->toBe('Recent User');
});

it('paginates the index to 25 actors per page', function () {
    foreach (range(1, 30) as $i) {
        $u = User::create(['name' => "User $i"]);
        seedSubjectEvent($u->getMorphClass(), $u->getKey(), now()->subMinutes($i)->toDateTimeString());
    }

    $component = Livewire::test(SubjectTimeline::class);

    expect($component->viewData('total'))->toBe(30)
        ->and(count($component->viewData('actors')))->toBe(25)
        ->and($component->viewData('totalPages'))->toBe(2);
});

it('finds a subject by name even when ranked low', function () {
    foreach (range(1, 30) as $i) {
        $u = User::create(['name' => "Filler $i"]);
        seedSubjectEvent($u->getMorphClass(), $u->getKey(), now()->subMinutes($i)->toDateTimeString());
    }
    $marina = User::create(['name' => 'Marina Rocha']);
    seedSubjectEvent($marina->getMorphClass(), $marina->getKey(), now()->subDays(40)->toDateTimeString());

    Livewire::test(SubjectTimeline::class)
        ->set('indexSearch', 'Marina')
        ->assertViewHas('total', 1)
        ->assertSee('Marina Rocha');
});

it('finds an unresolved subject only by id', function () {
    seedSubjectEvent('App\\Models\\Ghost', 4242, now()->toDateTimeString());

    Livewire::test(SubjectTimeline::class)
        ->set('indexSearch', '4242')
        ->assertViewHas('total', 1);

    Livewire::test(SubjectTimeline::class)
        ->set('indexSearch', 'ghostname')
        ->assertViewHas('total', 0);
});
