<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Livewire\Concerns\ResolvesEvents;
use Trail\Trail\Models\TrailEvent;

class SubjectTimeline extends Component
{
    use ResolvesEvents;

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
    #[Url]
    public string $typeFilter = '';

    #[Url]
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

    /** Distinct real subjects with resolved identity, most active first (used by timeline switcher). */
    private function realActors(): array
    {
        $rows = Trail::events()->toBuilder()->reorder()
            ->selectRaw('subject_type, subject_id, count(*) as aggregate')
            ->whereNotNull('subject_id')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('aggregate')->limit(50)->get();

        $identities = $this->resolveIdentities(
            $rows->map(fn ($r) => [$r->subject_type, $r->subject_id])->all()
        );

        return $rows->map(function ($row) use ($identities): array {
            $type = $row->subject_type ? class_basename($row->subject_type) : 'Anônimo';
            $id = $identities[$row->subject_type.'|'.$row->subject_id] ?? null;

            return [
                'key' => $row->subject_type.'|'.$row->subject_id,
                'name' => $id['name'] ?? "{$type} #{$row->subject_id}",
                'type' => $type,
                'id' => (string) $row->subject_id,
                'email' => $id['email'] ?? null,
            ];
        })->all();
    }

    /** All actors for the index page, with search + type filter applied in PHP after identity resolution. */
    private function indexActors(): array
    {
        $distinctTypes = Trail::events()->toBuilder()->reorder()
            ->select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->filter()
            ->map(fn ($t) => ['value' => $t, 'label' => class_basename($t)])
            ->sortBy('label')
            ->values()
            ->all();

        $rows = Trail::events()->toBuilder()->reorder()
            ->selectRaw('subject_type, subject_id, count(*) as total, max(occurred_at) as last_seen')
            ->whereNotNull('subject_id')
            ->when($this->typeFilter !== '', fn ($q) => $q->where('subject_type', $this->typeFilter))
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('total')
            ->toBase()->get();

        $identities = $this->resolveIdentities(
            $rows->map(fn ($r) => [$r->subject_type, $r->subject_id])->all()
        );

        $actors = $rows->map(function ($row) use ($identities): array {
            $type = $row->subject_type ? class_basename($row->subject_type) : 'Anônimo';
            $key = $row->subject_type.'|'.$row->subject_id;
            $identity = $identities[$key] ?? null;

            return [
                'key' => $key,
                'name' => $identity['name'] ?? "{$type} #{$row->subject_id}",
                'type' => $type,
                'id' => (string) $row->subject_id,
                'email' => $identity['email'] ?? null,
                'total' => (int) $row->total,
                'last_seen' => $row->last_seen !== null
                    ? (int) (strtotime((string) $row->last_seen) * 1000)
                    : null,
            ];
        })->all();

        if ($this->indexSearch !== '') {
            $term = mb_strtolower($this->indexSearch);
            $actors = array_values(array_filter($actors, fn ($a) => str_contains(mb_strtolower($a['id']), $term) ||
                str_contains(mb_strtolower($a['name']), $term) ||
                str_contains(mb_strtolower($a['email'] ?? ''), $term)
            ));
        }

        return ['actors' => $actors, 'distinctTypes' => $distinctTypes];
    }

    public function render(): View
    {
        if (! $this->demo && $this->actorId === '') {
            ['actors' => $allActors, 'distinctTypes' => $distinctTypes] = $this->indexActors();

            $perPage = 25;
            $total = count($allActors);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = max(1, min($this->page, $totalPages));
            $paged = array_slice($allActors, ($page - 1) * $perPage, $perPage);

            return view('trail::livewire.subject-timeline', [
                'indexMode' => true,
                'actors' => $paged,
                'total' => $total,
                'page' => $page,
                'totalPages' => $totalPages,
                'distinctTypes' => $distinctTypes,
            ])->layout('trail::layout', ['active' => 'timeline', 'title' => 'Subject Timeline']);
        }

        if ($this->demo) {
            $actor = $this->demoActor();
            $events = $this->demoEvents;
            $results = array_map(fn ($a) => $a + ['key' => $a['id']], Sample::actors());
        } else {
            $actors = $this->realActors();
            $actor = collect($actors)->firstWhere('key', $this->actorId)
                ?? ['key' => '', 'name' => '-', 'type' => '-', 'id' => '-', 'email' => null];
            $events = $this->realEventsFor($actor['key']);
            $results = $this->actorSearch !== ''
                ? array_values(array_filter($actors, fn ($a) => str_contains(
                    mb_strtolower($a['name'].' '.$a['id'].' '.($a['email'] ?? '')),
                    mb_strtolower($this->actorSearch)
                )))
                : $actors;
        }

        $pageViewName = (string) config('trail.auto_track.event_name', 'page.viewed');

        $filtered = $this->activeTypes !== []
            ? array_values(array_filter($events, fn ($e) => in_array($e['name'], $this->activeTypes, true)))
            : $events;

        if (! $this->showPageViews) {
            $filtered = array_values(array_filter($filtered, fn ($e) => $e['name'] !== $pageViewName));
        }

        $groups = [];
        foreach ($filtered as $e) {
            $label = Sample::dayLabel($e['ts']);
            if ($groups === [] || end($groups)['label'] !== $label) {
                $groups[] = ['label' => $label, 'date' => Sample::fullDate($e['ts']), 'items' => []];
            }
            $groups[array_key_last($groups)]['items'][] = $e + [
                'clock' => Sample::clock($e['ts']),
                'relative' => Sample::relative($e['ts']),
            ];
        }

        $types = collect($events)->pluck('name')->reject(fn ($name) => $name === $pageViewName)
            ->unique()->sort()->values()
            ->map(fn ($name) => [
                'name' => $name,
                'color' => Sample::colorFor($name),
                'on' => in_array($name, $this->activeTypes, true),
            ])->all();

        $ts = array_column($events, 'ts');
        $counts = array_count_values(array_column($events, 'name'));
        if (! $this->showPageViews) {
            unset($counts[$pageViewName]);
        }
        arsort($counts);
        $byDay = [];
        foreach ($events as $e) {
            $k = (int) (strtotime(date('Y-m-d', (int) ($e['ts'] / 1000))) * 1000);
            $byDay[$k] = ($byDay[$k] ?? 0) + 1;
        }
        $today = (int) (strtotime('today') * 1000);
        $bars = [];
        for ($i = 6; $i >= 0; $i--) {
            $bars[] = $byDay[$today - $i * 86400000] ?? 0;
        }

        return view('trail::livewire.subject-timeline', [
            'indexMode' => false,
            'actor' => $actor,
            'groups' => $groups,
            'types' => $types,
            'events' => $events,
            'empty' => $filtered === [],
            'stats' => [
                'total' => count($events),
                'sessions' => $counts['session.started'] ?? 0,
                'first' => $ts === [] ? '-' : Sample::fullDate(min($ts)),
                'last' => $ts === [] ? '-' : Sample::relative(max($ts)),
                'top_event' => array_key_first($counts) ?? '-',
                'bars' => $bars,
                'max_bar' => max(1, ...$bars),
            ],
            'results' => $results,
        ])->layout('trail::layout', ['active' => ($this->demo ? 'demo-' : '').'timeline', 'title' => 'Subject Timeline']);
    }

    private function realEventsFor(string $key): array
    {
        if ($key === '') {
            return [];
        }
        [$type, $id] = explode('|', $key, 2);

        // Trail::events() already orders newest-first; uses the
        // (subject_type, subject_id, occurred_at) composite index.
        return Trail::events()->toBuilder()
            ->with('subject')
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->limit(300)->get()
            ->map(fn (TrailEvent $e) => $this->normalizeEvent($e))->all();
    }
}
