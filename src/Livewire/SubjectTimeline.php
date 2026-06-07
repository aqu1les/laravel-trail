<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Livewire\Concerns\ResolvesEvents;
use Trail\Trail\Models\TrailEvent;

class SubjectTimeline extends Component
{
    use ResolvesEvents;

    public bool $demo = false;

    /** Selection token: a Sample actor id (demo) or "subject_type|subject_id" (real). */
    public string $actorId = '';

    public string $actorSearch = '';

    /** @var list<string> */
    public array $activeTypes = [];

    /** @var list<array<string,mixed>> Demo history buffer (demo mode only). */
    public array $events = [];

    public function mount(bool $demo = false): void
    {
        $this->demo = $demo;

        if ($this->demo) {
            $this->actorId = Sample::actors()[0]['id'];
            $this->events = Sample::actorHistory($this->demoActor());

            return;
        }

        $this->actorId = $this->realActors()[0]['key'] ?? '';
    }

    public function selectActor(string $id): void
    {
        $this->actorId = $id;
        $this->activeTypes = [];
        $this->actorSearch = '';

        if ($this->demo) {
            $this->events = Sample::actorHistory($this->demoActor());
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

    private function demoActor(): array
    {
        $actor = collect(Sample::actors())->firstWhere('id', $this->actorId) ?? Sample::actors()[0];

        return $actor + ['key' => $actor['id']];
    }

    /** Distinct real subjects with resolved identity, most active first. */
    private function realActors(): array
    {
        return Trail::events()->toBuilder()->reorder()
            ->selectRaw('subject_type, subject_id, count(*) as aggregate')
            ->whereNotNull('subject_id')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('aggregate')->limit(50)->get()
            ->map(function ($row): array {
                $type = $row->subject_type ? class_basename($row->subject_type) : 'Anônimo';
                [$name, $email] = $this->resolveIdentity($row->subject_type, $row->subject_id);

                return [
                    'key' => $row->subject_type.'|'.$row->subject_id,
                    'name' => $name ?? "{$type} #{$row->subject_id}",
                    'type' => $type,
                    'id' => (string) $row->subject_id,
                    'email' => $email,
                ];
            })->all();
    }

    private function resolveIdentity(?string $type, mixed $id): array
    {
        if ($type === null || ! class_exists($type)) {
            return [null, null];
        }
        $model = $type::query()->find($id);

        return [$model?->name ?? null, $model?->email ?? null];
    }

    public function render(): View
    {
        $cats = Sample::categories();

        if ($this->demo) {
            $actor = $this->demoActor();
            $events = $this->events;
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

        $filtered = $this->activeTypes !== []
            ? array_values(array_filter($events, fn ($e) => in_array($e['name'], $this->activeTypes, true)))
            : $events;

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

        $types = collect($events)->pluck('name')->unique()->sort()->values()
            ->map(fn ($name) => [
                'name' => $name,
                'cat' => collect($events)->firstWhere('name', $name)['cat'],
                'on' => in_array($name, $this->activeTypes, true),
            ])->all();

        $ts = array_column($events, 'ts');
        $counts = array_count_values(array_column($events, 'name'));
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
            'cats' => $cats,
            'actor' => $actor,
            'groups' => $groups,
            'types' => $types,
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
