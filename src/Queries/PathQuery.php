<?php

declare(strict_types=1);

namespace Trail\Trail\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;

/**
 * Path discovery: the real ordered journeys subjects took after a start event.
 *
 * The inverse of FunnelReport. Instead of scoring a sequence you already know,
 * it reconstructs the sequences from the data.
 *
 * The cohort is bounded in SQL before anything is read, so a busy window costs
 * at most SUBJECT_CAP subjects' history rather than the whole table. Assembly
 * runs in PHP on purpose: ordering a path per subject would otherwise need
 * window functions, which SQLite and older MySQL do not offer.
 *
 * @internal Not covered by the package's backwards-compatibility promise.
 */
final class PathQuery
{
    /** How many subjects a single read may reconstruct. */
    public const SUBJECT_CAP = 1000;

    /** How many steps a single path may carry before it is cut. */
    private const DEFAULT_MAX_STEPS = 8;

    private string $startEvent = '';

    private ?string $endEvent = null;

    private int $maxSteps = self::DEFAULT_MAX_STEPS;

    private bool $collapseRepeats = true;

    private function __construct(private readonly Carbon $since) {}

    public static function inWindow(Carbon $since): self
    {
        return new self($since);
    }

    /** The event that anchors the cohort. Without it, sequences() reads nothing. */
    public function startingAt(string $name): self
    {
        $this->startEvent = $name;

        return $this;
    }

    /** Optional terminus: paths are cut here, and paths that never reach it are dropped. */
    public function endingAt(?string $name): self
    {
        $this->endEvent = $name;

        return $this;
    }

    public function maxSteps(int $steps): self
    {
        $this->maxSteps = max(1, $steps);

        return $this;
    }

    public function collapseRepeats(bool $collapse): self
    {
        $this->collapseRepeats = $collapse;

        return $this;
    }

    /**
     * Every event name in the window, for the two pickers.
     *
     * Deliberately ignores the configured start and end, for the same reason
     * EventStreamQuery::namesInWindow does: a menu narrowed by the active
     * selection would be a dead end you could never widen again.
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
     * The busiest event name in the window, which the screen opens on. Ties
     * break by name so the default view is stable across renders.
     */
    public function mostFrequentName(): ?string
    {
        $row = $this->window()->reorder()
            ->where('name', '!=', Trail::pageViewName())
            ->select('name')
            ->groupBy('name')
            ->orderByRaw('count(*) desc')
            ->orderBy('name')
            ->limit(1)
            ->first();

        return $row?->name;
    }

    /**
     * One reconstructed path per subject in the cohort, newest starter first.
     *
     * @return array{rows: list<array{key: SubjectKey, steps: list<array{name: string, occurred_at: Carbon, gap_seconds: int|null}>, completed: bool, truncated: bool, last_at: Carbon}>, total: int, truncated: bool}
     */
    public function sequences(): array
    {
        $empty = ['rows' => [], 'total' => 0, 'truncated' => false];

        if ($this->startEvent === '') {
            return $empty;
        }

        $cohort = $this->cohort();

        if ($cohort === []) {
            return $empty;
        }

        $eventsBySubject = $this->eventsFor($cohort);
        $rows = [];

        // Iterate the cohort, not the grouped events: the cohort already carries
        // the recency ordering the screen renders in.
        foreach ($cohort as $token) {
            $row = $this->assemble($token, $eventsBySubject[$token] ?? []);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return [
            'rows' => $rows,
            'total' => count($rows),
            'truncated' => count($cohort) >= self::SUBJECT_CAP,
        ];
    }

    /**
     * The subjects that fired the start event inside the window, most recent
     * starter first, as "type|id" tokens.
     *
     * @return list<string>
     */
    private function cohort(): array
    {
        return $this->window()->reorder()
            ->where('name', $this->startEvent)
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_id')
            ->select('subject_type', 'subject_id')
            ->groupBy('subject_type', 'subject_id')
            ->orderByRaw('max(occurred_at) desc')
            ->orderBy('subject_type')
            ->orderBy('subject_id')
            ->limit(self::SUBJECT_CAP)
            ->get()
            ->map(fn (TrailEvent $row) => SubjectKey::of($row->subject_type, $row->subject_id))
            ->filter()
            ->map(fn (SubjectKey $key) => (string) $key)
            ->values()
            ->all();
    }

    /**
     * Every in-window event for the cohort, grouped by subject and oldest first.
     *
     * The ids are grouped by subject_type and applied as one OR clause per type:
     * a composite whereIn over a column pair is not portable across the three
     * drivers the package supports. EventStreamQuery::applySearch does the same.
     *
     * @param  list<string>  $cohort
     * @return array<string, list<TrailEvent>>
     */
    private function eventsFor(array $cohort): array
    {
        $idsByType = [];

        foreach ($cohort as $token) {
            $key = SubjectKey::parse($token);

            if ($key !== null) {
                $idsByType[$key->type][] = $key->id;
            }
        }

        $rows = $this->window()->reorder()
            ->where('name', '!=', Trail::pageViewName())
            ->where(function (Builder $query) use ($idsByType): void {
                foreach ($idsByType as $type => $ids) {
                    $query->orWhere(fn (Builder $inner) => $inner
                        ->where('subject_type', $type)
                        ->whereIn('subject_id', array_values(array_unique($ids))));
                }
            })
            ->orderBy('subject_type')
            ->orderBy('subject_id')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->subject_type.'|'.$row->subject_id][] = $row;
        }

        return $grouped;
    }

    /**
     * One subject's ordered events, cut into the path the screen renders.
     * Returns null when the subject does not belong on the screen at all.
     *
     * @param  list<TrailEvent>  $events  oldest first
     * @return array{key: SubjectKey, steps: list<array{name: string, occurred_at: Carbon, gap_seconds: int|null}>, completed: bool, truncated: bool, last_at: Carbon}|null
     */
    private function assemble(string $token, array $events): ?array
    {
        $key = SubjectKey::parse($token);
        $anchor = null;

        foreach ($events as $index => $event) {
            if ($event->name === $this->startEvent) {
                $anchor = $index;
                break;
            }
        }

        if ($key === null || $anchor === null) {
            return null;
        }

        $steps = [];
        $previousName = null;
        $previousAt = null;
        $completed = false;
        $truncated = false;

        foreach (array_slice($events, $anchor) as $event) {
            // A run of the same event is one step: noisy tracking should not
            // push the interesting tail past maxSteps.
            if ($this->collapseRepeats && $event->name === $previousName) {
                continue;
            }

            if (count($steps) >= $this->maxSteps) {
                $truncated = true;
                break;
            }

            $at = $event->occurred_at;

            $steps[] = [
                'name' => $event->name,
                'occurred_at' => $at,
                'gap_seconds' => $previousAt === null
                    ? null
                    : max(0, $at->getTimestamp() - $previousAt->getTimestamp()),
            ];

            $previousName = $event->name;
            $previousAt = $at;

            if ($this->endEvent !== null && $event->name === $this->endEvent) {
                $completed = true;
                break;
            }
        }

        // With a terminus set, a path that never got there is not a path at all.
        if ($steps === [] || ($this->endEvent !== null && ! $completed)) {
            return null;
        }

        return [
            'key' => $key,
            'steps' => $steps,
            'completed' => $completed,
            'truncated' => $truncated,
            'last_at' => $steps[count($steps) - 1]['occurred_at'],
        ];
    }

    /**
     * The selected period, unfiltered. Every read layers its own clauses on top.
     *
     * @return Builder<TrailEvent>
     */
    private function window(): Builder
    {
        return (new EventQuery)->toBuilder()->where('occurred_at', '>=', $this->since);
    }
}
