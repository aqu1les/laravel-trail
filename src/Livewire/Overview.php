<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Livewire\Concerns\ResolvesEvents;
use Trail\Trail\Models\TrailAggregate;
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
        // The chart series and the windowed "top events" are the two heaviest
        // scans; serve them from pre-computed rollups when available, else live.
        [$period, $since, $useRollup] = $this->seriesWindow();

        [$labels, $counts, $total] = $useRollup
            ? $this->rollupSeries($period, $since)
            : $this->liveSeries();

        $topRows = $useRollup
            ? $this->rollupTopEventRows($period, $since)
            : $this->liveTopEventRows($since);
        $maxTop = (int) ($topRows->max('c') ?: 1);
        $topEvents = $topRows->map(fn ($r) => [
            'name' => $r->name,
            'count' => $this->humanize((int) $r->c),
            'pct' => (int) round((int) $r->c / $maxTop * 100),
        ])->all();
        $topEvent = $topRows->first();

        // Exact figures the rollups can't safely provide (cross-name uniqueness,
        // per-actor breakdown) stay live.
        $totalEvents = Trail::events()->count();
        $uniqueSubjects = DB::table(
            Trail::events()->toBuilder()->reorder()
                ->whereNotNull('subject_id')
                ->whereNotNull('subject_type')
                ->select('subject_type', 'subject_id')
                ->distinct()->toBase(),
            'unique_actors'
        )->count();
        $todayEvents = Trail::events()->today();

        $metrics = [
            ['label' => 'Total de eventos', 'value' => $this->humanize($totalEvents), 'sub' => 'desde o início',
                'sparkPts' => count($counts) > 1 ? self::spark($counts) : null, 'accent' => true],
            ['label' => 'Atores únicos ativos', 'value' => $this->humanize($uniqueSubjects), 'sub' => 'com eventos'],
            ['label' => 'Evento mais frequente', 'value' => $topEvent->name ?? '-', 'mono' => true,
                'sub' => $topEvent ? $this->humanize((int) $topEvent->c).' disparos' : 'sem eventos'],
            ['label' => 'Eventos hoje', 'value' => $this->humanize($todayEvents), 'sub' => 'últimas 24h'],
        ];

        return [
            'metrics' => $metrics,
            'chart' => $this->chartGeometry($labels, $counts, $this->humanize($total)),
            'topEvents' => $topEvents,
            'topActors' => $this->realActors(),
        ];
    }

    /** Resolve the chart window: [rollup period, since, whether rollups cover it]. */
    private function seriesWindow(): array
    {
        $now = Carbon::now();
        [$period, $n, $sub, $unit] = match ($this->granularity) {
            'Hora' => ['hour', 12, 'subHours', 'hour'],
            'Semana' => ['week', 6, 'subWeeks', 'week'],
            default => ['day', 7, 'subDays', 'day'],
        };
        $since = (clone $now)->{$sub}($n - 1)->startOf($unit);
        $useRollup = TrailAggregate::query()->where('period', $period)->where('bucket', '>=', $since)->exists();

        return [$period, $since, $useRollup];
    }

    /** Chart series from pre-computed rollups (a handful of rows). */
    private function rollupSeries(string $period, Carbon $since): array
    {
        $now = Carbon::now();
        [$n, $sub, $unit] = match ($period) {
            'hour' => [12, 'subHours', 'hour'],
            'week' => [6, 'subWeeks', 'week'],
            default => [7, 'subDays', 'day'],
        };

        $map = TrailAggregate::query()->where('period', $period)->where('bucket', '>=', $since)
            ->selectRaw('bucket, sum(count) as c')->groupBy('bucket')->pluck('c', 'bucket');

        $labels = $counts = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $start = (clone $now)->{$sub}($i)->startOf($unit);
            $labels[] = $this->seriesLabel($start, $i);
            $counts[] = (int) ($map[$start->toDateTimeString()] ?? 0);
        }

        return [$labels, $counts, array_sum($counts)];
    }

    private function seriesLabel(Carbon $start, int $i): string
    {
        return match ($this->granularity) {
            'Hora' => $start->format('H'),
            'Semana' => $i === 0 ? 'Atual' : "S-{$i}",
            default => ucfirst($start->locale('pt_BR')->isoFormat('ddd')),
        };
    }

    /** Top events over the window, live (name + c). */
    private function liveTopEventRows(Carbon $since)
    {
        return Trail::events()->between($since, Carbon::now())->toBuilder()->reorder()
            ->selectRaw('name, count(*) as c')
            ->groupBy('name')->orderByDesc('c')->limit(6)->get();
    }

    /** Top events over the window, from rollups (name + c). */
    private function rollupTopEventRows(string $period, Carbon $since)
    {
        return TrailAggregate::query()->where('period', $period)->where('bucket', '>=', $since)
            ->selectRaw('name, sum(count) as c')
            ->groupBy('name')->orderByDesc('c')->limit(6)->get();
    }

    /** Per-bucket counts for the chart - one GROUP BY query, no row loading. */
    private function liveSeries(): array
    {
        $now = Carbon::now();

        // Weeks roll up from a single daily aggregate (<= 42 rows).
        if ($this->granularity === 'Semana') {
            $weeks = 6;
            $since = (clone $now)->subWeeks($weeks - 1)->startOfWeek();
            $daily = $this->groupedCounts($since, 'day');
            $labels = $counts = [];
            for ($w = $weeks - 1; $w >= 0; $w--) {
                $start = (clone $now)->subWeeks($w)->startOfWeek();
                $sum = 0;
                for ($d = 0; $d < 7; $d++) {
                    $sum += $daily[(clone $start)->addDays($d)->format('Y-m-d')] ?? 0;
                }
                $labels[] = $w === 0 ? 'Atual' : "S-{$w}";
                $counts[] = $sum;
            }

            return [$labels, $counts, array_sum($counts)];
        }

        [$unit, $n, $step, $fmt, $labelFn] = $this->granularity === 'Hora'
            ? ['hour', 12, 'subHours', 'Y-m-d H', fn (Carbon $d) => $d->format('H')]
            : ['day', 7, 'subDays', 'Y-m-d', fn (Carbon $d) => ucfirst($d->locale('pt_BR')->isoFormat('ddd'))];

        $since = (clone $now)->{$step}($n - 1)->startOf($unit);
        $grouped = $this->groupedCounts($since, $unit);

        $labels = $counts = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $d = (clone $now)->{$step}($i);
            $labels[] = $labelFn($d);
            $counts[] = $grouped[$d->format($fmt)] ?? 0;
        }

        return [$labels, $counts, array_sum($counts)];
    }

    /**
     * Bucketed event counts since a moment, aggregated in SQL.
     *
     * @return array<string,int> bucket key => count
     */
    private function groupedCounts(Carbon $since, string $unit): array
    {
        $builder = Trail::events()->between($since, Carbon::now())->toBuilder()->reorder();
        $driver = (new TrailEvent)->getConnection()->getDriverName();

        $expr = match ([$driver, $unit]) {
            ['sqlite', 'hour'] => "strftime('%Y-%m-%d %H', occurred_at)",
            ['sqlite', 'day'] => "strftime('%Y-%m-%d', occurred_at)",
            ['mysql', 'hour'], ['mariadb', 'hour'] => "DATE_FORMAT(occurred_at, '%Y-%m-%d %H')",
            ['mysql', 'day'], ['mariadb', 'day'] => "DATE_FORMAT(occurred_at, '%Y-%m-%d')",
            ['pgsql', 'hour'] => "to_char(occurred_at, 'YYYY-MM-DD HH24')",
            ['pgsql', 'day'] => "to_char(occurred_at, 'YYYY-MM-DD')",
            default => null,
        };

        // Unknown driver: portable fallback over the bounded window.
        if ($expr === null) {
            $fmt = $unit === 'hour' ? 'Y-m-d H' : 'Y-m-d';

            return $builder->get(['occurred_at'])
                ->groupBy(fn (TrailEvent $e) => $e->occurred_at->format($fmt))
                ->map(fn ($group) => $group->count())->all();
        }

        return $builder->selectRaw("{$expr} as bucket, count(*) as aggregate")
            ->groupBy('bucket')->pluck('aggregate', 'bucket')
            ->map(fn ($v) => (int) $v)->all();
    }

    private function realActors(): array
    {
        $rows = Trail::events()->toBuilder()->reorder()
            ->selectRaw('subject_type, subject_id, count(*) as aggregate')
            ->whereNotNull('subject_id')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('aggregate')->limit(5)->get();

        $identities = $this->resolveIdentities(
            $rows->map(fn ($r) => [$r->subject_type, $r->subject_id])->all()
        );

        return $rows->map(function ($row) use ($identities): array {
            $type = $row->subject_type ? class_basename($row->subject_type) : 'Anônimo';
            $id = $identities[$row->subject_type.'|'.$row->subject_id] ?? null;

            return [
                'name' => $id['name'] ?? $id['email'] ?? "{$type} #{$row->subject_id}",
                'meta' => "{$type} · {$row->subject_id}",
                'count' => $this->humanize((int) $row->getAttribute('aggregate')),
            ];
        })->all();
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
