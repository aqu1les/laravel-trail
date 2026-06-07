@use('Trail\Trail\Livewire\Sample')

<div class="flex-1 flex flex-col min-w-0">
  <!-- Header -->
  <header class="flex items-center justify-between px-6 border-b shrink-0" style="height: var(--trail-header-h); border-color: var(--trail-border);">
    <div class="flex items-center gap-3">
      <h1 class="text-[18px] font-semibold tracking-tight">Overview</h1>
      <span class="trail-badge" style="background:var(--trail-success-subtle);color:var(--trail-success)"><span class="trail-dot trail-dot-live"></span>tracking ativo</span>
    </div>
    <div class="flex items-center gap-2.5">
      <div class="trail-search" style="width:240px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input class="trail-input" placeholder="Buscar eventos, atores…">
      </div>
      <div class="trail-segmented">
        <button class="{{ $period === 'Hoje' ? 'is-active' : '' }}" wire:click="$set('period', 'Hoje')">Hoje</button>
        <button class="{{ $period === '7d' ? 'is-active' : '' }}" wire:click="$set('period', '7d')">7d</button>
        <button class="{{ $period === '30d' ? 'is-active' : '' }}" wire:click="$set('period', '30d')">30d</button>
      </div>
      <button class="trail-btn trail-btn-secondary trail-btn-icon" title="Período custom">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      </button>
    </div>
  </header>

  <!-- Scroll content -->
  <div class="flex-1 overflow-auto trail-scroll px-6 py-5">

    <!-- Metric cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
      @foreach ($metrics as $m)
        @php($color = ($m['accent'] ?? false) ? 'var(--trail-accent)' : 'var(--trail-text-faint)')
        <div class="trail-card trail-card-pad">
          <div class="trail-eyebrow mb-2">{{ $m['label'] }}</div>
          <div class="flex items-end justify-between gap-2">
            <div class="min-w-0">
              <div class="{{ ($m['mono'] ?? false) ? 'trail-mono text-[17px] font-semibold' : 'text-[26px] font-bold trail-tnum' }} tracking-tight leading-none truncate">{{ $m['value'] }}</div>
              <div class="mt-2 flex items-center gap-1.5">
                @if (! empty($m['delta']))
                  <span class="trail-delta {{ $m['dir'] === 'up' ? 'trail-delta-up' : ($m['dir'] === 'down' ? 'trail-delta-down' : 'trail-delta-flat') }}">
                    @if ($m['dir'] === 'up')
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    @elseif ($m['dir'] === 'down')
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7 17 17M17 9v8H9"/></svg>
                    @endif
                    {{ $m['delta'] }}
                  </span>
                @endif
                <span class="ds-label">{{ $m['sub'] ?? 'vs 7d ant.' }}</span>
              </div>
            </div>
            @if (! empty($m['sparkPts']))
              <div class="shrink-0">
                <svg width="88" height="32" viewBox="0 0 88 32" preserveAspectRatio="none" style="overflow:visible">
                  <polygon points="{{ $m['sparkPts']['area'] }}" fill="{{ $color }}" opacity="0.12"/>
                  <polyline points="{{ $m['sparkPts']['line'] }}" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                </svg>
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    <!-- Main chart -->
    <div class="trail-card mb-4">
      <div class="trail-card-head">
        <div class="flex items-center gap-3">
          <h3 class="trail-card-title">Eventos ao longo do tempo</h3>
          <span class="inline-flex items-center gap-1.5 text-xs" style="color:var(--trail-text-subtle)"><span style="width:8px;height:8px;border-radius:2px;background:var(--trail-accent);display:inline-block"></span>todos os eventos</span>
        </div>
        <div class="trail-segmented">
          <button class="{{ $granularity === 'Hora' ? 'is-active' : '' }}" wire:click="$set('granularity', 'Hora')">Hora</button>
          <button class="{{ $granularity === 'Dia' ? 'is-active' : '' }}" wire:click="$set('granularity', 'Dia')">Dia</button>
          <button class="{{ $granularity === 'Semana' ? 'is-active' : '' }}" wire:click="$set('granularity', 'Semana')">Semana</button>
        </div>
      </div>
      <div class="trail-card-pad">
        <div class="flex items-baseline gap-3 mb-4">
          <span class="text-[28px] font-bold tracking-tight trail-tnum leading-none">{{ $chart['total'] }}</span>
          <span class="ds-label">eventos no período</span>
        </div>
        <svg width="100%" height="240" viewBox="0 0 820 240" preserveAspectRatio="none" style="display:block;overflow:visible">
          <defs><linearGradient id="trail-ag" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="var(--trail-accent)" stop-opacity="0.22"/>
            <stop offset="100%" stop-color="var(--trail-accent)" stop-opacity="0"/>
          </linearGradient></defs>
          @foreach ($chart['grid'] as $y)
            <line stroke="var(--trail-border)" stroke-width="1" vector-effect="non-scaling-stroke" x1="8" y1="{{ $y }}" x2="{{ $chart['gridX2'] }}" y2="{{ $y }}"/>
          @endforeach
          <polygon points="{{ $chart['area'] }}" fill="url(#trail-ag)"/>
          <polyline points="{{ $chart['line'] }}" fill="none" stroke="var(--trail-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
          @foreach ($chart['dots'] as $d)
            <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="2.5" fill="var(--trail-surface)" stroke="var(--trail-accent)" stroke-width="1.5"/>
          @endforeach
          @foreach ($chart['labels'] as $l)
            <text fill="var(--trail-text-faint)" style="font-size:11px;font-family:var(--trail-font-mono)" x="{{ $l['x'] }}" y="232" text-anchor="middle">{{ $l['label'] }}</text>
          @endforeach
        </svg>
      </div>
    </div>

    <!-- Two columns -->
    <div class="grid lg:grid-cols-2 gap-4">
      <div class="trail-card">
        <div class="trail-card-head">
          <h3 class="trail-card-title">Top eventos</h3>
          <span class="ds-label">por contagem · 7d</span>
        </div>
        <div class="trail-card-pad space-y-3.5">
          @foreach ($topEvents as $e)
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <span class="trail-tag">{{ $e['name'] }}</span>
                <span class="text-xs trail-tnum" style="color:var(--trail-text-subtle)">{{ $e['count'] }}</span>
              </div>
              <div class="trail-bar-track"><div class="trail-bar-fill" style="width:{{ $e['pct'] }}%"></div></div>
            </div>
          @endforeach
        </div>
      </div>

      <div class="trail-card">
        <div class="trail-card-head">
          <h3 class="trail-card-title">Atores mais ativos</h3>
          <span class="ds-label">por eventos · 7d</span>
        </div>
        <div class="px-2 py-1.5">
          @foreach ($topActors as $a)
            <div class="flex items-center gap-3 px-2 rounded-md" style="height:48px;transition:background .12s" x-data x-on:mouseover="$el.style.background='var(--trail-surface-2)'" x-on:mouseout="$el.style.background='transparent'">
              <span class="trail-avatar">{{ Sample::initials($a['name']) }}</span>
              <div class="min-w-0 flex-1">
                <div class="text-[13px] font-medium truncate">{{ $a['name'] }}</div>
                <div class="ds-label truncate">{{ $a['meta'] }}</div>
              </div>
              @if (! empty($a['sparkPts']))
                <div class="shrink-0">
                  <svg width="88" height="32" viewBox="0 0 88 32" preserveAspectRatio="none" style="overflow:visible">
                    <polygon points="{{ $a['sparkPts']['area'] }}" fill="var(--trail-text-faint)" opacity="0.12"/>
                    <polyline points="{{ $a['sparkPts']['line'] }}" fill="none" stroke="var(--trail-text-faint)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                  </svg>
                </div>
              @endif
              <div class="text-[13px] font-semibold trail-tnum shrink-0 w-12 text-right">{{ $a['count'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
    <div class="h-4"></div>
  </div>
</div>
