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

        // Opening on the busiest event beats opening on nothing at all.
        if ($this->startEvent === '') {
            $this->startEvent = $this->paths()->mostFrequentName() ?? '';
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

    public function render(): View
    {
        $result = $this->paths()->sequences();

        $totalPages = max(1, (int) ceil($result['total'] / self::PER_PAGE));
        $this->page = max(1, min($this->page, $totalPages));

        $page = array_slice($result['rows'], ($this->page - 1) * self::PER_PAGE, self::PER_PAGE);

        return view('trail::livewire.paths', [
            'rows' => $this->displayRows($page),
            'names' => $this->paths()->namesInWindow(),
            'total' => $result['total'],
            'totalPages' => $totalPages,
            'capped' => $result['truncated'],
        ])->layout('trail::layout', ['active' => ($this->demo ? 'demo-' : '').'paths', 'title' => 'Paths']);
    }
}
