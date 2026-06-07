<?php

declare(strict_types=1);

namespace Trail\Trail\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Trail\Trail\Support\Aggregator;

class AggregateCommand extends Command
{
    protected $signature = 'trail:aggregate
        {--period=day : hour|day|week|month}
        {--since=2 days : how far back to recompute}';

    protected $description = 'Recompute Trail aggregates for the dashboard';

    public function handle(Aggregator $aggregator): int
    {
        $period = (string) $this->option('period');
        $from = Carbon::parse('-'.(string) $this->option('since'));
        $to = Carbon::now();

        $aggregator->aggregate($period, $from, $to);

        $this->info("Aggregated [{$period}] from {$from->toDateTimeString()} to {$to->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
