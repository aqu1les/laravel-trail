<?php

declare(strict_types=1);

use Trail\Trail\Contracts\EventBuffer;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

beforeEach(function () {
    // Default recorder writes synchronously so assertions see rows immediately.
    config()->set('trail.recorder', 'sync');
    Trail::ingestUsing(null);
});

afterEach(function () {
    Trail::ingestUsing(null);
    Trail::auth(null);
});

it('stores an authenticated event with the server-resolved subject', function () {
    $user = User::create(['name' => 'Ada']);

    $this->actingAs($user)
        ->postJson('/trail/api/ingest', [
            'events' => [
                ['name' => 'chat.message_sent', 'properties' => ['thread_id' => 42], 'value' => 1.0],
            ],
        ])
        ->assertStatus(202)
        ->assertJsonPath('accepted', 1);

    $event = TrailEvent::firstWhere('name', 'chat.message_sent');

    expect($event)->not->toBeNull()
        ->and($event->subject_type)->toBe($user->getMorphClass())
        ->and((int) $event->subject_id)->toBe($user->getKey())
        ->and($event->properties)->toBe(['thread_id' => 42])
        ->and((float) $event->value)->toBe(1.0);
});

it('stores an anonymous event tied to the client session_id', function () {
    $this->postJson('/trail/api/ingest', [
        'events' => [
            ['name' => 'page.opened', 'session_id' => 'anon-abc123'],
        ],
    ])->assertStatus(202);

    $event = TrailEvent::firstWhere('name', 'page.opened');

    expect($event->subject_type)->toBeNull()
        ->and($event->subject_id)->toBeNull()
        ->and($event->session_id)->toBe('anon-abc123');
});

it('accepts a batch of multiple events', function () {
    $this->postJson('/trail/api/ingest', [
        'events' => [
            ['name' => 'a.one', 'session_id' => 's1'],
            ['name' => 'a.two', 'session_id' => 's1'],
            ['name' => 'a.three', 'session_id' => 's1'],
        ],
    ])->assertStatus(202)->assertJsonPath('accepted', 3);

    expect(TrailEvent::count())->toBe(3);
});

it('honors a valid browser occurred_at', function () {
    $when = now()->subMinutes(3)->milliseconds(0);

    $this->postJson('/trail/api/ingest', [
        'events' => [
            ['name' => 'timed.event', 'session_id' => 's1', 'occurred_at' => $when->toIso8601String()],
        ],
    ])->assertStatus(202);

    expect(TrailEvent::firstWhere('name', 'timed.event')->occurred_at->toDateTimeString())
        ->toBe($when->toDateTimeString());
});

it('clamps a far-future occurred_at back to now', function () {
    $this->postJson('/trail/api/ingest', [
        'events' => [
            ['name' => 'future.event', 'session_id' => 's1', 'occurred_at' => now()->addDay()->toIso8601String()],
        ],
    ])->assertStatus(202);

    expect(TrailEvent::firstWhere('name', 'future.event')->occurred_at->diffInMinutes(now()))
        ->toBeLessThan(2);
});

it('clamps an unparseable occurred_at back to now', function () {
    $this->postJson('/trail/api/ingest', [
        'events' => [
            ['name' => 'bad.time', 'session_id' => 's1', 'occurred_at' => 'not-a-date'],
        ],
    ])->assertStatus(202);

    expect(TrailEvent::firstWhere('name', 'bad.time'))->not->toBeNull();
});

it('returns 422 when the batch structure is malformed', function () {
    $this->postJson('/trail/api/ingest', ['events' => []])->assertStatus(422);
    $this->postJson('/trail/api/ingest', ['events' => 'nope'])->assertStatus(422);
    $this->postJson('/trail/api/ingest', [])->assertStatus(422);
});

it('skips individually invalid events but accepts the good ones', function () {
    $this->postJson('/trail/api/ingest', [
        'events' => [
            ['name' => 'good.one', 'session_id' => 's1'],
            ['name' => 123, 'session_id' => 's1'],            // bad name
            ['properties' => ['x' => 1]],                      // missing name
            ['name' => 'good.two', 'value' => 'NaN'],          // bad value -> skipped
            ['name' => 'good.three', 'session_id' => 's1'],
        ],
    ])->assertStatus(202)->assertJsonPath('accepted', 2);

    expect(TrailEvent::pluck('name')->all())->toEqualCanonicalizing(['good.one', 'good.three']);
});

it('rejects the batch when it exceeds max_batch', function () {
    config()->set('trail.browser.max_batch', 2);

    $this->postJson('/trail/api/ingest', [
        'events' => [
            ['name' => 'a'], ['name' => 'b'], ['name' => 'c'],
        ],
    ])->assertStatus(422);
});

it('returns 429 once the rate limit is exceeded', function () {
    config()->set('trail.browser.rate_limit', '2,1');

    // Authenticate so the throttle key (user id) is stable across requests.
    // Anonymous test requests get a fresh session id each time.
    $this->actingAs(User::create(['name' => 'Throttled']));

    $payload = ['events' => [['name' => 'rl.event', 'session_id' => 'rl-session']]];

    $this->postJson('/trail/api/ingest', $payload)->assertStatus(202);
    $this->postJson('/trail/api/ingest', $payload)->assertStatus(202);
    $this->postJson('/trail/api/ingest', $payload)->assertStatus(429);
});

it('drops events whose name is not in the allowlist', function () {
    config()->set('trail.browser.allowed_events', ['allowed.event']);

    $this->postJson('/trail/api/ingest', [
        'events' => [
            ['name' => 'allowed.event', 'session_id' => 's1'],
            ['name' => 'blocked.event', 'session_id' => 's1'],
        ],
    ])->assertStatus(202)->assertJsonPath('accepted', 1);

    expect(TrailEvent::pluck('name')->all())->toBe(['allowed.event']);
});

it('routes events through the configured recorder into the ingest buffer', function () {
    config()->set('trail.browser.recorder', 'ingest');
    config()->set('trail.ingest.buffer', 'memory');
    config()->set('trail.ingest.flush_at', 100);
    app()->forgetInstance(EventBuffer::class);

    // Spy on the buffer so we can assert the ingest recorder received the event,
    // independent of the request's terminating flush.
    $buffer = new class implements EventBuffer
    {
        /** @var array<int, array<string, mixed>> */
        public array $pushed = [];

        public function push(array $attributes): void
        {
            $this->pushed[] = $attributes;
        }

        public function flush(): void {}

        public function size(): int
        {
            return count($this->pushed);
        }
    };
    app()->instance(EventBuffer::class, $buffer);

    $this->postJson('/trail/api/ingest', [
        'events' => [['name' => 'buffered.ingest', 'session_id' => 's1']],
    ])->assertStatus(202);

    expect($buffer->pushed)->toHaveCount(1)
        ->and($buffer->pushed[0]['name'])->toBe('buffered.ingest');
});

it('denies ingestion when the write gate denies', function () {
    Trail::ingestUsing(fn () => false);

    $this->postJson('/trail/api/ingest', [
        'events' => [['name' => 'denied.event', 'session_id' => 's1']],
    ])->assertForbidden();

    expect(TrailEvent::count())->toBe(0);
});

it('still allows ingestion when only the view gate denies', function () {
    Trail::auth(fn () => false);       // read/dashboard locked down
    Trail::ingestUsing(null);          // write open (default)

    $this->postJson('/trail/api/ingest', [
        'events' => [['name' => 'open.write', 'session_id' => 's1']],
    ])->assertStatus(202);

    // ...but the read API is forbidden under the same locked view gate.
    $this->getJson('/trail/api/events')->assertForbidden();
});
