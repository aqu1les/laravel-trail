<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;

it('queries events fluently by subject and date range', function () {
    $user = User::create(['name' => 'Ada']);

    TrailEvent::create(['name' => 'a', 'subject_type' => $user->getMorphClass(), 'subject_id' => $user->getKey(), 'occurred_at' => now()->subDays(2)]);
    TrailEvent::create(['name' => 'b', 'subject_type' => $user->getMorphClass(), 'subject_id' => $user->getKey(), 'occurred_at' => now()->subDays(20)]);

    $results = Trail::events()->for($user)->between(now()->subDays(5), now())->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('a');
});

it('counts events for today', function () {
    TrailEvent::create(['name' => 'order.placed', 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'order.placed', 'occurred_at' => now()->subDays(2)]);

    expect(Trail::count('order.placed')->today())->toBe(1);
});

it('paginates events for Livewire consumption', function () {
    foreach (range(1, 3) as $i) {
        TrailEvent::create(['name' => "e.$i", 'occurred_at' => now()->subMinutes($i)]);
    }

    $page = Trail::events()->paginate(2);

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->total())->toBe(3)
        ->and($page->perPage())->toBe(2);
});

it('builds a funnel report from the facade', function () {
    $u = User::create(['name' => 'A']);
    TrailEvent::create(['name' => 'signup', 'subject_type' => $u->getMorphClass(), 'subject_id' => $u->getKey(), 'occurred_at' => now()]);
    TrailEvent::create(['name' => 'purchase', 'subject_type' => $u->getMorphClass(), 'subject_id' => $u->getKey(), 'occurred_at' => now()]);

    $report = Trail::funnel(['signup', 'purchase']);

    expect($report['steps'][0]['name'])->toBe('signup')
        ->and($report['overall_conversion'])->toBe(1.0);
});
