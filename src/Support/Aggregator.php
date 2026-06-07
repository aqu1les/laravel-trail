<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Illuminate\Support\Carbon;
use Trail\Trail\Models\TrailAggregate;
use Trail\Trail\Models\TrailEvent;

class Aggregator
{
    public function aggregate(string $period, Carbon $from, Carbon $to): void
    {
        $events = TrailEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['name', 'subject_id', 'value', 'occurred_at']);

        $rows = $events
            ->groupBy(fn (TrailEvent $event): string => $this->bucket($event->occurred_at, $period)->toDateTimeString().'|'.$event->name)
            ->map(function ($group) use ($period): array {
                /** @var TrailEvent $first */
                $first = $group->first();
                $sum = $group->whereNotNull('value')->sum(fn (TrailEvent $event): float => (float) $event->value);

                return [
                    'period' => $period,
                    'bucket' => $this->bucket($first->occurred_at, $period),
                    'name' => $first->name,
                    'count' => $group->count(),
                    'unique_subjects' => $group->whereNotNull('subject_id')->pluck('subject_id')->unique()->count(),
                    'sum_value' => $sum > 0 ? $sum : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        TrailAggregate::query()->upsert(
            $rows,
            uniqueBy: ['period', 'bucket', 'name'],
            update: ['count', 'unique_subjects', 'sum_value', 'updated_at'],
        );
    }

    private function bucket(Carbon $at, string $period): Carbon
    {
        return match ($period) {
            'hour' => $at->copy()->startOfHour(),
            'week' => $at->copy()->startOfWeek(),
            'month' => $at->copy()->startOfMonth(),
            default => $at->copy()->startOfDay(),
        };
    }
}
