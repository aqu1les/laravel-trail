<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers\Api;

use Illuminate\Http\Request;
use Trail\Trail\Models\TrailEvent;

class FunnelController
{
    /**
     * Compute conversion through an ordered sequence of events.
     *
     * Conversion is membership-based: each step counts the subjects who
     * completed it and every preceding step (in any temporal order).
     *
     * @return array<string, mixed>
     */
    public function show(Request $request): array
    {
        /** @var list<string> $steps */
        $steps = array_values(array_filter((array) $request->input('steps', [])));

        /** @var list<string>|null $qualified */
        $qualified = null;
        $result = [];
        $firstCount = 0;
        $previousCount = 0;

        foreach ($steps as $index => $name) {
            $identities = $this->subjectsForEvent($name);

            $qualified = $qualified === null
                ? $identities
                : array_values(array_intersect($qualified, $identities));

            $count = count($qualified);

            if ($index === 0) {
                $firstCount = $count;
            }

            $result[] = [
                'name' => $name,
                'count' => $count,
                'rate' => $firstCount === 0 ? 0.0 : round($count / $firstCount, 4),
                'drop_off' => $index === 0 ? 0 : $previousCount - $count,
            ];

            $previousCount = $count;
        }

        $lastCount = $previousCount;

        return [
            'steps' => $result,
            'overall_conversion' => $firstCount === 0 ? 0.0 : round($lastCount / $firstCount, 4),
        ];
    }

    /**
     * The distinct subject identities that performed the given event.
     *
     * @return array<int, string>
     */
    private function subjectsForEvent(string $name): array
    {
        return TrailEvent::query()
            ->where('name', $name)
            ->whereNotNull('subject_id')
            ->get(['subject_type', 'subject_id'])
            ->map(fn (TrailEvent $event): string => $event->subject_type.'|'.$event->subject_id)
            ->unique()
            ->values()
            ->all();
    }
}
