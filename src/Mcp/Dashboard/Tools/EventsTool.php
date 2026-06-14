<?php

declare(strict_types=1);

namespace Trail\Trail\Mcp\Dashboard\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Trail\Trail\Mcp\Dashboard\Support\Provenance;
use Trail\Trail\Models\TrailEvent;

#[Name('trail_events')]
#[IsReadOnly]
#[Description('Bounded drill-down into individual events, newest first by default. Use only to investigate specific sessions or subjects; prefer trail_metrics for trends. properties/context are omitted unless enabled by config. Hard-capped; over-cap results are marked truncated.')]
class EventsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'subject_type' => 'nullable|string',
            'subject_id' => 'nullable|string',
            'session_id' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'order' => 'nullable|in:asc,desc',
            'limit' => 'nullable|integer|min:1',
            'include_properties' => 'nullable|boolean',
        ]);

        $cap = (int) config('trail.mcp.dashboard.events_max', 200);
        $requested = (int) ($validated['limit'] ?? 50);
        $limit = min($requested, $cap);
        $direction = ($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $exposeProperties = (bool) config('trail.mcp.dashboard.expose_properties', false)
            && ($validated['include_properties'] ?? false);

        $rows = TrailEvent::query()
            ->when(isset($validated['name']), fn ($q) => $q->where('name', $validated['name']))
            ->when(isset($validated['subject_type']), fn ($q) => $q->where('subject_type', $validated['subject_type']))
            ->when(isset($validated['subject_id']), fn ($q) => $q->where('subject_id', $validated['subject_id']))
            ->when(isset($validated['session_id']), fn ($q) => $q->where('session_id', $validated['session_id']))
            ->when(isset($validated['from']), fn ($q) => $q->where('occurred_at', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($q) => $q->where('occurred_at', '<=', $validated['to']))
            ->orderBy('occurred_at', $direction)
            ->orderBy('id', $direction)
            ->limit($limit + 1) // fetch one extra to detect more rows
            ->get();

        $truncated = $rows->count() > $limit || $requested > $cap;
        $events = $rows->take($limit)->map(fn (TrailEvent $event): array => $this->present($event, $exposeProperties))->all();

        $payload = [
            'events' => $events,
            'provenance' => Provenance::events(truncated: $truncated, limit: $limit),
        ];

        if (! $exposeProperties) {
            $payload['notes'] = ['properties and context are hidden; set trail.mcp.dashboard.expose_properties to true to allow include_properties.'];
        }

        return Response::structured($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(TrailEvent $event, bool $exposeProperties): array
    {
        $row = [
            'name' => $event->name,
            'value' => $event->value,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'session_id' => $event->session_id,
        ];

        if ($exposeProperties) {
            $row['properties'] = $event->properties;
            $row['context'] = $event->context;
        }

        return $row;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Filter by event name.'),
            'subject_type' => $schema->string()->description('Filter by subject (actor) morph type.'),
            'subject_id' => $schema->string()->description('Filter by subject (actor) id.'),
            'session_id' => $schema->string()->description('Filter by session id.'),
            'from' => $schema->string()->description('ISO-8601 lower bound on occurred_at.'),
            'to' => $schema->string()->description('ISO-8601 upper bound on occurred_at.'),
            'order' => $schema->string()->enum(['asc', 'desc'])->description('Sort direction by occurred_at.')->default('desc'),
            'limit' => $schema->integer()->description('Max rows. Hard-capped by config (default cap 200).')->default(50),
            'include_properties' => $schema->boolean()->description('Request properties/context. Ignored unless allowed by server config.')->default(false),
        ];
    }
}
