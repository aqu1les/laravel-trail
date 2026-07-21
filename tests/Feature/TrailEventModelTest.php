<?php

declare(strict_types=1);

use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;

it('persists an event with its core attributes', function () {
    $event = TrailEvent::create([
        'name' => 'product.viewed',
        'properties' => ['sku' => 'ABC-1'],
        'context' => ['url' => '/products/1'],
        'value' => 97.00,
        'occurred_at' => now(),
    ]);

    expect($event->uuid)->not->toBeNull()
        ->and($event->name)->toBe('product.viewed')
        ->and($event->properties)->toBe(['sku' => 'ABC-1'])
        ->and($event->context)->toBe(['url' => '/products/1'])
        ->and((float) $event->value)->toBe(97.00);

    $this->assertDatabaseHas('trail_events', ['name' => 'product.viewed']);
});

it('resolves a polymorphic subject', function () {
    $user = User::create(['name' => 'Ada']);

    $event = TrailEvent::create([
        'name' => 'order.placed',
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->getKey(),
        'occurred_at' => now(),
    ]);

    expect($event->subject)->toBeInstanceOf(User::class)
        ->and($event->subject->is($user))->toBeTrue();
});
