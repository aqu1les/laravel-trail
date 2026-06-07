<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Jobs\ProcessTrailEvent;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;

beforeEach(function () {
    config()->set('trail.recorder', 'queue');
});

it('dispatches a job instead of writing synchronously', function () {
    Queue::fake();

    Trail::track('order.placed', ['order_id' => 7], value: 97.00);

    Queue::assertPushed(ProcessTrailEvent::class);
    expect(TrailEvent::count())->toBe(0);
});

it('respects the configured queue connection and name', function () {
    Queue::fake();
    config()->set('trail.queue.connection', 'redis');
    config()->set('trail.queue.queue', 'tracking');

    Trail::track('order.placed');

    Queue::assertPushed(ProcessTrailEvent::class, function (ProcessTrailEvent $job) {
        return $job->connection === 'redis' && $job->queue === 'tracking';
    });
});

it('persists the event when the dispatched job runs', function () {
    $user = User::create(['name' => 'Ada']);

    Trail::for($user)->queue()->track('order.placed', ['order_id' => 7], value: 97.00);

    $event = TrailEvent::firstWhere('name', 'order.placed');

    expect($event)->not->toBeNull()
        ->and($event->subject->is($user))->toBeTrue()
        ->and($event->properties)->toBe(['order_id' => 7])
        ->and((float) $event->value)->toBe(97.00);
});
