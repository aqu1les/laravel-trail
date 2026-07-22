<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Trail\Trail\Queries\PathQuery;
use Trail\Trail\Queries\SubjectIdentity;
use Trail\Trail\Queries\SubjectKey;

class Paths extends Component
{
    /** The periods the segmented control offers, as URL-safe tokens. */
    private const PERIODS = ['today', '7d', '30d'];

    /** How many actor rows a page renders. */
    private const PER_PAGE = 15;

    /** When true the screen renders sample data instead of querying. */
    public bool $demo = false;

    /** The event that anchors the cohort. Empty only when the window has no events. */
    #[Url(as: 'start')]
    public string $startEvent = '';

    /** Optional terminus. Null means "any path, however it ended". */
    #[Url(as: 'end')]
    public ?string $endEvent = null;

    /** Window token: one of self::PERIODS. */
    #[Url]
    public string $since = '7d';

    #[Url]
    public int $page = 1;

    public function mount(bool $demo = false): void
    {
        $this->demo = $demo;

        $this->guardSince();

        // Opening on the busiest event beats opening on nothing at all. Demo
        // mode never queries, so its default is a fixed name from the sample set.
        if ($this->startEvent === '') {
            $this->startEvent = $this->demo
                ? 'register'
                : ($this->paths()->mostFrequentName() ?? '');
        }

        // An empty ?end= (e.g. a hand-typed URL) means the same as "no terminus".
        if ($this->endEvent === '') {
            $this->endEvent = null;
        }

        // A path from an event to itself has no shape; drop the terminus.
        if ($this->endEvent === $this->startEvent) {
            $this->endEvent = null;
        }
    }

    public function setStart(string $name): void
    {
        $this->startEvent = $name;

        // A path from an event to itself has no shape; drop the terminus.
        if ($this->endEvent === $name) {
            $this->endEvent = null;
        }

        $this->page = 1;
    }

    public function setEnd(?string $name): void
    {
        $this->endEvent = ($name === null || $name === '' || $name === $this->startEvent) ? null : $name;
        $this->page = 1;
    }

    public function clearEnd(): void
    {
        $this->endEvent = null;
        $this->page = 1;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /** Changing the window changes the result set, so the old page number is meaningless. */
    public function updatedSince(): void
    {
        $this->guardSince();
        $this->page = 1;
    }

    // A hand-typed ?since= or a $set('since', ...) would otherwise leave every
    // segment unlit, and the invalid value would then round-trip into the URL.
    private function guardSince(): void
    {
        if (! in_array($this->since, self::PERIODS, true)) {
            $this->since = '7d';
        }
    }

    /** Lower bound of the selected period window. */
    private function sinceAt(): Carbon
    {
        return match ($this->since) {
            'today' => now()->startOfDay(),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };
    }

    /** The engine behind this render, configured from the current selection. */
    private function paths(): PathQuery
    {
        return PathQuery::inWindow($this->sinceAt())
            ->startingAt($this->startEvent)
            ->endingAt($this->endEvent);
    }

    /**
     * A gap in the compact units the screen uses: +38s, +4min, +1h, +2d.
     */
    private static function gapLabel(?int $seconds): ?string
    {
        return match (true) {
            $seconds === null => null,
            $seconds < 60 => '+'.$seconds.'s',
            $seconds < 3600 => '+'.intdiv($seconds, 60).'min',
            $seconds < 86400 => '+'.intdiv($seconds, 3600).'h',
            default => '+'.intdiv($seconds, 86400).'d',
        };
    }

    /**
     * Turn one page of engine rows into rows the view can render without
     * reaching for a formatter of its own.
     *
     * @param  list<array<string,mixed>>  $page
     * @return list<array<string,mixed>>
     */
    private function displayRows(array $page): array
    {
        /** @var list<SubjectKey> $keys */
        $keys = array_column($page, 'key');
        $identities = SubjectIdentity::resolve($keys);

        return array_map(function (array $row) use ($identities): array {
            /** @var SubjectKey $key */
            $key = $row['key'];
            $actor = SubjectIdentity::display($key, $identities);
            $last = count($row['steps']) - 1;

            return [
                'name' => $actor['name'],
                'type' => $actor['type'],
                'id' => $actor['id'],
                'initials' => Sample::initials($actor['name']),
                'href' => route('trail.timeline', ['actor' => (string) $key]),
                'when' => Sample::relative($row['last_at']->getTimestamp() * 1000),
                'completed' => $row['completed'],
                'truncated' => $row['truncated'],
                'elided' => $row['elided'],
                'steps' => array_map(fn (array $step, int $index) => [
                    'name' => $step['name'],
                    'gap' => self::gapLabel($step['gap_seconds']),
                    'is_start' => $index === 0,
                    // A row can be both completed and truncated (the terminus was
                    // found beyond maxSteps); the marker only cares whether this
                    // step is the completed path's last one, not whether it was
                    // also truncated to get there.
                    'is_end' => $row['completed'] && $index === $last,
                ], $row['steps'], array_keys($row['steps'])),
            ];
        }, $page);
    }

    /**
     * The demo rows, filtered in PHP. Sample data never touches the database,
     * so the engine is bypassed entirely here. Mirrors PathQuery::assemble()'s
     * completed/truncated/elided semantics rather than hardcoding them: a
     * sample path longer than PathQuery::DEFAULT_MAX_STEPS is genuinely cut
     * the same way, so both marker states the view can render are honestly
     * reachable. completed is true only when a terminus was requested and
     * matched - exactly as the real engine leaves it false when no end event
     * is set.
     *
     * @return list<array<string,mixed>>
     */
    private function demoRows(): array
    {
        $rows = [];
        $maxSteps = PathQuery::DEFAULT_MAX_STEPS;

        foreach (Sample::paths() as $row) {
            $names = array_column($row['steps'], 'name');

            // The LAST occurrence, then walk back over a consecutive run of the
            // same name - the same anchor rule PathQuery::assemble() uses. No
            // sample template repeats a name today, so this only guards
            // against future templates that do.
            $anchor = null;

            foreach ($names as $index => $name) {
                if ($name === $this->startEvent) {
                    $anchor = $index;
                }
            }

            if ($anchor === null) {
                continue;
            }

            while ($anchor > 0 && $names[$anchor - 1] === $this->startEvent) {
                $anchor--;
            }

            $steps = array_slice($row['steps'], $anchor);
            $completed = false;
            $truncated = false;
            $elided = 0;

            if ($this->endEvent !== null) {
                $terminus = array_search($this->endEvent, array_column($steps, 'name'), true);

                if ($terminus === false) {
                    continue;
                }

                if ($terminus < $maxSteps) {
                    // The terminus sits within the cap: nothing to elide.
                    $steps = array_slice($steps, 0, $terminus + 1);
                } else {
                    // Found past the cap: render up to maxSteps, then append the
                    // terminus. Every step in between is dropped for good and
                    // counted as elided - the sample templates never repeat a
                    // name, so each dropped step is its own run, same as the
                    // real engine counts runs, not raw events.
                    $steps = array_merge(array_slice($steps, 0, $maxSteps), [$steps[$terminus]]);
                    $truncated = true;
                    $elided = $terminus - $maxSteps;
                }

                $completed = true;
            } elseif (count($steps) > $maxSteps) {
                // No terminus configured: cut at the cap and leave the journey
                // open-ended, exactly like the real engine's no-terminus branch.
                $steps = array_slice($steps, 0, $maxSteps);
                $truncated = true;
            }

            $last = count($steps) - 1;

            $rows[] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'id' => $row['id'],
                'initials' => Sample::initials($row['name']),
                'href' => null,
                'when' => $row['when'],
                'completed' => $completed,
                'truncated' => $truncated,
                'elided' => $elided,
                'steps' => array_map(fn (array $step, int $index) => [
                    'name' => $step['name'],
                    // The anchor step's own gap is meaningless once the path is cut.
                    'gap' => $index === 0 ? null : $step['gap'],
                    'is_start' => $index === 0,
                    'is_end' => $completed && $index === $last,
                ], $steps, array_keys($steps)),
            ];
        }

        return $rows;
    }

    /**
     * The vocabulary the demo pickers offer: every name any sample path visits.
     *
     * @return list<string>
     */
    private function demoNames(): array
    {
        $names = [];

        foreach (Sample::paths() as $row) {
            foreach ($row['steps'] as $step) {
                $names[$step['name']] = true;
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * Pull $page back into range for a result set of this size, and report how
     * many pages it has. A stale ?page= from a wider window must not render blank.
     */
    private function clampPage(int $total): int
    {
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $this->page = max(1, min($this->page, $totalPages));

        return $totalPages;
    }

    public function render(): View
    {
        // Both branches produce the same four values; only the source differs.
        // Slice first, then build display rows: resolving identities (or, in
        // demo mode, just slicing) for every actor in the cohort would be up
        // to SUBJECT_CAP lookups to render fifteen rows.
        if ($this->demo) {
            $all = $this->demoRows();
            $total = count($all);
            $capped = false;
            $totalPages = $this->clampPage($total);
            $rows = array_slice($all, ($this->page - 1) * self::PER_PAGE, self::PER_PAGE);
            $names = $this->demoNames();
        } else {
            $result = $this->paths()->sequences();
            $total = $result['total'];
            $capped = $result['truncated'];
            $totalPages = $this->clampPage($total);
            $rows = $this->displayRows(
                array_slice($result['rows'], ($this->page - 1) * self::PER_PAGE, self::PER_PAGE)
            );
            $names = $this->paths()->namesInWindow();
        }

        return view('trail::livewire.paths', [
            'rows' => $rows,
            'names' => $names,
            'total' => $total,
            'totalPages' => $totalPages,
            'capped' => $capped,
        ])->layout('trail::layout', ['active' => ($this->demo ? 'demo-' : '').'paths', 'title' => 'Paths']);
    }
}
