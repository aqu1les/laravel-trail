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

it('counts actors with the same id but different type as two distinct unique subjects in overview metrics', function () {
    makeEvent(['subject_type' => 'App\\Models\\User', 'subject_id' => 1]);
    makeEvent(['subject_type' => 'App\\Models\\Team', 'subject_id' => 1]);

    $this->getJson('/trail/api/metrics')
        ->assertOk()
        ->assertJsonPath('unique_subjects', 2);
});

it('counts actors with the same id but different type as two distinct unique subjects in series buckets', function () {
    makeEvent(['subject_type' => 'App\\Models\\User', 'subject_id' => 1, 'occurred_at' => now()]);
    makeEvent(['subject_type' => 'App\\Models\\Team', 'subject_id' => 1, 'occurred_at' => now()]);

    $response = $this->getJson('/trail/api/metrics?period=day')->assertOk();

    $bucket = collect($response->json('series'))->firstWhere('unique_subjects', '>', 0);
    expect($bucket['unique_subjects'])->toBe(2);
});

it('computes funnel conversion per step', function () {
    $u1 = User::create(['name' => 'A']);
    $u2 = User::create(['name' => 'B']);

    // u1 completes both steps; u2 only the first.
    foreach ([$u1, $u2] as $u) {
        makeEvent(['name' => 'signup', 'subject_type' => $u->getMorphClass(), 'subject_id' => $u->getKey()]);
    }
    makeEvent(['name' => 'purchase', 'subject_type' => $u1->getMorphClass(), 'subject_id' => $u1->getKey()]);

    $this->getJson('/trail/api/funnel?'.http_build_query(['steps' => ['signup', 'purchase']]))
        ->assertOk()
        ->assertJsonPath('steps.0.name', 'signup')
        ->assertJsonPath('steps.0.count', 2)
        ->assertJsonPath('steps.1.name', 'purchase')
        ->assertJsonPath('steps.1.count', 1);
});

it('orders events ascending when asked', function () {
    makeEvent(['name' => 'old.event', 'occurred_at' => now()->subDay()]);
    makeEvent(['name' => 'new.event', 'occurred_at' => now()]);

    $this->getJson('/trail/api/events?order=asc')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'old.event');
});

it('returns a daily series and the resolved range in metrics', function () {
    makeEvent(['name' => 'a', 'occurred_at' => now()->subDay()]);
    makeEvent(['name' => 'b', 'occurred_at' => now()]);

    $response = $this->getJson('/trail/api/metrics?period=day');

    $response->assertOk()
        ->assertJsonStructure([
            'range' => ['from', 'to', 'period'],
            'total_events',
            'unique_subjects',
            'top_events',
            'series' => [['bucket', 'count', 'unique_subjects']],
        ])
        ->assertJsonPath('range.period', 'day');

    expect(count($response->json('series')))->toBeGreaterThanOrEqual(2);
});

it('includes conversion rate and drop-off per funnel step', function () {
    $u1 = User::create(['name' => 'A']);
    $u2 = User::create(['name' => 'B']);

    foreach ([$u1, $u2] as $u) {
        makeEvent(['name' => 'signup', 'subject_type' => $u->getMorphClass(), 'subject_id' => $u->getKey()]);
    }
    makeEvent(['name' => 'purchase', 'subject_type' => $u1->getMorphClass(), 'subject_id' => $u1->getKey()]);

    $this->getJson('/trail/api/funnel?'.http_build_query(['steps' => ['signup', 'purchase']]))
        ->assertOk()
        ->assertJsonPath('steps.0.rate', 1) // JSON serializes 1.0 as 1; the UI parseFloats
        ->assertJsonPath('steps.1.rate', 0.5)
        ->assertJsonPath('steps.1.drop_off', 1)
        ->assertJsonPath('overall_conversion', 0.5);
});
