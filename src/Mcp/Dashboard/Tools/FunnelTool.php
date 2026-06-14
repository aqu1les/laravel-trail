<?php

declare(strict_types=1);

namespace Trail\Trail\Mcp\Dashboard\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Trail\Trail\Mcp\Dashboard\Support\Provenance;
use Trail\Trail\Support\FunnelReport;

#[Name('trail_funnel')]
#[IsReadOnly]
#[Description('Conversion through an ordered sequence of event names. Returns per-step counts, rates, drop-off, and overall conversion. Membership-based: each step counts subjects who completed it and every preceding step.')]
class FunnelTool extends Tool
{
    public function handle(Request $request, FunnelReport $report): ResponseFactory
    {
        $validated = $request->validate([
            'steps' => 'required|array|min:1',
            'steps.*' => 'required|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        /** @var list<string> $steps */
        $steps = array_values($validated['steps']);
        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : null;

        $result = $report->build($steps, $from, $to);
        $result['provenance'] = Provenance::events();

        return Response::structured($result);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'steps' => $schema->array()
                ->items($schema->string())
                ->description('Ordered list of event names, e.g. ["signup", "purchase"].')
                ->required(),
            'from' => $schema->string()->description('Optional ISO-8601 lower bound on occurred_at.'),
            'to' => $schema->string()->description('Optional ISO-8601 upper bound on occurred_at.'),
        ];
    }
}
