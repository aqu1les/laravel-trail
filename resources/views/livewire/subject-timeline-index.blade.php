@use('Trail\Trail\Livewire\Sample')

<div class="flex-1 flex flex-col min-w-0">

  <header class="flex items-center justify-between px-6 border-b shrink-0" style="height: var(--trail-header-h); border-color: var(--trail-border);">
    <div class="flex items-center gap-3">
      <h1 class="text-[18px] font-semibold tracking-tight">Subject Timeline</h1>
      <span class="ds-label">{{ number_format($total, 0, ',', '.') }} atores</span>
    </div>
  </header>

  <!-- Filter bar -->
  <div class="flex items-center gap-2.5 px-6 py-3 border-b shrink-0" style="border-color: var(--trail-border);">
    <div class="trail-search" style="width:280px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      <input class="trail-input" wire:model.live.debounce.300ms="indexSearch" placeholder="Buscar por nome, email ou ID…">
    </div>

    @if (count($distinctTypes) > 1)
      <div style="position:relative" x-data="{ open: false }" @click.outside="open = false">
        <button class="trail-btn trail-btn-secondary" @click="open = !open">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span>{{ $typeFilter !== '' ? collect($distinctTypes)->firstWhere('value', $typeFilter)['label'] ?? 'Tipo' : 'Todos os tipos' }}</span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="trail-menu trail-scroll" x-show="open" x-cloak @click="open = false" style="position:absolute;top:calc(100% + 6px);left:0;z-index:20;min-width:160px">
          <a class="trail-menu-item" aria-checked="{{ $typeFilter === '' ? 'true' : 'false' }}" href="{{ route('trail.timeline', array_filter(['q' => $indexSearch])) }}">
            <span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>
            Todos os tipos
          </a>
          @foreach ($distinctTypes as $t)
            <a class="trail-menu-item" aria-checked="{{ $typeFilter === $t['value'] ? 'true' : 'false' }}" href="{{ route('trail.timeline', array_filter(['type_filter' => $t['value'], 'q' => $indexSearch])) }}">
              <span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>
              {{ $t['label'] }}
            </a>
          @endforeach
        </div>
      </div>
    @endif

    <div class="flex-1"></div>
    @if ($typeFilter !== '' || $indexSearch !== '')
      <a class="trail-btn trail-btn-ghost trail-btn-sm" href="{{ route('trail.timeline') }}">Limpar filtros</a>
    @endif
  </div>

  <!-- Table -->
  <div class="flex-1 overflow-auto trail-scroll">
    @if (count($actors))
      <table class="trail-table trail-table-rows">
        <thead>
          <tr>
            <th style="width:36%">Ator</th>
            <th style="width:14%">Tipo</th>
            <th>ID</th>
            <th style="width:150px;text-align:right">Total de eventos</th>
            <th style="width:150px;text-align:right">Última atividade</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($actors as $a)
            <tr wire:key="idx-{{ $loop->index }}" onclick="location.href='{{ route('trail.timeline', ['actor' => $a['key']]) }}'" style="cursor:pointer">
              <td>
                <div class="flex items-center gap-2.5">
                  <span class="trail-avatar trail-avatar-sm">{{ Sample::initials($a['name']) }}</span>
                  <div style="min-width:0">
                    <div class="text-[13px] truncate">{{ $a['name'] }}</div>
                    @if ($a['email'])
                      <div class="ds-label truncate">{{ $a['email'] }}</div>
                    @endif
                  </div>
                </div>
              </td>
              <td><span class="trail-badge trail-badge-outline">{{ $a['type'] }}</span></td>
              <td class="trail-mono" style="font-size:12px;color:var(--trail-text-subtle)">{{ $a['id'] }}</td>
              <td style="text-align:right" class="trail-tnum">{{ number_format($a['total'], 0, ',', '.') }}</td>
              <td style="text-align:right;white-space:nowrap" class="ds-label">
                {{ $a['last_seen'] ? Sample::relative($a['last_seen']) : '-' }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="trail-empty">
        <div class="trail-empty-glyph"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <div class="trail-empty-title">Nenhum ator encontrado</div>
        <div class="trail-empty-body">Ajuste a busca ou os filtros para ver atores.</div>
        @if ($typeFilter !== '' || $indexSearch !== '')
          <button class="trail-btn trail-btn-secondary trail-btn-sm" style="margin-top:8px" wire:click="clearIndex">Limpar filtros</button>
        @endif
      </div>
    @endif
  </div>

  <!-- Pagination -->
  @if ($totalPages > 1)
    <div class="flex items-center justify-center gap-3 px-6 py-3 border-t shrink-0" style="border-color: var(--trail-border);">
      <button class="trail-btn trail-btn-secondary trail-btn-sm" wire:click="$set('page', {{ max(1, $page - 1) }})" @disabled($page <= 1)>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Anterior
      </button>
      <span class="ds-label">Página {{ $page }} de {{ $totalPages }}</span>
      <button class="trail-btn trail-btn-secondary trail-btn-sm" wire:click="$set('page', {{ min($totalPages, $page + 1) }})" @disabled($page >= $totalPages)>
        Próxima
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>
  @endif

</div>
