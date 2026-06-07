<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trail · {{ $title ?? 'Dashboard' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="{{ route('trail.styles') }}">
<script src="https://unpkg.com/@tailwindcss/browser@4"></script>
@verbatim
<style type="text/tailwindcss">
  @import "tailwindcss";
  @custom-variant dark (&:where(.dark, .dark *));
  @theme {
    --color-bg: var(--trail-bg);
    --color-surface: var(--trail-surface);
    --color-surface-2: var(--trail-surface-2);
    --color-surface-3: var(--trail-surface-3);
    --color-border: var(--trail-border);
    --color-border-strong: var(--trail-border-strong);
    --color-content: var(--trail-text);
    --color-muted: var(--trail-text-muted);
    --color-subtle: var(--trail-text-subtle);
    --color-faint: var(--trail-text-faint);
    --color-accent: var(--trail-accent);
    --color-accent-fg: var(--trail-accent-fg);
    --color-accent-subtle: var(--trail-accent-subtle);
    --color-success: var(--trail-success);
    --color-danger: var(--trail-danger);
    --font-sans: var(--trail-font-sans);
    --font-mono: var(--trail-font-mono);
    --radius-md: var(--trail-radius-md);
    --radius-lg: var(--trail-radius-lg);
  }
  html, body { height: 100%; margin: 0; }
</style>
@endverbatim
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
