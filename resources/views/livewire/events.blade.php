@use('Trail\Trail\Livewire\Sample')
@php
    $chip = fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? '['.count($v).']' : (is_string($v) ? $v : (string) $v));
    $actorLabel = $actorFilter
        ? (collect($actors)->firstWhere('key', $actorFilter)['name'] ?? 'Ator')
        : 'Todos os atores';
@endphp

<div class="flex-1 flex flex-col min-w-0">
  <header class="flex items-center justify-between px-6 border-b shrink-0" style="height: var(--trail-header-h); border-color: var(--trail-border);">
    <div class="flex items-center gap-3">
      <h1 class="text-[18px] font-semibold tracking-tight">Events</h1>
      <span class="ds-label">{{ number_format(count($visible), 0, ',', '.') }} eventos</span>
    </div>
    <div class="flex items-center gap-2.5">
      <button class="trail-btn trail-btn-secondary" wire:click="toggleLive">
        <span class="trail-dot trail-dot-live" style="{{ $live ? '' : 'animation-play-state:paused;background:var(--trail-text-faint)' }}"></span>
        <span>{{ $live ? 'Ao vivo' : 'Pausado' }}</span>
      </button>
      <div class="trail-segmented">
        <button class="{{ $period === 'Hoje' ? 'is-active' : '' }}" wire:click="$set('period', 'Hoje')">Hoje</button>
        <button class="{{ $period === '7d' ? 'is-active' : '' }}" wire:click="$set('period', '7d')">7d</button>
        <button class="{{ $period === '30d' ? 'is-active' : '' }}" wire:click="$set('period', '30d')">30d</button>
      </div>
      <button class="trail-btn trail-btn-secondary" wire:click="togglePageViews">
        <span>{{ $showPageViews ? 'Esconder page views' : 'Mostrar page views' }}</span>
      </button>
    </div>
  </header>

  <!-- Filter bar -->
  <div class="flex items-center gap-2.5 px-6 py-3 border-b shrink-0" style="border-color: var(--trail-border);">
    <div class="trail-search" style="width:280px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      <input class="trail-input" wire:model.live.debounce.300ms="search" placeholder="Buscar evento, ator, propriedade…">
    </div>

    <div style="position:relative" x-data="{ open: false }" @click.outside="open = false">
      <button class="trail-btn trail-btn-secondary" @click="open = !open">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18M6 12h12M10 20h4"/></svg>
        <span>Evento</span>
        @if (count($eventFilter))<span class="trail-badge trail-badge-accent">{{ count($eventFilter) }}</span>@endif
      </button>
      <div class="trail-menu trail-scroll" x-show="open" x-cloak style="position:absolute;top:calc(100% + 6px);left:0;z-index:20;max-height:300px;overflow:auto">
        @foreach ($names as $n)
          <div class="trail-menu-item" aria-checked="{{ in_array($n, $eventFilter, true) ? 'true' : 'false' }}" wire:click="toggleEvent('{{ $n }}')">
            <span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>
            <span class="trail-mono" style="font-size:12px">{{ $n }}</span>
          </div>
        @endforeach
      </div>
    </div>

    <div style="position:relative" x-data="{ open: false }" @click.outside="open = false">
      <button class="trail-btn trail-btn-secondary" @click="open = !open">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>{{ $actorLabel }}</span>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      <div class="trail-menu trail-scroll" x-show="open" x-cloak @click="open = false" style="position:absolute;top:calc(100% + 6px);left:0;z-index:20;max-height:300px;overflow:auto">
        <div class="trail-menu-item" aria-checked="{{ $actorFilter ? 'false' : 'true' }}" wire:click="setActor(null)">
          <span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>Todos os atores
        </div>
        @foreach ($actors as $a)
          <div class="trail-menu-item" aria-checked="{{ $actorFilter === $a['key'] ? 'true' : 'false' }}" data-key="{{ $a['key'] }}" @click="$wire.setActor($el.dataset.key)">
            <span class="trail-avatar trail-avatar-sm">{{ Sample::initials($a['name']) }}</span>
            <div style="min-width:0"><div style="font-size:13px;color:var(--trail-text)">{{ $a['name'] }}</div><div class="ds-label">{{ $a['type'] }} · {{ $a['id'] }}</div></div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="flex-1"></div>
    @if ($hasFilters)
      <button class="trail-btn trail-btn-ghost trail-btn-sm" wire:click="clearFilters">Limpar filtros</button>
    @endif
  </div>

  <!-- Table -->
  <div class="flex-1 overflow-auto trail-scroll" @if ($live) wire:poll.3s="tick" @endif>
    @if (count($visible))
      <table class="trail-table trail-table-rows">
        <thead>
          <tr>
            <th style="width:34%">Evento</th>
            <th style="width:22%">Ator</th>
            <th>Propriedades</th>
            <th style="width:110px;text-align:right">Valor</th>
            <th style="width:96px;text-align:right">Quando</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($visible as $e)
            <tr wire:key="evt-{{ $e['id'] }}" wire:click="select({{ $e['id'] }})" class="{{ $e['id'] === $newId ? 'trail-row-new' : '' }}">
              <td><div class="flex items-center gap-2.5">
                <span class="cat-dot" style="background:{{ $e['color'] }}"></span>
                <span class="trail-mono" style="font-size:13px">{{ $e['name'] }}</span>
              </div></td>
              <td><div class="flex items-center gap-2">
                <span class="trail-avatar trail-avatar-sm">{{ Sample::initials($e['actor']['name']) }}</span>
                <div class="min-w-0"><div class="text-[13px] truncate">{{ $e['actor']['name'] }}</div></div>
              </div></td>
              <td><div class="flex items-center gap-1.5 flex-wrap" style="max-height:22px;overflow:hidden">
                @foreach (array_slice($e['props'], 0, 3, true) as $k => $v)
                  <span class="prop-chip">{{ $k }}={{ $chip($v) }}</span>
                @endforeach
              </div></td>
              <td style="text-align:right" class="trail-tnum">
                @if ($e['value'] !== null)
                  <span class="trail-mono" style="color:var(--trail-success)">{{ number_format($e['value'], 2, ',', '.') }}</span>
                @else
                  <span style="color:var(--trail-text-faint)">—</span>
                @endif
              </td>
              <td style="text-align:right;white-space:nowrap" class="ds-label">{{ Sample::relative($e['ts']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="flex items-center justify-center py-4 text-xs" style="color:var(--trail-text-faint)">
        <span class="trail-skeleton" style="width:120px;height:10px"></span>
      </div>
    @else
      <div class="trail-empty">
        <div class="trail-empty-glyph"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></div>
        <div class="trail-empty-title">Nenhum evento corresponde</div>
        <div class="trail-empty-body">Ajuste a busca ou os filtros para ver eventos.</div>
        <button class="trail-btn trail-btn-secondary trail-btn-sm" style="margin-top:8px" wire:click="clearFilters">Limpar filtros</button>
      </div>
    @endif
  </div>

  <!-- Drawer -->
  <div x-data="{ open: false }" @drawer-open.window="open = true" @keydown.escape.window="open = false">
    <div class="trail-drawer-overlay" @click="open = false" :style="open ? 'opacity:1;pointer-events:auto' : 'opacity:0;pointer-events:none'"></div>
    <aside class="trail-drawer trail-scroll" style="overflow:auto" :style="open ? 'transform:translateX(0)' : 'transform:translateX(110%)'">
      @if ($selected)
        <div x-data="{ tab: 'props' }">
          <div class="trail-card-head" style="border-radius:0;position:sticky;top:0;background:var(--trail-surface);z-index:2">
            <div class="flex items-center gap-2 min-w-0">
              <span class="cat-dot" style="background:{{ $selected['color'] }}"></span>
              <span class="trail-tag" style="font-size:13px">{{ $selected['name'] }}</span>
              @if ($selected['value'] !== null)
                <span class="trail-badge trail-badge-success">value {{ number_format($selected['value'], 2, ',', '.') }}</span>
              @endif
            </div>
            <button class="trail-icon-btn" @click="open = false"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
          </div>
          <div class="p-5">
            <div class="flex items-center gap-3 mb-4">
              <span class="trail-avatar trail-avatar-lg">{{ Sample::initials($selected['actor']['name']) }}</span>
              <div class="min-w-0">
                <div class="font-semibold text-sm flex items-center gap-2">{{ $selected['actor']['name'] }}<span class="trail-badge trail-badge-outline">{{ $selected['actor']['type'] }}</span></div>
                <div class="ds-label">{{ $selected['actor']['id'] }} · {{ Sample::relative($selected['ts']) }}</div>
              </div>
              @if (($selected['subject_key'] ?? '') !== '')
                <a href="{{ route('trail.timeline', ['actor' => $selected['subject_key']]) }}" class="trail-btn trail-btn-ghost trail-btn-sm ml-auto shrink-0">Ver timeline →</a>
              @endif
            </div>
            <div class="trail-tabs mb-3">
              <button :aria-selected="tab === 'props' ? 'true' : 'false'" @click="tab = 'props'">Propriedades</button>
              <button :aria-selected="tab === 'context' ? 'true' : 'false'" @click="tab = 'context'">Contexto</button>
              <button :aria-selected="tab === 'raw' ? 'true' : 'false'" @click="tab = 'raw'">Raw JSON</button>
            </div>
            <pre class="trail-code" x-show="tab === 'props'">{!! Sample::highlightJson($selected['props']) !!}</pre>
            <pre class="trail-code" x-show="tab === 'context'" x-cloak>{!! Sample::highlightJson($selected['context']) !!}</pre>
            <pre class="trail-code" x-show="tab === 'raw'" x-cloak>{!! Sample::highlightJson(['event' => $selected['name'], 'value' => $selected['value'], 'properties' => $selected['props'], 'context' => $selected['context']]) !!}</pre>
          </div>
        </div>
      @endif
    </aside>
  </div>
</div>
