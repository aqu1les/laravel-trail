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

    /**
     * How many of a subject's in-window events assemble() will scan looking for
     * the end event once maxSteps is behind it. A hard bound so a pathological
     * subject (thousands of events in the window) cannot make a single read
     * unbounded.
     */
    public const SCAN_CAP = 1000;

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
        // An empty string means unset, same as startingAt(''): a screen bound to a
        // "" terminus would match nothing and silently empty the whole result.
        $this->endEvent = $name === '' ? null : $name;

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
     * When a row is both completed and truncated, the appended terminus step
     * was found past maxSteps. `elided` carries how many distinct consecutive
     * event runs were dropped between the last rendered step and that
     * terminus (0 when the terminus sat at exactly maxSteps + 1, or when the
     * row was not truncated at all), so a consumer renders an elision marker
     * off that count directly rather than inferring it from
     * truncated/completed. It counts runs, not raw events: a dropped run of
     * repeated events (with collapseRepeats on) counts once, the same way a
     * rendered run counts as a single step.
     *
     * @return array{rows: list<array{key: SubjectKey, steps: list<array{name: string, occurred_at: Carbon, gap_seconds: int|null}>, completed: bool, truncated: bool, elided: int, last_at: Carbon}>, total: int, truncated: bool}
     */
    public function sequences(): array
    {
        $empty = ['rows' => [], 'total' => 0, 'truncated' => false];

        if ($this->startEvent === '') {
            return $empty;
        }

        $cohort = $this->cohort();

        if ($cohort['tokens'] === []) {
            return $empty;
        }

        $eventsBySubject = $this->eventsFor($cohort['tokens']);
        $rows = [];

        // Iterate the cohort, not the grouped events: the cohort already carries
        // the recency ordering the screen renders in.
        foreach ($cohort['tokens'] as $token) {
            $row = $this->assemble($token, $eventsBySubject[$token] ?? []);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return [
            'rows' => $rows,
            'total' => count($rows),
            'truncated' => $cohort['truncated'],
        ];
    }

    /**
     * The subjects that fired the start event inside the window, most recent
     * starter first, as "type|id" tokens.
     *
     * Reads one row past SUBJECT_CAP so truncation can be told from "exactly
     * at the cap": counting the kept rows against the cap would flag an
     * untruncated, exactly-full cohort as truncated.
     *
     * @return array{tokens: list<string>, truncated: bool}
     */
    private function cohort(): array
    {
        $rows = $this->window()->reorder()
            ->where('name', $this->startEvent)
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_id')
            ->select('subject_type', 'subject_id')
            ->groupBy('subject_type', 'subject_id')
            ->orderByRaw('max(occurred_at) desc')
            ->orderBy('subject_type')
            ->orderBy('subject_id')
            ->limit(self::SUBJECT_CAP + 1)
            ->get();

        $truncated = $rows->count() > self::SUBJECT_CAP;

        $tokens = $rows->take(self::SUBJECT_CAP)
            ->map(fn (TrailEvent $row) => SubjectKey::of($row->subject_type, $row->subject_id))
            ->filter()
            ->map(fn (SubjectKey $key) => (string) $key)
            ->values()
            ->all();

        return ['tokens' => $tokens, 'truncated' => $truncated];
    }

    /**
     * Every in-window event for the cohort, grouped by subject and oldest first.
     *
     * The ids are grouped by subject_type and applied as one OR clause per type:
     * a composite whereIn over a column pair is not portable across the three
     * drivers the package supports. EventStreamQuery::applySearch does the same.
     *
     * Only the four columns assemble() reads are selected: hydrating the
     * properties/context JSON casts and the value decimal cast for up to
     * SUBJECT_CAP subjects' whole in-window history is pure waste here.
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
            ->select('subject_type', 'subject_id', 'name', 'occurred_at', 'id')
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
     * @return array{key: SubjectKey, steps: list<array{name: string, occurred_at: Carbon, gap_seconds: int|null}>, completed: bool, truncated: bool, elided: int, last_at: Carbon}|null
     */
    private function assemble(string $token, array $events): ?array
    {
        $key = SubjectKey::parse($token);
        $anchor = null;

        // The LAST occurrence, not the first: cohort() orders subjects by their
        // most recent start (max(occurred_at) desc), so anchoring on an earlier
        // occurrence would render a stale journey under a recency-ordered row.
        foreach ($events as $index => $event) {
            if ($event->name === $this->startEvent) {
                $anchor = $index;
            }
        }

        if ($key === null || $anchor === null) {
            return null;
        }

        // When collapsing repeats, a run of the start event dates at its FIRST
        // event everywhere else in this method (see the collapse branch below),
        // so the anchor must agree: walk it back over any unbroken run of the
        // same event immediately preceding it. This only spans a consecutive
        // run - it stops at the first gap - so a separate, earlier start
        // occurrence (not part of this run) still loses to the last-occurrence
        // rule above. With collapseRepeats off there is no run dating to keep
        // consistent with, so the anchor stays on the exact last occurrence.
        if ($this->collapseRepeats) {
            while ($anchor > 0 && $events[$anchor - 1]->name === $this->startEvent) {
                $anchor--;
            }
        }

        $steps = [];
        $previousName = null;
        $previousAt = null;
        // The at of the last event actually scanned, regardless of whether it was
        // collapsed away or rendered as a step. Only used to date the terminus
        // step when scanning has continued past maxSteps (see below); everywhere
        // else gaps are measured against $previousAt, i.e. against the last
        // rendered step, so a collapsed run's gap is measured from its first
        // occurrence and sums back to the total path duration.
        $rawPreviousAt = null;
        $completed = false;
        $truncated = false;
        // How many distinct consecutive event runs were scanned and dropped,
        // without being rendered as a step or as the terminus, between the
        // last rendered step and the appended terminus. Only incremented in
        // the "no terminus yet" branch below, and only once per run (a
        // repeat of the run's own name collapses via the check above this
        // branch, before it can be counted again); a terminus found
        // immediately (maxSteps + 1) leaves this at 0.
        $elided = 0;
        $scanned = 0;

        foreach (array_slice($events, $anchor) as $event) {
            if ($scanned >= self::SCAN_CAP) {
                break;
            }

            $scanned++;

            $at = $event->occurred_at;
            $isTerminus = $this->endEvent !== null && $event->name === $this->endEvent;

            // A run of the same event is one step: noisy tracking should not
            // push the interesting tail past maxSteps.
            if ($this->collapseRepeats && $event->name === $previousName) {
                $rawPreviousAt = $at;

                continue;
            }

            if (count($steps) >= $this->maxSteps) {
                $truncated = true;

                if ($this->endEvent === null) {
                    break;
                }

                if (! $isTerminus) {
                    // No terminus yet: keep scanning past the cap, but stop
                    // rendering steps, so a converting subject is not confused
                    // with one that never converts. This event is dropped for
                    // good (never rendered), so it counts toward elided.
                    $previousName = $event->name;
                    $rawPreviousAt = $at;
                    $elided++;

                    continue;
                }

                // Found it past the cap: render it as the closing step. When
                // events were actually elided, its gap is measured from the
                // event that actually preceded it in the source stream ($rawPreviousAt),
                // not from the last rendered step, because the ellipsis chip sits
                // between them and the gap describes "elided run -> terminus".
                // But when nothing was elided ($elided === 0, e.g. the terminus sat
                // at exactly maxSteps + 1), collapseRepeats may still have swallowed
                // a run of the last rendered step's own name without incrementing
                // elided (a repeat collapse just updates $rawPreviousAt and
                // continues). In that case there is no ellipsis chip to anchor a
                // "from the raw last event" gap against, so the gap must be measured
                // from $previousAt, the first event of that last rendered run - the
                // same convention every other gap in this method follows, so gaps
                // keep summing back to the total path duration.
                $terminusFrom = $elided === 0 ? $previousAt : $rawPreviousAt;

                $steps[] = [
                    'name' => $event->name,
                    'occurred_at' => $at,
                    'gap_seconds' => $terminusFrom === null
                        ? null
                        : max(0, $at->getTimestamp() - $terminusFrom->getTimestamp()),
                ];

                $completed = true;
                break;
            }

            $steps[] = [
                'name' => $event->name,
                'occurred_at' => $at,
                'gap_seconds' => $previousAt === null
                    ? null
                    : max(0, $at->getTimestamp() - $previousAt->getTimestamp()),
            ];

            $previousName = $event->name;
            $previousAt = $at;
            $rawPreviousAt = $at;

            if ($isTerminus) {
                $completed = true;
                break;
            }
        }

        // steps is never empty here: maxSteps() enforces at least 1, and the
        // anchor event itself is always accepted as the first step before any
        // cap or collapse check can apply.
        // With a terminus set, a path that never got there is not a path at all.
        if ($this->endEvent !== null && ! $completed) {
            return null;
        }

        return [
            'key' => $key,
            'steps' => $steps,
            'completed' => $completed,
            'truncated' => $truncated,
            'elided' => $elided,
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
