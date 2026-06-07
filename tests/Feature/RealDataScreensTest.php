<?php

declare(strict_types=1);

use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

function seedTrailEvent(string $name = 'order.placed'): TrailEvent
{
    return TrailEvent::create([
        'name' => $name,
        'subject_type' => User::class,
        'subject_id' => 1,
        'value' => 97,
        'properties' => ['order_id' => 7],
        'occurred_at' => now(),
    ]);
}

it('lists real events on the Events screen', function () {
    seedTrailEvent();
    $this->get('/trail/events')->assertOk()->assertSee('order.placed', false);
});

it('aggregates real events on the Overview', function () {
    seedTrailEvent();
    seedTrailEvent('user.signed_up');
    $this->get('/trail')->assertOk()->assertSee('order.placed', false);
});

it('builds a real subject timeline', function () {
    seedTrailEvent();
    $this->get('/trail/timeline')->assertOk()->assertSee('order.placed', false);
});

it('shows empty screens when there are no events', function () {
    $this->get('/trail/events')->assertOk()->assertSee('Nenhum evento', false);
});
