{{-- Rodape padrao da sidebar. Sobrescreva via resources/views/vendor/trail/partials/ ou config('trail.branding.footer_view'). --}}
@php($trailUser = auth()->user())
<div class="px-3 py-3 border-t space-y-1" style="border-color: var(--trail-border);">
  @if ($trailBackUrl = config('trail.branding.back_url'))
    <a class="trail-nav-item w-full" href="{{ $trailBackUrl }}" style="background:transparent">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      <span>{{ config('trail.branding.back_label') }}</span>
    </a>
  @endif
  <button class="trail-nav-item w-full" id="themeToggle" style="background:transparent">
    <svg class="dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
    <svg class="hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
    <span class="dark:hidden">Tema claro</span><span class="hidden dark:inline">Tema escuro</span>
  </button>
  @if ($trailUser)
    @php($trailName = $trailUser->name ?? 'Usuario')
    @php($trailParts = preg_split('/\s+/', trim($trailName)) ?: [])
    @php($trailInitials = mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($trailParts, 0, 2)))))
    <div class="flex items-center gap-2.5 px-2 pt-2">
      <span class="trail-avatar">{{ $trailInitials }}</span>
      <div class="leading-tight min-w-0">
        <div class="text-xs font-medium truncate">{{ $trailName }}</div>
        @if (! empty($trailUser->email))
          <div class="ds-label truncate">{{ $trailUser->email }}</div>
        @endif
      </div>
    </div>
  @endif
</div>
