<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Livewire\Concerns\ResolvesEvents;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Queries\EventStreamQuery;
use Trail\Trail\Queries\SubjectIdentity;
use Trail\Trail\Queries\SubjectKey;

class Events extends Component
{
    use ResolvesEvents;

    /** The periods the segmented control offers, as URL-safe tokens. */
    private const PERIODS = ['today', '7d', '30d'];

    /** How many rows the table renders at most. */
    private const ROW_CAP = 200;

    /** How many actors the filter menu offers at most. */
    private const ACTOR_MENU_CAP = 200;

    /** When true the screen renders sample data instead of querying. */
    public bool $demo = false;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<string> */
    #[Url(as: 'events')]
    public array $eventFilter = [];

    /** Selection token: "subject_type|subject_id", same shape SubjectTimeline exposes. */
    #[Url(as: 'actor')]
    public ?string $actorFilter = null;

    /** Window token: one of self::PERIODS. */
    #[Url]
    public string $since = '7d';

    public bool $live = true;

    #[Url(as: 'page_views')]
    public bool $showPageViews = false;

    public ?int $selectedId = null;

    /** @var list<array<string,mixed>> Demo stream buffer (demo mode only). */
    public array $events = [];

    public int $seq = 0;

    public ?int $newId = null;

    /** Highest event id seen, so the live poll can skip idle ticks (real mode). */
    public ?int $lastSeenId = null;

    public function mount(bool $demo = false): void
    {
        $this->demo = $demo;

        // A hand-typed ?since= would otherwise leave every segment unlit.
        if (! in_array($this->since, self::PERIODS, true)) {
            $this->since = '7d';
        }

        if ($this->demo) {
            $this->events = Sample::stream(50);
            $this->seq = count($this->events);

            return;
        }

        $this->lastSeenId = (int) (Trail::events()->toBuilder()->max('id') ?? 0);
    }

    /** Live stream tick (wire:poll target). */
    public function tick(): void
    {
        if (! $this->live) {
            return;
        }

        // Demo: synthesise an event.
        if ($this->demo) {
            $event = Sample::makeEvent((int) (microtime(true) * 1000), ++$this->seq);
            array_unshift($this->events, $event);
            $this->events = array_slice($this->events, 0, 200);
            $this->newId = $event['id'];

            return;
        }

        // Real: a cheap PK-indexed max(id) check. Only re-query the table (and
        // re-render) when something new actually landed; otherwise skip.
        $maxId = (int) (Trail::events()->toBuilder()->max('id') ?? 0);
        if ($this->lastSeenId !== null && $maxId <= $this->lastSeenId) {
            $this->skipRender();

            return;
        }

        $this->newId = $maxId;
        $this->lastSeenId = $maxId;
    }

    public function toggleLive(): void
    {
        $this->live = ! $this->live;
    }

    public function togglePageViews(): void
    {
        // Clear the new-row highlight: revealing previously hidden page views
        // should not flash them as if they had just arrived from the live poll.
        $this->newId = null;
        $this->showPageViews = ! $this->showPageViews;
    }

    public function toggleEvent(string $name): void
    {
        $this->newId = null;
        if (in_array($name, $this->eventFilter, true)) {
            $this->eventFilter = array_values(array_diff($this->eventFilter, [$name]));
        } else {
            $this->eventFilter[] = $name;
        }
    }

    public function setActor(?string $id): void
    {
        $this->newId = null;
        $this->actorFilter = $id ?: null;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->eventFilter = [];
        $this->actorFilter = null;
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->dispatch('drawer-open');
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

    /**
     * The stream behind this render, configured from the current filters.
     *
     * Not named stream(): Livewire\Component already defines a public stream().
     */
    private function eventStream(): EventStreamQuery
    {
        return EventStreamQuery::inWindow($this->sinceAt())
            ->includingPageViews($this->showPageViews)
            ->onlyNames($this->eventFilter)
            ->byActor(SubjectKey::parse($this->actorFilter))
            ->matching($this->search);
    }

    /**
     * The actors offered by the filter menu, resolved to their display identity.
     *
     * @return list<array<string,mixed>>
     */
    private function actorFacets(): array
    {
        $keys = $this->eventStream()->subjectsInWindow(self::ACTOR_MENU_CAP);
        $identities = SubjectIdentity::resolve($keys);

        // This menu is the only place the Events screen can surface an email,
        // so a nameless subject is shown by its address rather than its id.
        return array_map(
            fn (SubjectKey $key) => SubjectIdentity::display($key, $identities, emailAsName: true),
            $keys
        );
    }

    /**
     * The event the drawer shows. Looked up by id rather than picked out of the
     * rendered rows: the drawer stays open client-side, so it must survive a
     * filter (or a live tick) that drops its row from the table.
     *
     * @param  list<array<string,mixed>>  $visible
     * @return array<string,mixed>|null
     */
    private function selectedEvent(array $visible): ?array
    {
        if ($this->selectedId === null) {
            return null;
        }

        // Usually the row is right there; only pay for a query when it is not.
        $row = collect($visible)->firstWhere('id', $this->selectedId);
        if ($row !== null || $this->demo) {
            return $row;
        }

        $event = TrailEvent::with('subject')->find($this->selectedId);

        return $event === null ? null : $this->normalizeEvent($event);
    }

    /**
     * The demo buffer, filtered in PHP - Sample data never touches the database.
     *
     * @return list<array<string,mixed>>
     */
    private function filteredDemoEvents(): array
    {
        $pageViewName = Trail::pageViewName();

        return array_values(array_filter($this->events, function (array $e) use ($pageViewName): bool {
            if (! $this->showPageViews && $e['name'] === $pageViewName) {
                return false;
            }
            if ($this->eventFilter !== [] && ! in_array($e['name'], $this->eventFilter, true)) {
                return false;
            }
            if ($this->actorFilter !== null && ($e['subject_key'] ?? '') !== $this->actorFilter) {
                return false;
            }
            if ($this->search !== '') {
                $hay = mb_strtolower($e['name'].' '.$e['actor']['name'].' '.$e['actor']['id'].' '.json_encode($e['props']));
                if (! str_contains($hay, mb_strtolower($this->search))) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function render(): View
    {
        if ($this->demo) {
            $pageViewName = Trail::pageViewName();
            $visible = $this->filteredDemoEvents();
            $names = collect($this->events)->pluck('name')->reject(fn ($n) => $n === $pageViewName)
                ->unique()->sort()->values()->all();
            $actors = collect($this->events)
                ->reject(fn ($e) => ($e['subject_key'] ?? '') === '')
                ->map(fn ($e) => $e['actor'] + ['key' => $e['subject_key']])
                ->unique('key')->values()->all();
        } else {
            $visible = $this->eventStream()->rows(self::ROW_CAP)
                ->map(fn (TrailEvent $event) => $this->normalizeEvent($event))
                ->all();
            $names = $this->eventStream()->namesInWindow();
            $actors = $this->actorFacets();
        }

        $selected = $this->selectedEvent($visible);

        return view('trail::livewire.events', [
            'visible' => $visible,
            'names' => $names,
            'actors' => $actors,
            'selected' => $selected,
            'hasFilters' => $this->search !== '' || $this->eventFilter !== [] || $this->actorFilter !== null,
        ])->layout('trail::layout', ['active' => ($this->demo ? 'demo-' : '').'events', 'title' => 'Events']);
    }
}
