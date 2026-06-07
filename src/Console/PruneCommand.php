<?php

declare(strict_types=1);

namespace Trail\Trail\Console;

use Illuminate\Console\Command;
use Trail\Trail\Models\TrailAggregate;
use Trail\Trail\Models\TrailEvent;

class PruneCommand extends Command
{
    protected $signature = 'trail:prune';

    protected $description = 'Delete Trail data beyond the retention window';

    public function handle(): int
    {
        $eventsDays = (int) config('trail.retention.events_days', 90);
        $aggregatesDays = (int) config('trail.retention.aggregates_days', 730);

        $events = TrailEvent::query()->where('occurred_at', '<', now()->subDays($eventsDays))->delete();
        $aggregates = TrailAggregate::query()->where('bucket', '<', now()->subDays($aggregatesDays))->delete();

        $this->info("Pruned {$events} events and {$aggregates} aggregates.");

        return self::SUCCESS;
    }
}
