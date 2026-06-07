<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers\Api;

use Illuminate\Http\Request;
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

        return [
            'total_events' => (clone $base)->count(),
            'unique_subjects' => (clone $base)->whereNotNull('subject_id')->distinct()->count('subject_id'),
            'top_events' => $topEvents,
        ];
    }
}
