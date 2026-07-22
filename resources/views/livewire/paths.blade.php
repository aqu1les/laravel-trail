<div class="flex-1 flex flex-col min-w-0">
  <header class="flex items-center justify-between px-6 border-b shrink-0" style="height: var(--trail-header-h); border-color: var(--trail-border);">
    <div class="flex items-center gap-3">
      <h1 class="text-[18px] font-semibold tracking-tight">Paths</h1>
      <span class="ds-label">{{ number_format($total, 0, ',', '.') }} atores</span>
    </div>
    <div class="trail-segmented">
      <button class="{{ $since === 'today' ? 'is-active' : '' }}" wire:click="$set('since', 'today')">Hoje</button>
      <button class="{{ $since === '7d' ? 'is-active' : '' }}" wire:click="$set('since', '7d')">7d</button>
      <button class="{{ $since === '30d' ? 'is-active' : '' }}" wire:click="$set('since', '30d')">30d</button>
    </div>
  </header>

  {{-- Controls: the two event pickers, plus the (future) mode toggle. --}}
  <div class="flex items-center justify-between gap-3 px-6 border-b shrink-0" style="height:60px; border-color: var(--trail-border); background: var(--trail-surface);">
    <div class="flex items-center gap-2.5">
      <span class="ds-label" style="color: var(--trail-text-subtle); white-space: nowrap">A partir de</span>

      <div style="position:relative" x-data="{ open: false }" @click.outside="open = false">
        <button class="trail-btn trail-btn-secondary" @click="open = !open" @if ($startEvent === '') disabled @endif>
          <span class="trail-step trail-step-start">{{ $startEvent !== '' ? $startEvent : 'sem eventos' }}</span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="trail-menu trail-scroll" x-show="open" x-cloak @click="open = false" style="position:absolute;top:calc(100% + 6px);left:0;z-index:20;max-height:300px;overflow:auto;min-width:200px">
          @foreach ($names as $n)
            <div class="trail-menu-item" aria-checked="{{ $startEvent === $n ? 'true' : 'false' }}" wire:click="setStart('{{ $n }}')">
              <span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>
              <span class="trail-mono" style="font-size:12px">{{ $n }}</span>
            </div>
          @endforeach
        </div>
      </div>

      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--trail-text-faint)" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      <span class="ds-label" style="color: var(--trail-text-subtle); white-space: nowrap">Até</span>

      <div style="position:relative" x-data="{ open: false }" @click.outside="open = false">
        <button class="trail-btn trail-btn-secondary" @click="open = !open" @if ($startEvent === '') disabled @endif>
          @if ($endEvent)
            <span class="trail-step trail-step-end">{{ $endEvent }}</span>
          @else
            <span style="font-size:11px; color: var(--trail-text-subtle)">qualquer evento</span>
          @endif
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="trail-menu trail-scroll" x-show="open" x-cloak @click="open = false" style="position:absolute;top:calc(100% + 6px);left:0;z-index:20;max-height:300px;overflow:auto;min-width:200px">
          <div class="trail-menu-item" aria-checked="{{ $endEvent ? 'false' : 'true' }}" wire:click="setEnd(null)">
            <span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>
            qualquer evento
          </div>
          @foreach ($names as $n)
            @continue ($n === $startEvent)
            <div class="trail-menu-item" aria-checked="{{ $endEvent === $n ? 'true' : 'false' }}" wire:click="setEnd('{{ $n }}')">
              <span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>
              <span class="trail-mono" style="font-size:12px">{{ $n }}</span>
            </div>
          @endforeach
        </div>
      </div>

      @if ($endEvent)
        <button class="trail-btn trail-btn-ghost trail-btn-sm" wire:click="clearEnd">limpar</button>
      @endif
    </div>

    {{-- Árvore is the aggregate branch view, not built yet. --}}
    <div class="trail-segmented">
      <button disabled style="opacity:.5;cursor:default">Árvore <span class="trail-badge trail-badge-neutral" style="margin-left:6px">em breve</span></button>
      <button class="is-active">Sequências</button>
    </div>
  </div>

  <div class="flex-1 overflow-auto trail-scroll px-6 py-5">
    @if ($capped)
      <div class="trail-badge trail-badge-warning" style="margin-bottom:14px">
        Amostra limitada aos {{ number_format(\Trail\Trail\Queries\PathQuery::SUBJECT_CAP, 0, ',', '.') }} atores mais recentes desta janela.
      </div>
    @endif

    <div class="trail-card">
      <div class="trail-card-head">
        <h3 class="trail-card-title">Sequências por ator</h3>
        <span class="ds-label">evento inicial &rarr; tempo entre eventos</span>
      </div>

      @if ($startEvent === '')
        <div class="trail-empty">
          <div class="trail-empty-glyph">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          </div>
          <div class="trail-empty-title">Nenhum evento nesta janela</div>
          <div class="trail-empty-body">Não há eventos registrados neste período para reconstruir um caminho.</div>
        </div>
      @elseif (count($rows))
        <div style="padding:10px 12px 14px">
          @foreach ($rows as $row)
            <a href="{{ $row['href'] }}" class="flex items-center gap-3.5 px-3.5 py-3 rounded-lg hover:bg-surface-2" @if (! $loop->last) style="border-bottom:1px solid var(--trail-border)" @endif>
              <div class="flex items-center gap-2.5 shrink-0" style="width:180px">
                <span class="trail-avatar">{{ $row['initials'] }}</span>
                <div style="min-width:0">
                  <div style="font:500 13px var(--trail-font-sans); white-space:nowrap; overflow:hidden; text-overflow:ellipsis">{{ $row['name'] }}</div>
                  <div class="ds-label">{{ $row['type'] }} &middot; {{ $row['id'] }}</div>
                </div>
              </div>

              <div class="flex items-center flex-wrap flex-1" style="gap:2px">
                @foreach ($row['steps'] as $step)
                  @if (! $loop->first)
                    <span class="trail-step-conn">
                      <svg width="20" height="8" viewBox="0 0 20 8" fill="none" stroke="var(--trail-text-faint)" stroke-width="1.5"><path d="M0 4h16M13 1l4 3-4 3"/></svg>
                      <span>{{ $step['gap'] }}</span>
                    </span>
                  @endif
                  <span class="trail-step {{ $step['is_start'] ? 'trail-step-start' : '' }} {{ $step['is_end'] ? 'trail-step-end' : '' }}">{{ $step['name'] }}@if ($step['is_end']) &check; @endif</span>
                @endforeach
                {{-- A row can be both completed and truncated (the terminus was found
                     beyond maxSteps and appended as the final step): only render the
                     elision marker when the path was cut off before reaching an end,
                     never off the truncated flag alone. --}}
                @if ($row['truncated'] && ! $row['completed'])
                  <span class="trail-step-conn"><svg width="20" height="8" viewBox="0 0 20 8" fill="none" stroke="var(--trail-text-faint)" stroke-width="1.5"><path d="M0 4h16M13 1l4 3-4 3"/></svg></span>
                  <span class="trail-step trail-step-exit">&hellip;</span>
                @endif
              </div>

              <span class="ds-label shrink-0" style="width:60px; text-align:right">{{ $row['when'] }}</span>
            </a>
          @endforeach
        </div>

        @if ($totalPages > 1)
          <div class="flex items-center justify-center gap-3 px-6 py-3 border-t shrink-0" style="border-color: var(--trail-border);">
            <button class="trail-btn trail-btn-secondary trail-btn-sm" wire:click="gotoPage({{ $page - 1 }})" @disabled($page <= 1)>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
              Anterior
            </button>
            <span class="ds-label">Página {{ $page }} de {{ $totalPages }}</span>
            <button class="trail-btn trail-btn-secondary trail-btn-sm" wire:click="gotoPage({{ $page + 1 }})" @disabled($page >= $totalPages)>
              Próximo
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
          </div>
        @endif
      @else
        <div class="trail-empty">
          <div class="trail-empty-glyph">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          </div>
          <div class="trail-empty-title">Nenhum ator neste caminho</div>
          <div class="trail-empty-body">Nenhum ator saiu de <b>{{ $startEvent }}</b>@if ($endEvent) e chegou a <b>{{ $endEvent }}</b>@endif nesta janela.</div>
        </div>
      @endif
    </div>
  </div>
</div>
