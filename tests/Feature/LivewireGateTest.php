<?php

declare(strict_types=1);

use Livewire\Livewire;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Livewire\Paths;
use Trail\Trail\Livewire\SubjectTimeline;
use Trail\Trail\Trail;

afterEach(fn () => Trail::auth(null));

// The dashboard routes run the view gate, but Livewire's update endpoint does
// not go through them. Every component re-checks the gate on each request.
it('refuses to mount a dashboard component when the view gate denies', function (string $component) {
    Trail::auth(fn () => false);

    Livewire::test($component, ['demo' => true])->assertForbidden();
})->with([Overview::class, Events::class, Paths::class, SubjectTimeline::class]);

it('refuses a Livewire update once the view gate denies', function (string $component) {
    $allowed = true;
    Trail::auth(function () use (&$allowed) {
        return $allowed;
    });

    $test = Livewire::test($component, ['demo' => true])->assertOk();

    $allowed = false;

    $test->call('$refresh')->assertForbidden();
})->with([Overview::class, Events::class, Paths::class, SubjectTimeline::class]);
