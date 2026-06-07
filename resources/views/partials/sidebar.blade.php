{{-- Shared dashboard sidebar. $active ∈ overview|events|timeline|design-system --}}
@php($active = $active ?? '')
<aside class="flex flex-col shrink-0 border-r" style="width: var(--trail-sidebar-w); border-color: var(--trail-border);">
  <div class="flex items-center gap-2.5 px-4" style="height: var(--trail-header-h);">
    <div class="grid place-items-center w-7 h-7 rounded-md" style="background: var(--trail-accent);">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--trail-accent-fg)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18 L9 9 L14 14 L20 5"/><circle cx="20" cy="5" r="1.6" fill="var(--trail-accent-fg)" stroke="none"/></svg>
    </div>
    <span class="font-semibold text-[15px] tracking-tight">Trail</span>
  </div>

  <nav class="flex-1 px-3 py-2 space-y-0.5">
    <div class="trail-eyebrow px-2 pt-2 pb-1.5">Análise</div>

    <a class="trail-nav-item {{ $active === 'overview' ? 'is-active' : '' }}" href="{{ route('trail.dashboard') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Overview
    </a>

    <a class="trail-nav-item {{ $active === 'events' ? 'is-active' : '' }}" href="{{ route('trail.events') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>Events
      @if ($active === 'events')
        <span class="trail-badge trail-badge-accent ml-auto"><span class="trail-dot trail-dot-live" style="background:var(--trail-accent)"></span>live</span>
      @else
        <span class="trail-badge trail-badge-neutral ml-auto">live</span>
      @endif
    </a>

    <a class="trail-nav-item" style="opacity:.5;pointer-events:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18l-7 8v6l-4 2v-8z"/></svg>Funnels<span class="trail-badge trail-badge-neutral ml-auto">em breve</span>
    </a>

    <a class="trail-nav-item {{ $active === 'timeline' ? 'is-active' : '' }}" href="{{ route('trail.timeline') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Subject Timeline
    </a>

    <div class="trail-eyebrow px-2 pt-4 pb-1.5">Sistema</div>
    <a class="trail-nav-item {{ $active === 'design-system' ? 'is-active' : '' }}" href="{{ route('trail.design-system') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="6.5" cy="12" r="2.5"/><circle cx="16.5" cy="14" r="2.5"/><path d="M11 8.5 8.5 10.5M14.5 12.5 11 13"/></svg>Design System
    </a>

    @if (app()->environment('local'))
      <div class="trail-eyebrow px-2 pt-4 pb-1.5">Demo · dev</div>
      <a class="trail-nav-item {{ $active === 'demo-overview' ? 'is-active' : '' }}" href="{{ route('trail.demo') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 1.9 4.7L19 8l-3.5 3.4.8 4.9L12 14l-4.3 2.3.8-4.9L5 8l5.1-.3z"/></svg>Overview
      </a>
      <a class="trail-nav-item {{ $active === 'demo-events' ? 'is-active' : '' }}" href="{{ route('trail.demo.events') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>Events
      </a>
      <a class="trail-nav-item {{ $active === 'demo-timeline' ? 'is-active' : '' }}" href="{{ route('trail.demo.timeline') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Timeline
      </a>
    @endif
  </nav>

  <div class="px-3 py-3 border-t space-y-1" style="border-color: var(--trail-border);">
    <button class="trail-nav-item w-full" id="themeToggle" style="background:transparent">
      <svg class="dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
      <svg class="hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      <span class="dark:hidden">Tema claro</span><span class="hidden dark:inline">Tema escuro</span>
    </button>
    <div class="flex items-center gap-2.5 px-2 pt-2">
      <span class="trail-avatar">DV</span>
      <div class="leading-tight min-w-0"><div class="text-xs font-medium truncate">Dev · Admin</div><div class="ds-label truncate">acme.app</div></div>
    </div>
  </div>
</aside>
