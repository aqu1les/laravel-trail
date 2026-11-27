<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Trail\Trail\Models\TrailAggregate;
use Trail\Trail\Models\TrailEvent;

class Aggregator
{
    /** Events read per query while scanning the window. */
    private const CHUNK = 1000;

    /** Rows written per upsert statement. */
    private const UPSERT_CHUNK = 500;

    /**
     * Recompute every bucket of the given period that the window touches.
     *
     * The upsert replaces the stored row, so a bucket is always recomputed
     * whole: the window is widened to the start of the bucket holding $from
     * and the end of the bucket holding $to. Without that, a window starting
     * mid-day would overwrite that day's full count with a partial one.
     */
    public function aggregate(string $period, CarbonInterface $from, CarbonInterface $to): void
    {
        $start = $this->bucket($from, $period);
        $end = $this->nextBucket($to, $period);

        $events = TrailEvent::query()
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->select(['id', 'name', 'subject_type', 'subject_id', 'value', 'occurred_at'])
            ->lazyById(self::CHUNK);

        /** @var array<string, array{bucket: string, name: string, count: int, sum: float, subjects: array<string, true>}> $groups */
        $groups = [];

        foreach ($events as $event) {
            $bucket = $this->bucket($event->occurred_at, $period)->toDateTimeString();
            $key = $bucket.'|'.$event->name;

            $groups[$key] ??= ['bucket' => $bucket, 'name' => $event->name, 'count' => 0, 'sum' => 0.0, 'subjects' => []];
            $groups[$key]['count']++;

            if ($event->value !== null) {
                $groups[$key]['sum'] += (float) $event->value;
            }

            if ($event->subject_id !== null) {
                $groups[$key]['subjects'][$event->subject_type.'|'.$event->subject_id] = true;
            }
        }

        $now = Date::now();

        $rows = array_map(fn (array $group): array => [
            'period' => $period,
            'bucket' => $group['bucket'],
            'name' => $group['name'],
            'count' => $group['count'],
            'unique_subjects' => count($group['subjects']),
            'sum_value' => $group['sum'] > 0 ? $group['sum'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($groups));

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            TrailAggregate::query()->upsert(
                $chunk,
                uniqueBy: ['period', 'bucket', 'name'],
                update: ['count', 'unique_subjects', 'sum_value', 'updated_at'],
            );
        }
    }

    private function bucket(CarbonInterface $at, string $period): CarbonInterface
    {
        return match ($period) {
            'hour' => $at->copy()->startOfHour(),
            'week' => $at->copy()->startOfWeek(),
            'month' => $at->copy()->startOfMonth(),
            default => $at->copy()->startOfDay(),
        };
    }

    /** The start of the bucket right after the one holding $at. */
    private function nextBucket(CarbonInterface $at, string $period): CarbonInterface
    {
        $start = $this->bucket($at, $period);

        return match ($period) {
            'hour' => $start->addHour(),
            'week' => $start->addWeek(),
            'month' => $start->addMonth(),
            default => $start->addDay(),
        };
    }
}
