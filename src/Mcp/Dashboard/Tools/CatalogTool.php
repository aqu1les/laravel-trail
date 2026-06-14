<?php

declare(strict_types=1);

namespace Trail\Trail\Mcp\Dashboard\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Trail\Trail\Mcp\Dashboard\Support\Provenance;
use Trail\Trail\Models\TrailEvent;

#[Name('trail_catalog')]
#[IsReadOnly]
#[Description('Discovery: list every event name with its count, value summary, first/last seen, unique subjects, and subject types. Call this first to learn the app event vocabulary before querying anything.')]
class CatalogTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $events = TrailEvent::query()
            ->when(isset($validated['from']), fn ($q) => $q->where('occurred_at', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($q) => $q->where('occurred_at', '<=', $validated['to']))
            ->get(['name', 'value', 'subject_type', 'subject_id', 'occurred_at']);

        $names = $events
            ->groupBy('name')
            ->map(fn (Collection $group, string $name): array => $this->summarize($name, $group))
            ->values()
            ->all();

        return Response::structured([
            'events' => $names,
            'provenance' => Provenance::events(),
        ]);
    }

    /**
     * @param  Collection<int, TrailEvent>  $group
     * @return array<string, mixed>
     */
    private function summarize(string $name, Collection $group): array
    {
        $withValue = $group->whereNotNull('value');

        return [
            'name' => $name,
            'count' => $group->count(),
            'value' => $withValue->isEmpty() ? null : [
                'sum' => (float) $withValue->sum(fn (TrailEvent $e): float => (float) $e->value),
                'min' => (float) $withValue->min(fn (TrailEvent $e): float => (float) $e->value),
                'max' => (float) $withValue->max(fn (TrailEvent $e): float => (float) $e->value),
            ],
            'first_seen' => $group->min(fn (TrailEvent $e) => $e->occurred_at)?->toIso8601String(),
            'last_seen' => $group->max(fn (TrailEvent $e) => $e->occurred_at)?->toIso8601String(),
            'unique_subjects' => $group->whereNotNull('subject_id')
                ->unique(fn (TrailEvent $e): string => $e->subject_type.'|'.$e->subject_id)
                ->count(),
            'subject_types' => $group->whereNotNull('subject_type')
                ->pluck('subject_type')->unique()->values()->all(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Optional ISO-8601 lower bound on occurred_at.'),
            'to' => $schema->string()->description('Optional ISO-8601 upper bound on occurred_at.'),
        ];
    }
}
