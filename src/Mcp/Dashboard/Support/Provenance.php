<?php

declare(strict_types=1);

namespace Trail\Trail\Mcp\Dashboard\Support;

use Illuminate\Support\Carbon;

class Provenance
{
    /**
     * Footer for data read live from the trail_events table.
     *
     * @return array<string, mixed>
     */
    public static function events(bool $truncated = false, ?int $limit = null): array
    {
        $footer = [
            'source' => 'events',
            'as_of' => Carbon::now()->toIso8601String(),
            'truncated' => $truncated,
        ];

        if ($truncated && $limit !== null) {
            $footer['limit'] = $limit;
        }

        return $footer;
    }

    /**
     * Footer for data read from the rolled-up trail_aggregates table.
     *
     * @return array<string, mixed>
     */
    public static function aggregates(?Carbon $lastAggregatedAt): array
    {
        return [
            'source' => 'aggregates',
            'last_aggregated_at' => $lastAggregatedAt?->toIso8601String(),
            'truncated' => false,
        ];
    }
}
