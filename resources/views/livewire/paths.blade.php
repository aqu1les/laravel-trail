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

  <div class="flex-1 overflow-auto trail-scroll px-6 py-5">
    @if (count($rows))
      @foreach ($rows as $row)
        <a href="{{ $row['href'] }}" class="block">
          <span>{{ $row['name'] }}</span>
          @foreach ($row['steps'] as $step)
            <span>{{ $step['gap'] }} {{ $step['name'] }}</span>
          @endforeach
          <span class="ds-label">{{ $row['when'] }}</span>
        </a>
      @endforeach

      @if ($totalPages > 1)
        <div class="flex items-center gap-2 pt-4">
          @for ($i = 1; $i <= $totalPages; $i++)
            <button class="{{ $page === $i ? 'is-active' : '' }}" wire:click="gotoPage({{ $i }})">{{ $i }}</button>
          @endfor
        </div>
      @endif
    @else
      <div class="trail-empty">
        <div class="trail-empty-title">Nenhum ator neste caminho</div>
        <div class="trail-empty-body">Nenhum ator saiu de <b>{{ $startEvent }}</b>@if ($endEvent) e chegou a <b>{{ $endEvent }}</b>@endif nesta janela.</div>
      </div>
    @endif
  </div>
</div>
