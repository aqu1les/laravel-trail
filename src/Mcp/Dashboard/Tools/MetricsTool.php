<?php

declare(strict_types=1);

namespace Trail\Trail\Mcp\Dashboard\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Trail\Trail\Http\Controllers\Api\MetricsController;
use Trail\Trail\Mcp\Dashboard\Support\Provenance;
use Trail\Trail\Models\TrailAggregate;

#[Name('trail_metrics')]
#[IsReadOnly]
#[Description('Aggregate overview for a time range: total events, unique subjects, top events, and a per-bucket time series. Reads rolled-up aggregates when available, else live events. Prefer this over trail_events for trends and totals.')]
class MetricsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'period' => 'nullable|in:hour,day,week,month',
            'name' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        $period = $validated['period'] ?? 'day';
        $name = $validated['name'] ?? null;
        $limit = (int) ($validated['limit'] ?? 10);

        $aggregates = TrailAggregate::query()
            ->where('period', $period)
            ->whereBetween('bucket', [$from, $to])
            ->when($name !== null, fn ($q) => $q->where('name', $name))
            ->get();

        if ($aggregates->isNotEmpty()) {
            return $this->fromAggregates($aggregates, $limit);
        }

        return $this->fromEvents($from, $to, $period, $name, $limit);
    }

    /**
     * @param  Collection<int, TrailAggregate>  $rows
     */
    private function fromAggregates(Collection $rows, int $limit): ResponseFactory
    {
        $topEvents = $rows
            ->groupBy('name')
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'count' => (int) $group->sum('count'),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();

        $series = $rows
            ->groupBy(fn (TrailAggregate $row): string => $row->bucket->toIso8601String())
            ->map(fn (Collection $group, string $bucket): array => [
                'bucket' => $bucket,
                'count' => (int) $group->sum('count'),
                'unique_subjects' => (int) $group->sum('unique_subjects'),
            ])
            ->sortBy('bucket')
            ->values()
            ->all();

        return Response::structured([
            'total_events' => (int) $rows->sum('count'),
            'unique_subjects' => null,
            'notes' => ['unique_subjects is not derivable from rolled-up data; query a bounded range so the events source is used for an exact count.'],
            'top_events' => $topEvents,
            'series' => $series,
            'provenance' => Provenance::aggregates($rows->max(fn (TrailAggregate $row) => $row->bucket)),
        ]);
    }

    private function fromEvents(Carbon $from, Carbon $to, string $period, ?string $name, int $limit): ResponseFactory
    {
        $inner = HttpRequest::create('/', 'GET', array_filter([
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'period' => $period,
            'name' => $name,
            'limit' => $limit,
        ], fn ($v): bool => $v !== null));

        $data = app(MetricsController::class)->index($inner);
        $data['provenance'] = Provenance::events();

        return Response::structured($data);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('ISO-8601 lower bound on occurred_at.')->required(),
            'to' => $schema->string()->description('ISO-8601 upper bound on occurred_at.')->required(),
            'period' => $schema->string()->enum(['hour', 'day', 'week', 'month'])->description('Time-series bucket size.')->default('day'),
            'name' => $schema->string()->description('Optional single event name to scope to.'),
            'limit' => $schema->integer()->description('Max number of top events to return.')->default(10),
        ];
    }
}
