<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Trail\Trail\Livewire\Concerns\ResolvesEvents;
use Trail\Trail\Models\TrailEvent;

class Overview extends Component
{
    use ResolvesEvents;

    public bool $demo = false;

    public string $period = '7d';

    public string $granularity = 'Dia';

    public function mount(bool $demo = false): void
    {
        $this->demo = $demo;
    }

    public function render(): View
    {
        $data = $this->demo ? $this->demoData() : $this->realData();

        return view('trail::livewire.overview', $data)
            ->layout('trail::layout', ['active' => ($this->demo ? 'demo-' : '').'overview', 'title' => 'Overview']);
    }

    // ------------------------------------------------------------------
    // Real data
    // ------------------------------------------------------------------

    private function realData(): array
    {
        [$labels, $counts, $total] = $this->realSeries();

        $topEventRows = TrailEvent::query()
            ->selectRaw('name, count(*) as aggregate')
            ->groupBy('name')->orderByDesc('aggregate')->limit(6)->get();
        $maxTop = (int) ($topEventRows->max('aggregate') ?: 1);
        $topEvents = $topEventRows->map(fn ($r) => [
            'name' => $r->name,
            'count' => $this->humanize((int) $r->aggregate),
            'pct' => (int) round((int) $r->aggregate / $maxTop * 100),
        ])->all();

        $totalEvents = TrailEvent::query()->count();
        $uniqueSubjects = TrailEvent::query()->whereNotNull('subject_id')->distinct()->count('subject_id');
        $todayEvents = TrailEvent::query()->where('occurred_at', '>=', Carbon::today())->count();

        $metrics = [
            ['label' => 'Total de eventos', 'value' => $this->humanize($totalEvents), 'sub' => 'desde o início',
                'sparkPts' => count($counts) > 1 ? self::spark($counts) : null, 'accent' => true],
            ['label' => 'Atores únicos ativos', 'value' => $this->humanize($uniqueSubjects), 'sub' => 'com eventos'],
            ['label' => 'Evento mais frequente', 'value' => $topEventRows->first()->name ?? '—', 'mono' => true,
                'sub' => $topEventRows->first() ? $this->humanize((int) $topEventRows->first()->aggregate).' disparos' : 'sem eventos'],
            ['label' => 'Eventos hoje', 'value' => $this->humanize($todayEvents), 'sub' => 'últimas 24h'],
        ];

        return [
            'metrics' => $metrics,
            'chart' => $this->chartGeometry($labels, $counts, $this->humanize($total)),
            'topEvents' => $topEvents,
            'topActors' => $this->realActors(),
        ];
    }

    /** Per-bucket counts for the chart at the current granularity. */
    private function realSeries(): array
    {
        [$format, $count, $step, $labelFn] = match ($this->granularity) {
            'Hora' => ['Y-m-d H', 12, 'subHours', fn (Carbon $d) => $d->format('H')],
            'Semana' => ['o-W', 6, 'subWeeks', fn (Carbon $d) => $d->isoFormat('[S]ww')],
            default => ['Y-m-d', 7, 'subDays', fn (Carbon $d) => ucfirst($d->locale('pt_BR')->isoFormat('ddd'))],
        };

        $now = Carbon::now();
        $buckets = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $d = (clone $now)->{$step}($i);
            $buckets[$d->format($format)] = ['label' => $labelFn($d), 'count' => 0];
        }

        $since = (clone $now)->{$step}($count - 1)->startOf($this->granularity === 'Hora' ? 'hour' : ($this->granularity === 'Semana' ? 'week' : 'day'));

        TrailEvent::query()
            ->where('occurred_at', '>=', $since)
            ->get(['occurred_at'])
            ->each(function (TrailEvent $e) use (&$buckets, $format): void {
                $key = $e->occurred_at->format($format);
                if (isset($buckets[$key])) {
                    $buckets[$key]['count']++;
                }
            });

        $labels = array_column($buckets, 'label');
        $counts = array_column($buckets, 'count');

        return [$labels, $counts, array_sum($counts)];
    }

    private function realActors(): array
    {
        return TrailEvent::query()
            ->selectRaw('subject_type, subject_id, count(*) as aggregate')
            ->whereNotNull('subject_id')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('aggregate')->limit(5)->get()
            ->map(function ($row): array {
                $type = $row->subject_type ? class_basename($row->subject_type) : 'Anônimo';
                $name = $this->resolveName($row->subject_type, $row->subject_id) ?? "{$type} #{$row->subject_id}";

                return [
                    'name' => $name,
                    'meta' => "{$type} · {$row->subject_id}",
                    'count' => $this->humanize((int) $row->aggregate),
                ];
            })->all();
    }

    private function resolveName(?string $type, mixed $id): ?string
    {
        if ($type === null || ! class_exists($type)) {
            return null;
        }

        $model = $type::query()->find($id);

        return $model?->name ?? $model?->email ?? null;
    }

    private function humanize(int $n): string
    {
        if ($n >= 1_000_000) {
            return rtrim(rtrim(number_format($n / 1_000_000, 2, '.', ''), '0'), '.').'M';
        }
        if ($n >= 1_000) {
            return rtrim(rtrim(number_format($n / 1_000, 1, '.', ''), '0'), '.').'k';
        }

        return (string) $n;
    }

    // ------------------------------------------------------------------
    // Demo data
    // ------------------------------------------------------------------

    private function demoData(): array
    {
        $datasets = [
            'Hora' => ['labels' => ['00', '03', '06', '09', '12', '15', '18', '21', '23'], 'data' => [320, 180, 140, 520, 880, 760, 1020, 1240, 640], 'total' => '142k'],
            'Dia' => ['labels' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'], 'data' => [148, 162, 139, 205, 243, 128, 176], 'total' => '1.24M'],
            'Semana' => ['labels' => ['S-5', 'S-4', 'S-3', 'S-2', 'S-1', 'Atual'], 'data' => [820, 910, 760, 1080, 1190, 1320], 'total' => '5.9M'],
        ];
        $ds = $datasets[$this->granularity];

        $metrics = array_map(function (array $m): array {
            $m['sparkPts'] = self::spark($m['spark']);

            return $m;
        }, [
            ['label' => 'Total de eventos', 'value' => '1.24M', 'delta' => '+12.4%', 'dir' => 'up', 'spark' => [8, 10, 9, 13, 12, 17, 16, 21, 19, 24], 'accent' => true],
            ['label' => 'Atores únicos ativos', 'value' => '8.412', 'delta' => '+4.8%', 'dir' => 'up', 'spark' => [14, 15, 13, 16, 18, 17, 19, 20, 22, 23]],
            ['label' => 'Evento mais frequente', 'value' => 'order.placed', 'mono' => true, 'sub' => '48.2k disparos', 'spark' => [20, 18, 22, 19, 24, 21, 25, 23, 27, 26]],
            ['label' => 'Taxa de conversão', 'value' => '6.7%', 'delta' => '−1.2%', 'dir' => 'down', 'spark' => [12, 13, 11, 12, 10, 11, 9, 10, 8, 9]],
        ]);

        $actors = array_map(function (array $a): array {
            $a['sparkPts'] = self::spark($a['spark']);

            return $a;
        }, [
            ['name' => 'Marina Rocha', 'meta' => 'User · ator_8821', 'count' => '1.842', 'spark' => [6, 8, 7, 10, 12, 11, 14]],
            ['name' => 'Acme Team', 'meta' => 'Team · team_204', 'count' => '1.530', 'spark' => [10, 9, 12, 11, 13, 12, 15]],
            ['name' => 'João Silva', 'meta' => 'User · ator_3390', 'count' => '1.214', 'spark' => [8, 7, 9, 8, 10, 9, 11]],
            ['name' => 'Beatriz Lima', 'meta' => 'User · ator_7745', 'count' => '982', 'spark' => [5, 6, 5, 7, 6, 8, 7]],
            ['name' => 'Pedro Alves', 'meta' => 'User · ator_1182', 'count' => '774', 'spark' => [4, 5, 4, 6, 5, 5, 6]],
        ]);

        return [
            'metrics' => $metrics,
            'chart' => $this->chartGeometry($ds['labels'], $ds['data'], $ds['total']),
            'topEvents' => [
                ['name' => 'order.placed', 'count' => '48.2k', 'pct' => 100],
                ['name' => 'onboarding.step_completed', 'count' => '37.9k', 'pct' => 79],
                ['name' => 'user.signed_up', 'count' => '31.7k', 'pct' => 66],
                ['name' => 'whatsapp.connected', 'count' => '18.3k', 'pct' => 38],
                ['name' => 'cart.updated', 'count' => '12.4k', 'pct' => 26],
                ['name' => 'invoice.paid', 'count' => '6.1k', 'pct' => 13],
            ],
            'topActors' => $actors,
        ];
    }

    // ------------------------------------------------------------------
    // Geometry
    // ------------------------------------------------------------------

    /** Build line + area point strings for a sparkline. */
    public static function spark(array $pts, float $w = 88, float $h = 32): array
    {
        $max = max($pts);
        $min = min($pts);
        $span = ($max - $min) ?: 1;
        $count = count($pts);
        $map = [];
        foreach ($pts as $i => $v) {
            $x = $count > 1 ? $i / ($count - 1) * $w : 0;
            $y = $h - ($v - $min) / $span * ($h - 4) - 2;
            $map[] = round($x, 1).','.round($y, 1);
        }
        $line = implode(' ', $map);

        return ['line' => $line, 'area' => $line." {$w},{$h} 0,{$h}"];
    }

    /** Geometry for the main area chart. */
    private function chartGeometry(array $labels, array $data, string $total): array
    {
        $w = 820;
        $padL = 8;
        $padR = 8;
        $padT = 8;
        $innerW = $w - $padL - $padR;
        $innerH = 240 - $padT - 26;
        $max = ($data === [] ? 0 : max($data)) * 1.1 ?: 1;
        $n = count($data);

        $xs = [];
        $ys = [];
        foreach ($data as $i => $v) {
            $xs[] = $padL + ($n > 1 ? $i / ($n - 1) : 0) * $innerW;
            $ys[] = $padT + $innerH - ($v / $max) * $innerH;
        }

        $line = [];
        foreach ($xs as $i => $x) {
            $line[] = round($x, 1).','.round($ys[$i], 1);
        }
        $linePts = implode(' ', $line);

        $grid = [];
        for ($g = 0; $g <= 3; $g++) {
            $grid[] = round($padT + $g / 3 * $innerH, 1);
        }

        $labelsOut = [];
        foreach ($labels as $i => $label) {
            $labelsOut[] = ['x' => round($xs[$i] ?? 0, 1), 'label' => $label];
        }

        $dots = [];
        foreach ($xs as $i => $x) {
            $dots[] = ['x' => round($x, 1), 'y' => round($ys[$i], 1)];
        }

        return [
            'line' => $linePts,
            'area' => $xs === [] ? '' : $linePts.' '.round(end($xs), 1).','.($padT + $innerH).' '.$padL.','.($padT + $innerH),
            'grid' => $grid,
            'labels' => $labelsOut,
            'dots' => $dots,
            'gridX2' => $w - $padR,
            'total' => $total,
        ];
    }
}
