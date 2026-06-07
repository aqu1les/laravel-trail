<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\EventBuffer;

it('buffers rows and flushes them in a single insert', function () {
    $buffer = new EventBuffer(flushAt: 100);

    $buffer->push(['name' => 'a', 'uuid' => (string) Str::uuid(), 'occurred_at' => now()]);
    $buffer->push(['name' => 'b', 'uuid' => (string) Str::uuid(), 'occurred_at' => now()]);

    expect(TrailEvent::count())->toBe(0);

    $buffer->flush();

    expect(TrailEvent::count())->toBe(2)
        ->and($buffer->size())->toBe(0);
});

it('auto-flushes when reaching flush_at', function () {
    $buffer = new EventBuffer(flushAt: 2);

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
