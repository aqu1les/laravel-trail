<?php

declare(strict_types=1);

namespace Trail\Trail\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;

/**
 * One subject's activity: the timeline rows, the type chips, and the stats
 * panel. The stats are aggregated in SQL so they describe the subject's whole
 * history rather than the capped slice the timeline happens to render.
 *
 * @internal Not covered by the package's backwards-compatibility promise.
 */
final class SubjectActivityQuery
{
    private bool $includePageViews = false;

    private function __construct(private readonly SubjectKey $subject) {}

    public static function for(SubjectKey $subject): self
    {
        return new self($subject);
    }

    public function includingPageViews(bool $include): self
    {
        $this->includePageViews = $include;

        return $this;
    }

    /**
     * The timeline rows, filtered in SQL before the cap so a chip can surface
     * an event older than the subject's newest $cap rows.
     *
     * @param  list<string>  $types  chip selection; empty means every type
     * @return Collection<int, TrailEvent>
     */
    public function events(array $types, int $cap): Collection
    {
        $query = $this->stream();

        if ($types !== []) {
            $query->whereIn('name', $types);
        }

        return $query->with('subject')->limit($cap)->get();
    }

    /**
     * Every name the subject ever emitted, so the row cap cannot hide a type
     * from the chips. Honours the page-view toggle like every other read here.
     *
     * @return list<string>
     */
    public function distinctNames(): array
    {
        return $this->stream()->reorder()
            ->distinct()
            ->pluck('name')
            ->all();
    }

    /**
     * Event counts by name, most frequent first.
     *
     * @return array<string, int>
     */
    public function totals(): array
    {
        return $this->stream()->reorder()
            ->selectRaw('name, count(*) as aggregate')
            ->groupBy('name')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'name')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * First and last activity, as raw datetime strings.
     *
     * @return array{first: ?string, last: ?string}
     */
    public function bounds(): array
    {
        $row = $this->stream()->reorder()
            ->selectRaw('min(occurred_at) as first_at, max(occurred_at) as last_at')
            ->toBase()->first();

        return [
            'first' => $row->first_at ?? null,
            'last' => $row->last_at ?? null,
        ];
    }

    /**
     * Daily counts over the trailing $days days, grouped in SQL.
     *
     * @return array<int, int> keyed by day-start timestamp in ms
     */
    public function dailyCounts(int $days): array
    {
        $rows = $this->stream()->reorder()
            ->selectRaw('date(occurred_at) as day, count(*) as aggregate')
            ->where('occurred_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $byDay = [];
        foreach ($rows as $day => $count) {
            $byDay[(int) (strtotime((string) $day) * 1000)] = (int) $count;
        }

        return $byDay;
    }

    /**
     * The subject's events, minus page views when they are hidden. The chips
     * are not applied here: the stats panel should not shift as the reader
     * toggles a type.
     *
     * @return Builder<TrailEvent>
     */
    private function stream(): Builder
    {
        $query = $this->subject->applyTo((new EventQuery)->toBuilder());

        if (! $this->includePageViews) {
            $query->where('name', '!=', Trail::pageViewName());
        }

        return $query;
    }
}
