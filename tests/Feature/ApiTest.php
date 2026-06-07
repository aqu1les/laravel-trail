<?php

declare(strict_types=1);

use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(function () {
    Trail::auth(fn () => true);
});

afterEach(function () {
    Trail::auth(null);
});

function makeEvent(array $attributes = []): TrailEvent
{
    return TrailEvent::create(array_merge([
        'name' => 'product.viewed',
        'occurred_at' => now(),
    ], $attributes));
}

it('lists events as JSON, newest first', function () {
    makeEvent(['name' => 'old.event', 'occurred_at' => now()->subDay()]);
    makeEvent(['name' => 'new.event', 'occurred_at' => now()]);

    $response = $this->getJson('/trail/api/events');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'new.event');
});

it('filters events by name', function () {
    makeEvent(['name' => 'a.b']);
    makeEvent(['name' => 'c.d']);

    $this->getJson('/trail/api/events?name=a.b')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'a.b');
});

it('filters events by subject', function () {
    $user = User::create(['name' => 'Ada']);
    makeEvent(['name' => 'with.subject', 'subject_type' => $user->getMorphClass(), 'subject_id' => $user->getKey()]);
    makeEvent(['name' => 'without.subject']);

    $this->getJson('/trail/api/events?subject_type='.urlencode($user->getMorphClass()).'&subject_id='.$user->getKey())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'with.subject');
});

it('returns overview metrics', function () {
    $user = User::create(['name' => 'Ada']);
    makeEvent(['name' => 'order.placed', 'subject_type' => $user->getMorphClass(), 'subject_id' => $user->getKey(), 'value' => 10]);
    makeEvent(['name' => 'order.placed', 'subject_type' => $user->getMorphClass(), 'subject_id' => $user->getKey(), 'value' => 5]);
    makeEvent(['name' => 'product.viewed']);

    $response = $this->getJson('/trail/api/metrics');

    $response->assertOk()
        ->assertJsonPath('total_events', 3)
        ->assertJsonPath('unique_subjects', 1)
        ->assertJsonPath('top_events.0.name', 'order.placed')
        ->assertJsonPath('top_events.0.count', 2);
});

it('computes funnel conversion per step', function () {
    $u1 = User::create(['name' => 'A']);
    $u2 = User::create(['name' => 'B']);

    // u1 completes both steps; u2 only the first.
    foreach ([$u1, $u2] as $u) {
        makeEvent(['name' => 'signup', 'subject_type' => $u->getMorphClass(), 'subject_id' => $u->getKey()]);
    }
    makeEvent(['name' => 'purchase', 'subject_type' => $u1->getMorphClass(), 'subject_id' => $u1->getKey()]);

    $response = $this->getJson('/trail/api/funnel?'.http_build_query(['steps' => ['signup', 'purchase']]));

    $response->assertOk()
        ->assertJsonPath('steps.0.name', 'signup')
        ->assertJsonPath('steps.0.count', 2)
        ->assertJsonPath('steps.1.name', 'purchase')
        ->assertJsonPath('steps.1.count', 1);
});
