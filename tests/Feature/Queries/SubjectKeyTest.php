<?php

declare(strict_types=1);

use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Queries\SubjectKey;
use Trail\Trail\Tests\Fixtures\User;

it('parses a token and round-trips it', function () {
    $key = SubjectKey::parse(User::class.'|7');

    expect($key->type)->toBe(User::class)
        ->and($key->id)->toBe('7')
        ->and((string) $key)->toBe(User::class.'|7')
        ->and($key->label())->toBe('User');
});

it('rejects tokens it cannot use', function (?string $token) {
    expect(SubjectKey::parse($token))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'no separator' => 'App\\Models\\User',
    'missing id' => 'App\\Models\\User|',
    'missing type' => '|7',
]);

it('keeps a pipe that appears inside the id', function () {
    // Only the first separator splits, so an id containing "|" survives.
    $key = SubjectKey::parse('App\\Models\\User|a|b');

    expect($key->id)->toBe('a|b');
});

it('builds from column values and rejects missing ones', function () {
    expect((string) SubjectKey::of(User::class, 7))->toBe(User::class.'|7')
        ->and(SubjectKey::of(null, 7))->toBeNull()
        ->and(SubjectKey::of(User::class, null))->toBeNull();
});

it('narrows a query to the subject', function () {
    TrailEvent::create(['name' => 'mine', 'subject_type' => User::class, 'subject_id' => 7, 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'theirs', 'subject_type' => User::class, 'subject_id' => 8, 'occurred_at' => now()]);

    $names = SubjectKey::parse(User::class.'|7')
        ->applyTo(TrailEvent::query())
        ->pluck('name')->all();

    expect($names)->toBe(['mine']);
});
