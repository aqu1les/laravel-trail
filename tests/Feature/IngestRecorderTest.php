<?php

declare(strict_types=1);

use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Str;
use Trail\Trail\Contracts\EventBuffer;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\MemoryEventBuffer;
use Trail\Trail\Support\RedisEventBuffer;

it('buffers rows and flushes them in a single insert', function () {
    $buffer = new MemoryEventBuffer(flushAt: 100);

    $buffer->push(['name' => 'a', 'uuid' => (string) Str::uuid(), 'occurred_at' => now()]);
    $buffer->push(['name' => 'b', 'uuid' => (string) Str::uuid(), 'occurred_at' => now()]);

    expect(TrailEvent::count())->toBe(0);

    $buffer->flush();

    expect(TrailEvent::count())->toBe(2)
        ->and($buffer->size())->toBe(0);
});

it('auto-flushes when reaching flush_at', function () {
    $buffer = new MemoryEventBuffer(flushAt: 2);

    $buffer->push(['name' => 'a', 'uuid' => (string) Str::uuid(), 'occurred_at' => now()]);
    expect(TrailEvent::count())->toBe(0);

    $buffer->push(['name' => 'b', 'uuid' => (string) Str::uuid(), 'occurred_at' => now()]);
    expect(TrailEvent::count())->toBe(2);
});

it('records through the ingest driver into the buffer', function () {
    config()->set('trail.recorder', 'ingest');
    config()->set('trail.ingest.flush_at', 100);

    Trail::track('order.placed');

    expect(TrailEvent::count())->toBe(0);

    app(EventBuffer::class)->flush();

    expect(TrailEvent::firstWhere('name', 'order.placed'))->not->toBeNull();
});

it('selects the buffer driver from config', function () {
    config()->set('trail.ingest.buffer', 'memory');
    app()->forgetInstance(EventBuffer::class);
    expect(app(EventBuffer::class))->toBeInstanceOf(MemoryEventBuffer::class);

    config()->set('trail.ingest.buffer', 'redis');
    app()->forgetInstance(EventBuffer::class);
    expect(app(EventBuffer::class))->toBeInstanceOf(RedisEventBuffer::class);
});

it('buffers to redis and flushes via a single bulk insert', function () {
    $conn = Mockery::mock(Connection::class);
    $factory = Mockery::mock(Redis::class);
    $factory->shouldReceive('connection')->andReturn($conn);

    $buffer = new RedisEventBuffer($factory, flushAt: 100, key: 'k');

    $conn->shouldReceive('command')->with('rpush', Mockery::type('array'))->once();
    $conn->shouldReceive('command')->with('llen', ['k'])->andReturn(1);
    $buffer->push(['name' => 'a', 'occurred_at' => now()]);

    expect(TrailEvent::count())->toBe(0);

    $conn->shouldReceive('command')->with('lrange', ['k', 0, -1])->andReturn([
        (string) json_encode(['name' => 'a', 'occurred_at' => now()->toDateTimeString()]),
        (string) json_encode(['name' => 'b', 'occurred_at' => now()->toDateTimeString()]),
    ]);
    $conn->shouldReceive('command')->with('del', ['k'])->once();
    $buffer->flush();

    expect(TrailEvent::count())->toBe(2);
});
