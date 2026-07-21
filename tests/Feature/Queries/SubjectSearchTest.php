<?php

declare(strict_types=1);

use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Queries\SubjectSearch;
use Trail\Trail\Tests\Fixtures\User;

it('lists the distinct subject types in a query, ignoring anonymous events', function () {
    TrailEvent::create(['name' => 'a', 'subject_type' => User::class, 'subject_id' => 1, 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'b', 'subject_type' => User::class, 'subject_id' => 2, 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'c', 'occurred_at' => now()]);

    expect(SubjectSearch::distinctTypes(TrailEvent::query()))->toBe([User::class]);
});

it('matches subjects by name and by email', function () {
    $ada = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@acme.app']);
    User::create(['name' => 'Grace Hopper', 'email' => 'grace@acme.app']);

    expect(SubjectSearch::matchingIds('Lovelace', [User::class], 100))->toBe([User::class => [$ada->id]])
        ->and(SubjectSearch::matchingIds('ada@acme', [User::class], 100))->toBe([User::class => [$ada->id]]);
});

it('skips types whose class cannot be resolved', function () {
    expect(SubjectSearch::matchingIds('anything', ['App\\Models\\Ghost'], 100))->toBe([]);
});

it('omits a type entirely when nothing matches', function () {
    User::create(['name' => 'Ada Lovelace']);

    expect(SubjectSearch::matchingIds('nobody', [User::class], 100))->toBe([]);
});

it('respects the per-type cap', function () {
    foreach (range(1, 5) as $i) {
        User::create(['name' => "Filler $i"]);
    }

    expect(SubjectSearch::matchingIds('Filler', [User::class], 2)[User::class])->toHaveCount(2);
});

it('leaves the caller\'s builder untouched', function () {
    TrailEvent::create(['name' => 'a', 'subject_type' => User::class, 'subject_id' => 1, 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'b', 'occurred_at' => now()]);

    $query = TrailEvent::query()->orderBy('name');
    SubjectSearch::distinctTypes($query);

    // The ordering survives, and the anonymous row was not filtered out.
    expect($query->pluck('name')->all())->toBe(['a', 'b']);
});
