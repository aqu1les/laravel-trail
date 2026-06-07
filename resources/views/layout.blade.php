<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trail · {{ $title ?? 'Dashboard' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
@if ($trailStylesheet = config('trail.stylesheet'))
<link rel="stylesheet" href="{{ $trailStylesheet }}">
@else
{!! \Trail\Trail\Facades\Trail::styles() !!}
@endif
<style>
  .ds-label { font-size: 11px; color: var(--trail-text-faint); font-family: var(--trail-font-mono); }
  .cat-dot { width: 7px; height: 7px; border-radius: 2px; flex-shrink: 0; }
  .prop-chip { font-family: var(--trail-font-mono); font-size: 11px; color: var(--trail-text-subtle);
    background: var(--trail-surface-3); padding: 1px 6px; border-radius: 4px; white-space: nowrap; }
  [x-cloak] { display: none !important; }
</style>
@stack('head')
@livewireStyles
</head>
<body class="trail-root" style="height:100vh; overflow:hidden;">
<div class="flex h-full">
  @include('trail::partials.sidebar', ['active' => $active ?? ''])
  {{ $slot }}
</div>

<script>
  // Shared theme toggle (the button lives in the sidebar partial).
  (function () {
    const root = document.documentElement;
    if (localStorage.getItem('trail-theme-mode') === 'light') root.classList.remove('dark');
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('#themeToggle');
      if (!btn) return;
      root.classList.toggle('dark');
      localStorage.setItem('trail-theme-mode', root.classList.contains('dark') ? 'dark' : 'light');
    });
  })();
</script>
@stack('scripts')
@livewireScripts
</body>
</html>
