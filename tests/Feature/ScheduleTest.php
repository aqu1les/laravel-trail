<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

function scheduledCommands(): array
{
    return collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) $event->command)
        ->all();
}

it('schedules aggregate and prune by default', function () {
    $commands = collect(scheduledCommands());

    expect($commands->contains(fn (string $c) => str_contains($c, 'trail:aggregate')))->toBeTrue()
        ->and($commands->contains(fn (string $c) => str_contains($c, 'trail:prune')))->toBeTrue();
});
