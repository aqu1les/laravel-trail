<?php

declare(strict_types=1);

namespace Trail\Trail\Queries;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Trail\Trail\Models\TrailEvent;

/**
 * The actors index: distinct subjects ranked by recency, with a type filter,
 * a text search, and pagination. Counting, ordering, filtering and paging all
 * run in SQL; identities are resolved for the current page only.
 *
 * @internal Not covered by the package's backwards-compatibility promise.
 */
final class SubjectIndexQuery
{
    /** Cap on how many subject rows a single morph type contributes to a search. */
    private const SEARCH_CAP = 1000;

    private string $term = '';

    private string $typeFilter = '';

    /** @var array<string, list<mixed>>|null Resolved once; the count and page queries share it. */
    private ?array $matchedByType = null;

    /** @var list<string>|null Resolved once; the type filter and the search share it. */
    private ?array $types = null;

    private function __construct() {}

    public static function make(): self
    {
        return new self;
    }

    public function matching(string $term): self
    {
        $this->term = trim($term);

        return $this;
    }

    public function ofType(string $type): self
    {
        $this->typeFilter = $type;

        return $this;
    }

    /**
     * The options the index's type filter offers, as {value,label} pairs
     * sorted by label.
     *
     * @return list<array{value: string, label: string}>
     */
    public function typeFilterOptions(): array
    {
        return collect($this->subjectTypes())
            ->map(fn (string $type) => ['value' => $type, 'label' => class_basename($type)])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * The distinct subject types, resolved once per instance: the type filter's
     * options and the text search both need them.
     *
     * @return list<string>
     */
    private function subjectTypes(): array
    {
        return $this->types ??= SubjectSearch::distinctTypes((new EventQuery)->toBuilder());
    }

    /**
     * One page of the index.
     *
     * @return array{actors: list<array<string,mixed>>, total: int, page: int, totalPages: int}
     */
    public function page(int $page, int $perPage): array
    {
        // Total distinct subjects, via a grouped subquery (portable count of groups).
        $countSub = $this->applyFilters((new EventQuery)->toBuilder()->reorder())
            ->selectRaw('1')
            ->groupBy('subject_type', 'subject_id');
        $total = DB::query()->fromSub($countSub, 'sub')->count();

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));

        $rows = $this->applyFilters((new EventQuery)->toBuilder()->reorder())
            ->selectRaw('subject_type, subject_id, count(*) as total, max(occurred_at) as last_seen')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('last_seen')
            ->orderBy('subject_type')
            ->orderByDesc('subject_id')
            ->limit($perPage)->offset(($page - 1) * $perPage)
            ->toBase()->get();

        $keys = $rows->map(fn ($row) => SubjectKey::of($row->subject_type, $row->subject_id))
            ->filter()->values()->all();
        $identities = SubjectIdentity::resolve($keys);

        $actors = $rows->map(fn ($row) => SubjectIdentity::displayRow($row->subject_type, $row->subject_id, $identities) + [
            'total' => (int) $row->total,
            'last_seen' => $row->last_seen !== null
                ? (int) (strtotime((string) $row->last_seen) * 1000)
                : null,
        ])->all();

        return [
            'actors' => $actors,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * The most active subjects, for the timeline's switcher shortcut list. With
     * a term it searches the whole table instead, so a quiet subject stays
     * reachable rather than being ranked off the list.
     *
     * @return list<array<string,mixed>>
     */
    public function mostActive(int $cap): array
    {
        $rows = $this->applyFilters((new EventQuery)->toBuilder()->reorder(), withTypeFilter: false)
            ->selectRaw('subject_type, subject_id, count(*) as aggregate')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('aggregate')
            ->limit($cap)
            ->get();

        $identities = SubjectIdentity::resolve(
            $rows->map(fn (TrailEvent $row) => SubjectKey::of($row->subject_type, $row->subject_id))
                ->filter()->values()->all()
        );

        return $rows->map(fn (TrailEvent $row) => SubjectIdentity::displayRow(
            $row->subject_type, $row->subject_id, $identities
        ))->all();
    }

    /**
     * The shared WHERE clause, so the count and the page query stay in sync.
     *
     * @param  Builder<TrailEvent>  $query
     * @return Builder<TrailEvent>
     */
    private function applyFilters(Builder $query, bool $withTypeFilter = true): Builder
    {
        $query->whereNotNull('subject_id');

        if ($withTypeFilter && $this->typeFilter !== '') {
            $query->where('subject_type', $this->typeFilter);
        }

        if ($this->term !== '') {
            $query->where($this->searchPredicate());
        }

        return $query;
    }

    /**
     * Match the subject id directly (numeric terms) plus the ids whose identity
     * matched in each morph type's own table. An empty match set collapses to
     * "no rows", so a non-numeric miss never returns everything.
     */
    private function searchPredicate(): Closure
    {
        $term = $this->term;
        $matchedByType = $this->matchedByType ??= SubjectSearch::matchingIds(
            $term,
            $this->subjectTypes(),
            self::SEARCH_CAP,
        );

        return function ($query) use ($term, $matchedByType): void {
            $matched = false;

            if (ctype_digit($term)) {
                $query->orWhere('subject_id', (int) $term);
                $matched = true;
            }

            foreach ($matchedByType as $type => $ids) {
                $query->orWhere(fn ($inner) => $inner->where('subject_type', $type)->whereIn('subject_id', $ids));
                $matched = true;
            }

            if (! $matched) {
                $query->whereRaw('1 = 0');
            }
        };
    }
}
