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

class Events extends Component
{
    use ResolvesEvents;

    /** The periods the segmented control offers, as URL-safe tokens. */
    private const PERIODS = ['today', '7d', '30d'];

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

    /** The event set backing this render - demo buffer or a real query. */
    private function sourceEvents(): array
    {
        if ($this->demo) {
            return $this->events;
        }

        // Trail::events() already orders newest-first; window it, then cap and eager-load.
        return Trail::events()->toBuilder()
            ->where('occurred_at', '>=', $this->sinceAt())
            ->with('subject')
            ->limit(200)
            ->get()
            ->map(fn (TrailEvent $event) => $this->normalizeEvent($event))
            ->all();
    }

    public function render(): View
    {
        $all = $this->sourceEvents();

        $pageViewName = Trail::pageViewName();

        $visible = array_values(array_filter($all, function (array $e) use ($pageViewName): bool {
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

        $names = collect($all)->pluck('name')->reject(fn ($n) => $n === $pageViewName)
            ->unique()->sort()->values()->all();

        $actors = collect($all)
            ->reject(fn ($e) => ($e['subject_key'] ?? '') === '')
            ->map(fn ($e) => $e['actor'] + ['key' => $e['subject_key']])
            ->unique('key')->values()->all();

        $selected = $this->selectedId !== null
            ? collect($all)->firstWhere('id', $this->selectedId)
            : null;

        return view('trail::livewire.events', [
            'visible' => $visible,
            'names' => $names,
            'actors' => $actors,
            'selected' => $selected,
            'hasFilters' => $this->search !== '' || $this->eventFilter !== [] || $this->actorFilter !== null,
        ])->layout('trail::layout', ['active' => ($this->demo ? 'demo-' : '').'events', 'title' => 'Events']);
    }
}
