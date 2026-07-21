<?php

declare(strict_types=1);

namespace Trail\Trail\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;

/**
 * The event stream behind the Events screen: a time window, narrowed by the
 * active filters, plus the facets its filter menus offer.
 *
 * Every filter is applied in SQL before the row cap. Capping first and then
 * filtering in PHP would silently bound the screen to the newest N rows, so a
 * filter could never reach an older event inside the same window.
 *
 * @internal Not covered by the package's backwards-compatibility promise.
 */
final class EventStreamQuery
{
    /** Cap on how many subject rows a single morph type contributes to a search. */
    private const SUBJECT_SEARCH_CAP = 500;

    private bool $includePageViews = false;

    /** @var list<string> */
    private array $names = [];

    private ?SubjectKey $actor = null;

    private string $term = '';

    private function __construct(private readonly Carbon $since) {}

    public static function inWindow(Carbon $since): self
    {
        return new self($since);
    }

    public function includingPageViews(bool $include): self
    {
        $this->includePageViews = $include;

        return $this;
    }

    /** @param  list<string>  $names */
    public function onlyNames(array $names): self
    {
        $this->names = $names;

        return $this;
    }

    public function byActor(?SubjectKey $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function matching(string $term): self
    {
        $this->term = $term;

        return $this;
    }

    /**
     * The rows the table renders, newest-first.
     *
     * @return Collection<int, TrailEvent>
     */
    public function rows(int $cap): Collection
    {
        $query = $this->window();

        if (! $this->includePageViews) {
            $query->where('name', '!=', Trail::pageViewName());
        }

        if ($this->names !== []) {
            $query->whereIn('name', $this->names);
        }

        if ($this->actor !== null) {
            $this->actor->applyTo($query);
        }

        if ($this->term !== '') {
            $this->applySearch($query, $this->term);
        }

        return $query->with('subject')->limit($cap)->get();
    }

    /**
     * Every event name in the window.
     *
     * Named "InWindow" because it deliberately ignores the configured filters:
     * a menu that only offered what the active filters left visible would be a
     * dead end, since you could never widen the selection again.
     *
     * @return list<string>
     */
    public function namesInWindow(): array
    {
        return $this->window()->reorder()
            ->where('name', '!=', Trail::pageViewName())
            ->distinct()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The most recently seen subjects in the window, for the actor menu.
     * Ignores the configured filters, for the same reason namesInWindow() does.
     *
     * @return list<SubjectKey>
     */
    public function subjectsInWindow(int $cap): array
    {
        return $this->window()->reorder()
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_id')
            ->select('subject_type', 'subject_id')
            ->groupBy('subject_type', 'subject_id')
            ->orderByRaw('max(occurred_at) desc')
            ->limit($cap)
            ->get()
            ->map(fn (TrailEvent $row) => SubjectKey::of($row->subject_type, $row->subject_id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The selected period, newest-first and unfiltered. rows() layers the
     * active filters on top; the *InWindow() facets deliberately do not.
     *
     * @return Builder<TrailEvent>
     */
    private function window(): Builder
    {
        return (new EventQuery)->toBuilder()->where('occurred_at', '>=', $this->since);
    }

    /**
     * Match the term against the event name, its properties, or the actor.
     *
     * whereLike() keeps this case-insensitive on every driver (it compiles to
     * ilike on Postgres) and casts json and integer columns to text for us.
     *
     * @param  Builder<TrailEvent>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';
        $actors = SubjectSearch::matchingIds(
            $term,
            SubjectSearch::distinctTypes($this->window()),
            self::SUBJECT_SEARCH_CAP,
        );

        $query->where(function (Builder $query) use ($like, $actors): void {
            $query->whereLike('name', $like)
                ->orWhereLike('properties', $like)
                ->orWhereLike('subject_id', $like);

            foreach ($actors as $type => $ids) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('subject_type', $type)
                    ->whereIn('subject_id', $ids));
            }
        });
    }
}
