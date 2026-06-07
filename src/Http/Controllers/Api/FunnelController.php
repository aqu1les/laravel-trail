<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers\Api;

use Illuminate\Http\Request;
use Trail\Trail\Support\FunnelReport;

class FunnelController
{
    /**
     * Compute conversion through an ordered sequence of events.
     *
     * @return array<string, mixed>
     */
    public function show(Request $request, FunnelReport $report): array
    {
        /** @var list<string> $steps */
        $steps = array_values(array_filter((array) $request->input('steps', [])));

        return $report->build($steps);
    }
}
