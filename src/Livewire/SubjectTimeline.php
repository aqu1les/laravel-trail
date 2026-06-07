<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class SubjectTimeline extends Component
{
    public string $actorId = '';

    public string $actorSearch = '';

    /** @var list<string> */
    public array $activeTypes = [];

    /** @var list<array<string,mixed>> Stable history for the current actor. */
    public array $events = [];

    public function mount(): void
    {
        $this->actorId = Sample::actors()[0]['id'];
        $this->events = Sample::actorHistory($this->currentActor());
    }

    public function selectActor(string $id): void
    {
        $this->actorId = $id;
        $this->activeTypes = [];
        $this->actorSearch = '';
        $this->events = Sample::actorHistory($this->currentActor());
    }

    public function toggleType(string $name): void
    {
        if (in_array($name, $this->activeTypes, true)) {
            $this->activeTypes = array_values(array_diff($this->activeTypes, [$name]));
        } else {
            $this->activeTypes[] = $name;
        }
    }

    private function currentActor(): array
    {
        return collect(Sample::actors())->firstWhere('id', $this->actorId) ?? Sample::actors()[0];
    }

    public function render(): View
    {
        $cats = Sample::categories();
        $actor = $this->currentActor();

        $filtered = $this->activeTypes !== []
            ? array_values(array_filter($this->events, fn ($e) => in_array($e['name'], $this->activeTypes, true)))
            : $this->events;

        // Group consecutive events by day label (events are descending by time).
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

        // Distinct event types present in this actor's history.
        $types = collect($this->events)->pluck('name')->unique()->sort()->values()
            ->map(fn ($name) => [
                'name' => $name,
                'cat' => collect($this->events)->firstWhere('name', $name)['cat'],
                'on' => in_array($name, $this->activeTypes, true),
            ])->all();

        // Profile stats.
        $ts = array_column($this->events, 'ts');
        $counts = array_count_values(array_column($this->events, 'name'));
        arsort($counts);
        $byDay = [];
        foreach ($this->events as $e) {
            $k = (int) (strtotime(date('Y-m-d', (int) ($e['ts'] / 1000))) * 1000);
            $byDay[$k] = ($byDay[$k] ?? 0) + 1;
        }
        $today = (int) (strtotime('today') * 1000);
        $bars = [];
        for ($i = 6; $i >= 0; $i--) {
            $bars[] = $byDay[$today - $i * 86400000] ?? 0;
        }

        $results = $this->actorSearch !== ''
            ? array_values(array_filter(Sample::actors(), fn ($a) => str_contains(
                mb_strtolower($a['name'].' '.$a['id'].' '.$a['email']),
                mb_strtolower($this->actorSearch)
            )))
            : Sample::actors();

        return view('trail::livewire.subject-timeline', [
            'cats' => $cats,
            'actor' => $actor,
            'groups' => $groups,
            'types' => $types,
            'empty' => $filtered === [],
            'stats' => [
                'total' => count($this->events),
                'sessions' => $counts['session.started'] ?? 0,
                'first' => $ts === [] ? '—' : Sample::fullDate(min($ts)),
                'last' => $ts === [] ? '—' : Sample::relative(max($ts)),
                'top_event' => array_key_first($counts) ?? '—',
                'bars' => $bars,
                'max_bar' => max(1, ...$bars),
            ],
            'results' => $results,
        ])->layout('trail::layout', ['active' => 'timeline', 'title' => 'Subject Timeline']);
    }
}
