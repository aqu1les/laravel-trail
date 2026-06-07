<?php

declare(strict_types=1);

use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;

beforeEach(function () {
    config()->set('trail.recorder', 'sync');
});

it('exposes a trailEvents morph relation', function () {
    $user = User::create(['name' => 'Ada']);

    TrailEvent::create([
        'name' => 'a.b',
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->getKey(),
        'occurred_at' => now(),
    ]);

    expect($user->trailEvents)->toHaveCount(1)
        ->and($user->trailEvents->first()->name)->toBe('a.b');
});

it('records an event through the model shortcut', function () {
    $user = User::create(['name' => 'Ada']);

    $user->track('profile.updated', ['field' => 'name']);

    $event = TrailEvent::firstWhere('name', 'profile.updated');

    expect($event->subject->is($user))->toBeTrue()
        ->and($event->properties)->toBe(['field' => 'name']);
});

it('attributes a bare Trail::track to the configured default subject', function () {
    $user = User::create(['name' => 'Ada']);

    config()->set('trail.subject.resolver', fn () => $user);

    Trail::track('dashboard.opened');

    $event = TrailEvent::firstWhere('name', 'dashboard.opened');

    expect($event->subject->is($user))->toBeTrue();
});
