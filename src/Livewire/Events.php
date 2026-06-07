<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Trail\Trail\Livewire\Concerns\ResolvesEvents;
use Trail\Trail\Models\TrailEvent;

class Events extends Component
{
    use ResolvesEvents;

    /** When true the screen renders sample data instead of querying. */
    public bool $demo = false;

    public string $search = '';

    /** @var list<string> */
    public array $eventFilter = [];

    public ?string $actorFilter = null;

    public string $period = '7d';

    public bool $live = true;

    public ?int $selectedId = null;

    /** @var list<array<string,mixed>> Demo stream buffer (demo mode only). */
    public array $events = [];

    public int $seq = 0;

    public ?int $newId = null;

    public function mount(bool $demo = false): void
    {
        $this->demo = $demo;

        if ($this->demo) {
            $this->events = Sample::stream(50);
            $this->seq = count($this->events);
        }
    }

    /** Live stream tick (wire:poll target). */
    public function tick(): void
    {
        if (! $this->live) {
            return;
        }

        // Demo: synthesise an event. Real: the re-render re-queries the table.
        if ($this->demo) {
            $event = Sample::makeEvent((int) (microtime(true) * 1000), ++$this->seq);
            array_unshift($this->events, $event);
            $this->events = array_slice($this->events, 0, 200);
            $this->newId = $event['id'];
        }
    }

    public function toggleLive(): void
    {
        $this->live = ! $this->live;
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

    /** The event set backing this render — demo buffer or a real query. */
    private function sourceEvents(): array
    {
        if ($this->demo) {
            return $this->events;
        }

        return TrailEvent::query()
            ->with('subject')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (TrailEvent $event) => $this->normalizeEvent($event))
            ->all();
    }

    public function render(): View
    {
        $cats = Sample::categories();
        $all = $this->sourceEvents();

        $visible = array_values(array_filter($all, function (array $e): bool {
            if ($this->eventFilter !== [] && ! in_array($e['name'], $this->eventFilter, true)) {
                return false;
            }
            if ($this->actorFilter !== null && $e['actor']['id'] !== $this->actorFilter) {
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

        $names = collect($all)->pluck('name')->unique()->sort()->values()->all();

        $actors = collect($all)->pluck('actor')->unique('id')->values()
            ->reject(fn ($a) => $a['id'] === '—')->values()->all();

        $selected = $this->selectedId !== null
            ? collect($all)->firstWhere('id', $this->selectedId)
            : null;

        return view('trail::livewire.events', [
            'cats' => $cats,
            'visible' => $visible,
            'names' => $names,
            'actors' => $actors,
            'selected' => $selected,
            'hasFilters' => $this->search !== '' || $this->eventFilter !== [] || $this->actorFilter !== null,
        ])->layout('trail::layout', ['active' => ($this->demo ? 'demo-' : '').'events', 'title' => 'Events']);
    }
}
