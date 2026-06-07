<?php

declare(strict_types=1);

use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;

beforeEach(function () {
    config()->set('trail.recorder', 'sync');
});

it('records a bare event synchronously', function () {
    Trail::track('product.viewed');

    $event = TrailEvent::firstWhere('name', 'product.viewed');

    expect($event)->not->toBeNull()
        ->and($event->subject_type)->toBeNull()
        ->and($event->subject_id)->toBeNull();
});

it('records properties and a value', function () {
    Trail::track('order.placed', ['order_id' => 7], value: 97.00);

    $event = TrailEvent::firstWhere('name', 'order.placed');

    expect($event->properties)->toBe(['order_id' => 7])
        ->and((float) $event->value)->toBe(97.00);
});

it('attributes the event to a subject via for()', function () {
    $user = User::create(['name' => 'Ada']);

    Trail::for($user)->track('team.invited', ['email' => 'a@b.c']);

    $event = TrailEvent::firstWhere('name', 'team.invited');

    expect($event->subject->is($user))->toBeTrue()
        ->and($event->properties)->toBe(['email' => 'a@b.c']);
});

it('records an anonymous event with no subject', function () {
    Trail::anonymous()->track('landing.cta_clicked');

    $event = TrailEvent::firstWhere('name', 'landing.cta_clicked');

    expect($event->subject_type)->toBeNull()
        ->and($event->subject_id)->toBeNull();
});

it('records session and context', function () {
    Trail::withSession('sess-123')
        ->withContext(['referrer' => 'google'])
        ->track('page.viewed');

    $event = TrailEvent::firstWhere('name', 'page.viewed');

    expect($event->session_id)->toBe('sess-123')
        ->and($event->context)->toMatchArray(['referrer' => 'google']);
});

it('does not leak fluent state between calls on the singleton', function () {
    $user = User::create(['name' => 'Ada']);

    Trail::for($user)->track('first.event');
    Trail::track('second.event');

    $second = TrailEvent::firstWhere('name', 'second.event');

    expect($second->subject_id)->toBeNull();
});

it('forces the sync recorder via sync() even when config differs', function () {
    config()->set('trail.recorder', 'queue');

    Trail::sync()->track('critical.event');

    expect(TrailEvent::firstWhere('name', 'critical.event'))->not->toBeNull();
});
