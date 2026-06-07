@use('Trail\Trail\Livewire\Sample')
@php
    $icons = [
        'auth' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'commerce' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>',
        'onboarding' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>',
        'integration' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2v6M15 2v6M7 8h10v3a5 5 0 0 1-10 0z"/><path d="M12 16v6"/></svg>',
        'system' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
    ];
    $chip = fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? '['.count($v).']' : (is_string($v) ? $v : (string) $v));
@endphp

<div class="flex-1 flex flex-col min-w-0">
  <style>
    .tl-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid var(--trail-border); }
    .tl-stat-row:last-child { border-bottom: 0; }
    .tl-stat-k { font-size: 12px; color: var(--trail-text-subtle); }
    .tl-stat-v { font-size: 13px; font-weight: 600; }
  </style>

  <header class="flex items-center justify-between px-6 border-b shrink-0" style="height: var(--trail-header-h); border-color: var(--trail-border);">
    <h1 class="text-[18px] font-semibold tracking-tight">Subject Timeline</h1>
    <div style="position:relative;width:300px" x-data="{ open: false }" x-on:click.outside="open = false">
      <div class="trail-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input class="trail-input" wire:model.live.debounce.300ms="actorSearch" x-on:focus="open = true" placeholder="Trocar de ator (nome ou id)…" autocomplete="off">
      </div>
      <div class="trail-menu trail-scroll" x-show="open" x-cloak style="position:absolute;top:calc(100% + 6px);right:0;left:0;z-index:30;max-height:320px;overflow:auto">
        @forelse ($results as $a)
          <div class="trail-menu-item" wire:click="selectActor('{{ $a['key'] }}')" x-on:click="open = false" @if ($a['key'] === $actor['key']) aria-checked="true" @endif>
            <span class="trail-avatar trail-avatar-sm">{{ Sample::initials($a['name']) }}</span>
            <div style="min-width:0;flex:1"><div style="font-size:13px;color:var(--trail-text)">{{ $a['name'] }}</div><div class="ds-label">{{ $a['type'] }} · {{ $a['id'] }}</div></div>
            @if ($a['key'] === $actor['key'])<span class="trail-badge trail-badge-accent">atual</span>@endif
          </div>
        @empty
          <div class="trail-menu-item" style="pointer-events:none;color:var(--trail-text-faint)">Nenhum ator encontrado</div>
        @endforelse
      </div>
    </div>
  </header>

  <div class="flex-1 overflow-hidden grid" style="grid-template-columns: 320px 1fr;">

    <!-- Profile rail -->
    <div class="border-r overflow-auto trail-scroll p-5" style="border-color: var(--trail-border);">
      <div class="flex flex-col items-center text-center pb-4">
        <span class="trail-avatar" style="width:64px;height:64px;font-size:22px">{{ Sample::initials($actor['name']) }}</span>
        <div class="mt-3 text-[17px] font-semibold tracking-tight">{{ $actor['name'] }}</div>
        <div class="flex items-center gap-2 mt-1.5">
          <span class="trail-badge trail-badge-outline">{{ $actor['type'] }}</span>
          <span class="trail-tag">{{ $actor['id'] }}</span>
        </div>
        <div class="ds-label mt-2">{{ $actor['email'] }}</div>
      </div>
      <div class="trail-card trail-card-pad" style="padding:6px 16px">
        <div class="tl-stat-row"><span class="tl-stat-k">Total de eventos</span><span class="tl-stat-v trail-tnum">{{ $stats['total'] }}</span></div>
        <div class="tl-stat-row"><span class="tl-stat-k">Sessões</span><span class="tl-stat-v trail-tnum">{{ $stats['sessions'] }}</span></div>
        <div class="tl-stat-row"><span class="tl-stat-k">Primeira vez visto</span><span class="tl-stat-v">{{ $stats['first'] }}</span></div>
        <div class="tl-stat-row"><span class="tl-stat-k">Última atividade</span><span class="tl-stat-v">{{ $stats['last'] }}</span></div>
        <div class="tl-stat-row"><span class="tl-stat-k">Evento mais comum</span><span class="tl-stat-v trail-mono" style="font-size:11px">{{ $stats['top_event'] }}</span></div>
      </div>
      <div class="trail-card trail-card-pad mt-3">
        <div class="trail-eyebrow mb-3">Atividade · 7 dias</div>
        <div style="display:flex;align-items:flex-end;gap:6px;height:48px">
          @foreach ($stats['bars'] as $b)
            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%"><div title="{{ $b }} eventos" style="height:{{ max(4, $b / $stats['max_bar'] * 100) }}%;background:{{ $b ? 'var(--trail-accent)' : 'var(--trail-surface-3)' }};border-radius:3px"></div></div>
          @endforeach
        </div>
        <div style="display:flex;gap:6px;margin-top:6px">
          @foreach (['S', 'T', 'Q', 'Q', 'S', 'S', 'D'] as $d)
            <div style="flex:1;text-align:center" class="ds-label">{{ $d }}</div>
          @endforeach
        </div>
      </div>
      <button class="trail-btn trail-btn-secondary w-full mt-3" x-data x-on:click="navigator.clipboard && navigator.clipboard.writeText('{{ $actor['id'] }}')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Copiar ID do ator
      </button>
    </div>

    <!-- Timeline -->
    <div class="overflow-auto trail-scroll">
      <div class="px-7 pt-5 pb-2 sticky top-0 z-10" style="background: color-mix(in srgb, var(--trail-bg) 88%, transparent); backdrop-filter: blur(6px);">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="ds-label mr-1" style="align-self:center">{{ count($events) }} eventos</span>
          @foreach ($types as $t)
            @php($cat = $cats[$t['cat']])
            <button class="trail-badge" wire:click="toggleType('{{ $t['name'] }}')" style="cursor:pointer;height:24px;{{ $t['on'] ? "background:{$cat['color']};color:#fff;border-color:transparent" : 'background:var(--trail-surface-2);color:var(--trail-text-muted);border:1px solid var(--trail-border)' }}">
              <span class="cat-dot" style="width:6px;height:6px;border-radius:2px;background:{{ $t['on'] ? '#fff' : $cat['color'] }};display:inline-block"></span>
              <span class="trail-mono" style="font-size:11px">{{ $t['name'] }}</span>
            </button>
          @endforeach
        </div>
      </div>

      <div class="px-7 pb-10">
        @if ($empty)
          <div class="trail-empty">
            <div class="trail-empty-glyph"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
            <div class="trail-empty-title">Nenhum evento com esse filtro</div>
            <div class="trail-empty-body">Remova os filtros de tipo para ver toda a linha do tempo deste ator.</div>
          </div>
        @else
          @foreach ($groups as $g)
            <div class="trail-tl-day">
              <span class="trail-tl-day-label">{{ $g['label'] }}</span>
              <span class="ds-label">{{ $g['date'] }} · {{ count($g['items']) }} evento{{ count($g['items']) > 1 ? 's' : '' }}</span>
              <span class="trail-tl-day-line"></span>
            </div>
            <div class="trail-timeline">
              @foreach ($g['items'] as $e)
                @php($c = $cats[$e['cat']])
                <div class="trail-tl-row">
                  <div class="trail-tl-node" style="color:{{ $c['color'] }};border-color:color-mix(in srgb, {{ $c['color'] }} 35%, var(--trail-border))">{!! $icons[$e['cat']] !!}</div>
                  <div class="trail-tl-card" x-data="{ exp: false }" :class="exp ? 'expanded' : ''" x-on:click="exp = !exp">
                    <div class="flex items-center gap-2">
                      <span class="trail-mono" style="font-size:13px;color:var(--trail-text)">{{ $e['name'] }}</span>
                      @if ($e['value'] !== null)<span class="trail-badge trail-badge-success">{{ number_format($e['value'], 2, ',', '.') }}</span>@endif
                      <span class="ds-label ml-auto" style="white-space:nowrap">{{ $e['clock'] }} · {{ $e['relative'] }}</span>
                      <svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--trail-text-faint)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="exp ? 'transform:rotate(90deg)' : ''" style="transition:transform var(--trail-dur) var(--trail-ease)"><path d="m9 18 6-6-6-6"/></svg>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap mt-2">
                      @foreach (array_slice($e['props'], 0, 4, true) as $k => $v)
                        <span class="prop-chip">{{ $k }}={{ $chip($v) }}</span>
                      @endforeach
                    </div>
                    <div x-show="exp" x-cloak style="margin-top:10px"><pre class="trail-code">{!! Sample::highlightJson(['properties' => $e['props'], 'context' => $e['context']]) !!}</pre></div>
                  </div>
                </div>
              @endforeach
            </div>
          @endforeach
        @endif
      </div>
    </div>
  </div>
</div>
