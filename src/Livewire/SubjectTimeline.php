<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Livewire\Concerns\ResolvesEvents;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Queries\SubjectActivityQuery;
use Trail\Trail\Queries\SubjectIdentity;
use Trail\Trail\Queries\SubjectIndexQuery;
use Trail\Trail\Queries\SubjectKey;

class SubjectTimeline extends Component
{
    use ResolvesEvents;

    private const INDEX_PER_PAGE = 25;

    /** How many rows a single actor's timeline renders at most. */
    private const TIMELINE_CAP = 300;

    /** How many actors the switcher shortcut list offers. */
    private const SWITCHER_CAP = 50;

    /** How many days the activity bar chart covers. */
    private const BAR_DAYS = 7;

    public bool $demo = false;

    /** Selection token: a Sample actor id (demo) or "subject_type|subject_id" (real). */
    #[Url(as: 'actor')]
    public string $actorId = '';

    public string $actorSearch = '';

    /** @var list<string> */
    public array $activeTypes = [];

    public bool $showPageViews = false;

    /** @var list<array<string,mixed>> Demo history buffer (demo mode only). */
    public array $demoEvents = [];

    // Index mode filters and pagination (real mode, no actor selected).
    #[Url(as: 'type_filter')]
    public string $typeFilter = '';

    #[Url(as: 'q')]
    public string $indexSearch = '';

    #[Url]
    public int $page = 1;

    public function mount(bool $demo = false): void
    {
        $this->demo = $demo;

        if ($this->demo) {
            $this->actorId = Sample::actors()[0]['id'];
            $this->demoEvents = Sample::actorHistory($this->demoActor());
        }
        // Real mode: actorId stays '' if not in URL, which shows the actors index.
    }

    public function selectActor(string $id): void
    {
        $this->actorId = $id;
        $this->activeTypes = [];
        $this->actorSearch = '';

        if ($this->demo) {
            $this->demoEvents = Sample::actorHistory($this->demoActor());
        }
    }

    public function toggleType(string $name): void
    {
        if (in_array($name, $this->activeTypes, true)) {
            $this->activeTypes = array_values(array_diff($this->activeTypes, [$name]));
        } else {
            $this->activeTypes[] = $name;
        }
    }

    public function togglePageViews(): void
    {
        $this->showPageViews = ! $this->showPageViews;
    }

    public function filterByType(string $type): void
    {
        $this->typeFilter = $type;
        $this->page = 1;
    }

    public function clearIndex(): void
    {
        $this->typeFilter = '';
        $this->indexSearch = '';
        $this->page = 1;
    }

    public function updatedIndexSearch(): void
    {
        $this->page = 1;
    }

    private function demoActor(): array
    {
        $actor = collect(Sample::actors())->firstWhere('id', $this->actorId) ?? Sample::actors()[0];

        return $actor + ['key' => $actor['id']];
    }

    /**
     * One page of the actors index, plus the options its type filter offers.
     *
     * @return array{actors: list<array<string,mixed>>, total: int, page: int, totalPages: int, distinctTypes: list<array{value: string, label: string}>}
     */
    private function indexActors(): array
    {
        $index = SubjectIndexQuery::make()
            ->matching($this->indexSearch)
            ->ofType($this->typeFilter);

        return [
            ...$index->page($this->page, self::INDEX_PER_PAGE),
            'distinctTypes' => $index->typeFilterOptions(),
        ];
    }

    /**
     * The switcher's shortcut list: most active subjects, or a database search
     * when a term is typed so a quiet subject stays reachable.
     *
     * @return list<array<string,mixed>>
     */
    private function realActors(string $search = ''): array
    {
        return SubjectIndexQuery::make()->matching($search)->mostActive(self::SWITCHER_CAP);
    }

    /** Resolve a selected actor by its key, regardless of activity ranking. */
    private function resolveActor(string $token): array
    {
        $key = SubjectKey::parse($token);

        if ($key === null) {
            return SubjectIdentity::anonymous();
        }

        return SubjectIdentity::display($key, SubjectIdentity::resolve([$key]));
    }

    public function render(): View
    {
        if (! $this->demo && $this->actorId === '') {
            return view('trail::livewire.subject-timeline', [
                'indexMode' => true,
                ...$this->indexActors(),
            ])->layout('trail::layout', ['active' => 'timeline', 'title' => 'Subject Timeline']);
        }

        if ($this->demo) {
            $actor = $this->demoActor();
            $rows = $this->filteredDemoEvents();
            $stats = $this->statsFromEvents($this->demoStatsEvents());
            $types = $this->typesFrom(collect($this->demoEvents)->pluck('name')->all());
            $results = array_map(fn ($a) => $a + ['key' => $a['id']], Sample::actors());
        } else {
            $key = SubjectKey::parse($this->actorId);
            $actor = $this->resolveActor($this->actorId);
            $rows = $this->realEventsFor($key);
            $stats = $this->realStatsFor($key);
            $types = $this->realTypesFor($key);
            // No term: the activity-ranked shortcut list. With a term: a database
            // search so any actor is reachable, not just the most active ones.
            $results = $this->realActors($this->actorSearch);
        }

        $groups = [];
        foreach ($rows as $e) {
            $label = Sample::dayLabel($e['ts']);
            if ($groups === [] || end($groups)['label'] !== $label) {
                $groups[] = ['label' => $label, 'date' => Sample::fullDate($e['ts']), 'items' => []];
            }
            $groups[array_key_last($groups)]['items'][] = $e + [
                'clock' => Sample::clock($e['ts']),
                'relative' => Sample::relative($e['ts']),
            ];
        }

        return view('trail::livewire.subject-timeline', [
            'indexMode' => false,
            'actor' => $actor,
            'groups' => $groups,
            'types' => $types,
            'events' => $rows,
            'empty' => $rows === [],
            'stats' => $stats,
            'results' => $results,
        ])->layout('trail::layout', ['active' => ($this->demo ? 'demo-' : '').'timeline', 'title' => 'Subject Timeline']);
    }

    /**
     * The type chips, from a list of event names.
     *
     * Page views never get a chip, whatever the toggle says: they have their own
     * dedicated button, so offering both would be two controls for one thing.
     * That is a UI rule, separate from the query-level page-view filter.
     *
     * @param  list<string>  $names
     * @return list<array<string,mixed>>
     */
    private function typesFrom(array $names): array
    {
        return collect($names)->reject(fn ($name) => $name === Trail::pageViewName())
            ->unique()->sort()->values()
            ->map(fn ($name) => [
                'name' => $name,
                'color' => Sample::colorFor($name),
                'on' => in_array($name, $this->activeTypes, true),
            ])->all();
    }

    /**
     * The demo buffer narrowed by the chips and the page-view toggle.
     *
     * @return list<array<string,mixed>>
     */
    private function filteredDemoEvents(): array
    {
        $events = $this->activeTypes !== []
            ? array_values(array_filter($this->demoEvents, fn ($e) => in_array($e['name'], $this->activeTypes, true)))
            : $this->demoEvents;

        if (! $this->showPageViews) {
            $events = array_values(array_filter($events, fn ($e) => $e['name'] !== Trail::pageViewName()));
        }

        return $events;
    }

    /**
     * The demo buffer the stats panel summarises: the chips do not narrow it,
     * the page-view toggle does.
     *
     * @return list<array<string,mixed>>
     */
    private function demoStatsEvents(): array
    {
        return $this->showPageViews
            ? $this->demoEvents
            : array_values(array_filter($this->demoEvents, fn ($e) => $e['name'] !== Trail::pageViewName()));
    }

    /** The activity query for the selected actor, honouring the page-view toggle. */
    private function activity(SubjectKey $key): SubjectActivityQuery
    {
        return SubjectActivityQuery::for($key)->includingPageViews($this->showPageViews);
    }

    /**
     * The rows the timeline renders: filtered in SQL before the cap, so a chip
     * can surface an event older than the actor's newest rows.
     *
     * @return list<array<string,mixed>>
     */
    private function realEventsFor(?SubjectKey $key): array
    {
        if ($key === null) {
            return [];
        }

        return $this->activity($key)->events($this->activeTypes, self::TIMELINE_CAP)
            ->map(fn (TrailEvent $e) => $this->normalizeEvent($e))->all();
    }

    /**
     * The chips offered for an actor: every name they ever emitted, so the row
     * cap does not hide a type from the filter.
     *
     * @return list<array<string,mixed>>
     */
    private function realTypesFor(?SubjectKey $key): array
    {
        return $key === null ? [] : $this->typesFrom($this->activity($key)->distinctNames());
    }

    /**
     * The stats panel, aggregated in SQL so it covers the actor's whole history
     * rather than the capped slice the timeline happens to render.
     *
     * @return array<string,mixed>
     */
    private function realStatsFor(?SubjectKey $key): array
    {
        if ($key === null) {
            return $this->statsFromEvents([]);
        }

        $activity = $this->activity($key);
        $counts = $activity->totals();
        ['first' => $first, 'last' => $last] = $activity->bounds();

        return [
            'total' => array_sum($counts),
            'sessions' => $counts['session.started'] ?? 0,
            'first' => $first === null ? '-' : Sample::fullDate((int) (strtotime($first) * 1000)),
            'last' => $last === null ? '-' : Sample::relative((int) (strtotime($last) * 1000)),
            'top_event' => array_key_first($counts) ?? '-',
            ...$this->barsFrom($activity->dailyCounts(self::BAR_DAYS)),
        ];
    }

    /**
     * The stats panel for an in-memory event list (demo mode).
     *
     * @param  list<array<string,mixed>>  $events
     * @return array<string,mixed>
     */
    private function statsFromEvents(array $events): array
    {
        $ts = array_column($events, 'ts');
        $counts = array_count_values(array_column($events, 'name'));
        arsort($counts);

        $byDay = [];
        foreach ($events as $e) {
            $k = (int) (strtotime(date('Y-m-d', (int) ($e['ts'] / 1000))) * 1000);
            $byDay[$k] = ($byDay[$k] ?? 0) + 1;
        }

        return [
            'total' => count($events),
            'sessions' => $counts['session.started'] ?? 0,
            'first' => $ts === [] ? '-' : Sample::fullDate(min($ts)),
            'last' => $ts === [] ? '-' : Sample::relative(max($ts)),
            'top_event' => array_key_first($counts) ?? '-',
            ...$this->barsFrom($byDay),
        ];
    }

    /**
     * The 7-day bar series, from daily counts keyed by day-start timestamp.
     *
     * @param  array<int, int>  $byDay
     * @return array{bars: list<int>, max_bar: int}
     */
    private function barsFrom(array $byDay): array
    {
        $today = (int) (strtotime('today') * 1000);
        $bars = [];
        for ($i = 6; $i >= 0; $i--) {
            $bars[] = (int) ($byDay[$today - $i * 86400000] ?? 0);
        }

        return ['bars' => $bars, 'max_bar' => max(1, ...$bars)];
    }
}
