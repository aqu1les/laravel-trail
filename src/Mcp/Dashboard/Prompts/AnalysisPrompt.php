<?php

declare(strict_types=1);

namespace Trail\Trail\Mcp\Dashboard\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;

#[Name('trail_analysis')]
#[Description('How to analyze this app behavioral data with the Trail tools. Load this before answering analytics questions.')]
class AnalysisPrompt extends Prompt
{
    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        $guidance = <<<'TXT'
        You are analyzing product behavioral data exposed by Trail. Follow this routing:

        1. Start with `trail_catalog` to learn the event vocabulary (names, whether they carry value, subject types, date ranges) before querying anything.
        2. For trends, totals, and time series, prefer the aggregate tools: `trail_metrics` (overview + per-bucket series) and `trail_funnel` (conversion through an ordered set of events).
        3. Use `trail_events` only to investigate specific sessions or subjects. It is a bounded drill-down, not a way to total things up.
        4. `value` is serialized as a string in some payloads; coerce it to a number before doing math.
        5. Any result marked `"truncated": true` is a sample, not a complete total. Do not sum a truncated result and present it as the whole.
        6. Cite the provenance footer (`source`, `as_of` / `last_aggregated_at`) when you state how fresh a number is. Aggregate-sourced `unique_subjects` may be null by design; query a bounded range from events for an exact count.
        TXT;

        return [
            Response::text($guidance)->asAssistant(),
        ];
    }
}
