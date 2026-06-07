<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Overview extends Component
{
    public string $period = '7d';

    public string $granularity = 'Dia';

    /** Time-series datasets per granularity. */
    private function datasets(): array
    {
        return [
            'Hora' => ['labels' => ['00', '03', '06', '09', '12', '15', '18', '21', '23'], 'data' => [320, 180, 140, 520, 880, 760, 1020, 1240, 640], 'total' => '142k'],
            'Dia' => ['labels' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'], 'data' => [148, 162, 139, 205, 243, 128, 176], 'total' => '1.24M'],
            'Semana' => ['labels' => ['S-5', 'S-4', 'S-3', 'S-2', 'S-1', 'Atual'], 'data' => [820, 910, 760, 1080, 1190, 1320], 'total' => '5.9M'],
        ];
    }

    /** Metric cards (label, value, delta, sparkline). */
    private function metrics(): array
    {
        return [
            ['label' => 'Total de eventos', 'value' => '1.24M', 'delta' => '+12.4%', 'dir' => 'up', 'spark' => [8, 10, 9, 13, 12, 17, 16, 21, 19, 24], 'accent' => true],
            ['label' => 'Atores únicos ativos', 'value' => '8.412', 'delta' => '+4.8%', 'dir' => 'up', 'spark' => [14, 15, 13, 16, 18, 17, 19, 20, 22, 23]],
            ['label' => 'Evento mais frequente', 'value' => 'order.placed', 'mono' => true, 'sub' => '48.2k disparos', 'spark' => [20, 18, 22, 19, 24, 21, 25, 23, 27, 26]],
            ['label' => 'Taxa de conversão', 'value' => '6.7%', 'delta' => '−1.2%', 'dir' => 'down', 'spark' => [12, 13, 11, 12, 10, 11, 9, 10, 8, 9]],
        ];
    }

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

    /** Geometry for the main area chart at the current granularity. */
    private function chart(): array
    {
        $ds = $this->datasets()[$this->granularity];
        $data = $ds['data'];
        $w = 820;
        $h = 240;
        $padL = 8;
        $padR = 8;
        $padB = 26;
        $padT = 8;
        $innerW = $w - $padL - $padR;
        $innerH = $h - $padT - $padB;
        $max = max($data) * 1.1;
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

        $labels = [];
        foreach ($ds['labels'] as $i => $label) {
            $labels[] = ['x' => round($xs[$i], 1), 'label' => $label];
        }

        $dots = [];
        foreach ($xs as $i => $x) {
            $dots[] = ['x' => round($x, 1), 'y' => round($ys[$i], 1)];
        }

        return [
            'line' => $linePts,
            'area' => $linePts.' '.round(end($xs), 1).','.($padT + $innerH).' '.$padL.','.($padT + $innerH),
            'grid' => $grid,
            'labels' => $labels,
            'dots' => $dots,
            'gridX2' => $w - $padR,
            'total' => $ds['total'],
        ];
    }

    public function render(): View
    {
        $metrics = array_map(function (array $m) {
            $m['sparkPts'] = self::spark($m['spark']);

            return $m;
        }, $this->metrics());

        $actors = [
            ['name' => 'Marina Rocha', 'meta' => 'User · ator_8821', 'count' => '1.842', 'spark' => [6, 8, 7, 10, 12, 11, 14]],
            ['name' => 'Acme Team', 'meta' => 'Team · team_204', 'count' => '1.530', 'spark' => [10, 9, 12, 11, 13, 12, 15]],
            ['name' => 'João Silva', 'meta' => 'User · ator_3390', 'count' => '1.214', 'spark' => [8, 7, 9, 8, 10, 9, 11]],
            ['name' => 'Beatriz Lima', 'meta' => 'User · ator_7745', 'count' => '982', 'spark' => [5, 6, 5, 7, 6, 8, 7]],
            ['name' => 'Pedro Alves', 'meta' => 'User · ator_1182', 'count' => '774', 'spark' => [4, 5, 4, 6, 5, 5, 6]],
        ];
        $actors = array_map(function (array $a) {
            $a['sparkPts'] = self::spark($a['spark']);

            return $a;
        }, $actors);

        return view('trail::livewire.overview', [
            'metrics' => $metrics,
            'chart' => $this->chart(),
            'topEvents' => [
                ['name' => 'order.placed', 'count' => '48.2k', 'pct' => 100],
                ['name' => 'onboarding.step_completed', 'count' => '37.9k', 'pct' => 79],
                ['name' => 'user.signed_up', 'count' => '31.7k', 'pct' => 66],
                ['name' => 'whatsapp.connected', 'count' => '18.3k', 'pct' => 38],
                ['name' => 'cart.updated', 'count' => '12.4k', 'pct' => 26],
                ['name' => 'invoice.paid', 'count' => '6.1k', 'pct' => 13],
            ],
            'topActors' => $actors,
        ])->layout('trail::layout', ['active' => 'overview', 'title' => 'Overview']);
    }
}
