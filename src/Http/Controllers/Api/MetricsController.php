<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Trail\Trail\Models\TrailEvent;

class MetricsController
{
    /**
     * Return high-level overview metrics for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $base = TrailEvent::query()
            ->when($request->filled('from'), fn ($query) => $query->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('occurred_at', '<=', $request->date('to')));

        $topEvents = (clone $base)
            ->selectRaw('name, count(*) as count')
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit((int) $request->integer('limit', 10))
            ->get()
            ->map(fn (TrailEvent $event) => [
                'name' => $event->name,
                'count' => (int) $event->getAttribute('count'),
            ]);

        $period = strtolower($request->string('period')->value());
        if (! in_array($period, ['hour', 'day', 'week', 'month'], true)) {
            $period = 'day';
        }

        return [
            'range' => [
                'from' => $request->date('from')?->toIso8601String(),
                'to' => $request->date('to')?->toIso8601String(),
                'period' => $period,
            ],
            'total_events' => (clone $base)->count(),
            'unique_subjects' => DB::table(
                (clone $base)->whereNotNull('subject_id')->whereNotNull('subject_type')
                    ->select('subject_type', 'subject_id')->distinct()->toBase(),
                'unique_actors'
            )->count(),
            'top_events' => $topEvents,
            'series' => $this->series($base, $period),
        ];
    }

    /**
     * Build a per-bucket time series in PHP for cross-database portability.
     *
     * @param  Builder<TrailEvent>  $base
     * @return list<array{bucket: string, count: int, unique_subjects: int}>
     */
    private function series(Builder $base, string $period): array
    {
        $format = match ($period) {
            'hour' => 'Y-m-d H:00',
            'week' => 'o-\WW',
            'month' => 'Y-m',
            default => 'Y-m-d',
        };

        $grouped = (clone $base)
            ->get(['occurred_at', 'subject_type', 'subject_id'])
            ->groupBy(fn (TrailEvent $event): string => $event->occurred_at->format($format));

        $series = [];

        foreach ($grouped as $key => $group) {
            $series[] = [
                'bucket' => (string) $key,
                'count' => $group->count(),
                'unique_subjects' => $group->whereNotNull('subject_id')
                    ->unique(fn (TrailEvent $event): string => $event->subject_type.'|'.$event->subject_id)
                    ->count(),
            ];
        }

        usort($series, fn (array $a, array $b): int => $a['bucket'] <=> $b['bucket']);

        return $series;
    }
}
