<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trail · Design System</title>
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
    --color-warning: var(--trail-warning);
    --color-info: var(--trail-info);
    --font-sans: var(--trail-font-sans);
    --font-mono: var(--trail-font-mono);
    --radius-md: var(--trail-radius-md);
    --radius-lg: var(--trail-radius-lg);
    --radius-xl: var(--trail-radius-xl);
  }
  html, body { height: 100%; }
</style>
@endverbatim
<style>
  .ds-section + .ds-section { margin-top: 56px; }
  .ds-label { font-size: 11px; color: var(--trail-text-faint); font-family: var(--trail-font-mono); }
  .swatch { width: 100%; height: 44px; border-radius: var(--trail-radius-md); border: 1px solid var(--trail-border); }
  .specimen-row { display: grid; grid-template-columns: 1fr auto; align-items: baseline; gap: 16px;
    padding: 12px 0; border-bottom: 1px solid var(--trail-border); }
  .specimen-row:last-child { border-bottom: 0; }
</style>
</head>
<body class="trail-root trail-scroll">

<!-- ===================== Header ===================== -->
<header class="sticky top-0 z-30 flex items-center justify-between h-14 px-6 border-b"
        style="background: color-mix(in srgb, var(--trail-bg) 80%, transparent); backdrop-filter: blur(8px); border-color: var(--trail-border);">
  <div class="flex items-center gap-2.5">
    <div class="grid place-items-center w-7 h-7 rounded-md" style="background: var(--trail-accent);">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--trail-accent-fg)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 18 L9 9 L14 14 L20 5"/><circle cx="20" cy="5" r="1.6" fill="var(--trail-accent-fg)" stroke="none"/>
      </svg>
    </div>
    <div class="leading-none">
      <div class="font-semibold text-[15px] tracking-tight">Trail</div>
    </div>
    <span class="trail-badge trail-badge-neutral ml-1">Design System</span>
  </div>
  <div class="flex items-center gap-3">
    <button class="trail-icon-btn" id="themeToggle" title="Alternar tema">
      <svg class="dark:hidden" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
      <svg class="hidden dark:block" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
    </button>
  </div>
</header>

<main class="max-w-[1040px] mx-auto px-6 py-12">

  <!-- Intro -->
  <div class="mb-14">
    <p class="trail-eyebrow mb-3">Biblioteca de tracking · painel /trail</p>
    <h1 class="text-[30px] font-bold tracking-tight mb-3" style="text-wrap: balance;">O sistema visual do Trail</h1>
    <p class="text-[15px] max-w-[640px]" style="color: var(--trail-text-muted);">
      Ferramenta de desenvolvedor: densa, sóbria, dark por padrão. Tudo é dirigido por uma
      camada de tokens <code class="trail-tag">--trail-*</code>. Sobrescreva qualquer variável
      no seu app e a interface inteira reage. Nada hardcoda um valor.
    </p>
  </div>

  <!-- ===================== Colors ===================== -->
  <section class="ds-section">
    <div class="flex items-baseline justify-between mb-5">
      <h2 class="text-[13px] font-semibold uppercase tracking-wider" style="color: var(--trail-text-subtle);">01 — Cores</h2>
      <span class="ds-label">tokens/colors.css</span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
      <div><div class="swatch" style="background: var(--trail-bg);"></div><div class="mt-2 text-xs font-medium">Background</div><div class="ds-label">--trail-bg</div></div>
      <div><div class="swatch" style="background: var(--trail-surface);"></div><div class="mt-2 text-xs font-medium">Surface</div><div class="ds-label">--trail-surface</div></div>
      <div><div class="swatch" style="background: var(--trail-surface-2);"></div><div class="mt-2 text-xs font-medium">Surface 2</div><div class="ds-label">--trail-surface-2</div></div>
      <div><div class="swatch" style="background: var(--trail-surface-3);"></div><div class="mt-2 text-xs font-medium">Surface 3</div><div class="ds-label">--trail-surface-3</div></div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
      <div><div class="swatch flex items-end p-2" style="background: var(--trail-surface);"><span style="color: var(--trail-text); font-size:13px;">Aa</span></div><div class="mt-2 text-xs font-medium">Text</div><div class="ds-label">--trail-text</div></div>
      <div><div class="swatch flex items-end p-2" style="background: var(--trail-surface);"><span style="color: var(--trail-text-muted); font-size:13px;">Aa</span></div><div class="mt-2 text-xs font-medium">Muted</div><div class="ds-label">--trail-text-muted</div></div>
      <div><div class="swatch flex items-end p-2" style="background: var(--trail-surface);"><span style="color: var(--trail-text-subtle); font-size:13px;">Aa</span></div><div class="mt-2 text-xs font-medium">Subtle</div><div class="ds-label">--trail-text-subtle</div></div>
      <div><div class="swatch flex items-end p-2" style="background: var(--trail-surface);"><span style="color: var(--trail-text-faint); font-size:13px;">Aa</span></div><div class="mt-2 text-xs font-medium">Faint</div><div class="ds-label">--trail-text-faint</div></div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div><div class="swatch" style="background: var(--trail-accent);"></div><div class="mt-2 text-xs font-medium">Accent</div><div class="ds-label">--trail-accent</div></div>
      <div><div class="swatch" style="background: var(--trail-success);"></div><div class="mt-2 text-xs font-medium">Success</div><div class="ds-label">--trail-success</div></div>
      <div><div class="swatch" style="background: var(--trail-danger);"></div><div class="mt-2 text-xs font-medium">Danger</div><div class="ds-label">--trail-danger</div></div>
      <div><div class="swatch" style="background: var(--trail-warning);"></div><div class="mt-2 text-xs font-medium">Warning</div><div class="ds-label">--trail-warning</div></div>
    </div>
  </section>

  <!-- ===================== Type ===================== -->
  <section class="ds-section">
    <div class="flex items-baseline justify-between mb-5">
      <h2 class="text-[13px] font-semibold uppercase tracking-wider" style="color: var(--trail-text-subtle);">02 — Tipografia</h2>
      <span class="ds-label">Inter · JetBrains Mono</span>
    </div>
    <div class="trail-card trail-card-pad">
      <div class="specimen-row"><div class="text-[36px] font-bold tracking-tight">1.24M eventos</div><span class="ds-label">3xl / 700 · métrica</span></div>
      <div class="specimen-row"><div class="text-[24px] font-semibold tracking-tight">Eventos ao longo do tempo</div><span class="ds-label">xl / 600 · heading</span></div>
      <div class="specimen-row"><div class="text-[18px] font-semibold">Atores mais ativos</div><span class="ds-label">lg / 600 · card title</span></div>
      <div class="specimen-row"><div class="text-[14px]" style="color: var(--trail-text-muted);">Corpo padrão da interface, legível em densidade alta.</div><span class="ds-label">base / 400 · body</span></div>
      <div class="specimen-row"><div class="trail-mono text-[13px]" style="color: var(--trail-text-muted);">user.signed_up · 0x9f2a · ator_8821</div><span class="ds-label">mono · valores/IDs</span></div>
      <div class="specimen-row"><div class="trail-eyebrow">Eyebrow uppercase</div><span class="ds-label">2xs / 600 · eyebrow</span></div>
    </div>
  </section>

  <!-- ===================== Foundations ===================== -->
  <section class="ds-section">
    <div class="flex items-baseline justify-between mb-5">
      <h2 class="text-[13px] font-semibold uppercase tracking-wider" style="color: var(--trail-text-subtle);">03 — Forma & elevação</h2>
      <span class="ds-label">tokens/foundations.css</span>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
      <div class="trail-card trail-card-pad">
        <div class="trail-eyebrow mb-3">Radius</div>
        <div class="flex items-end gap-3">
          <div class="text-center"><div style="width:44px;height:44px;background:var(--trail-surface-3);border:1px solid var(--trail-border);border-radius:var(--trail-radius-sm);"></div><div class="ds-label mt-1.5">6</div></div>
          <div class="text-center"><div style="width:44px;height:44px;background:var(--trail-surface-3);border:1px solid var(--trail-border);border-radius:var(--trail-radius-md);"></div><div class="ds-label mt-1.5">8</div></div>
          <div class="text-center"><div style="width:44px;height:44px;background:var(--trail-surface-3);border:1px solid var(--trail-border);border-radius:var(--trail-radius-lg);"></div><div class="ds-label mt-1.5">12</div></div>
          <div class="text-center"><div style="width:44px;height:44px;background:var(--trail-surface-3);border:1px solid var(--trail-border);border-radius:var(--trail-radius-xl);"></div><div class="ds-label mt-1.5">16</div></div>
        </div>
      </div>
      <div class="trail-card trail-card-pad">
        <div class="trail-eyebrow mb-3">Elevation</div>
        <div class="flex items-center gap-4">
          <div style="width:48px;height:48px;background:var(--trail-surface);border:1px solid var(--trail-border);border-radius:var(--trail-radius-md);box-shadow:var(--trail-shadow-sm);"></div>
          <div style="width:48px;height:48px;background:var(--trail-surface);border:1px solid var(--trail-border);border-radius:var(--trail-radius-md);box-shadow:var(--trail-shadow-md);"></div>
          <div style="width:48px;height:48px;background:var(--trail-surface);border:1px solid var(--trail-border);border-radius:var(--trail-radius-md);box-shadow:var(--trail-shadow-lg);"></div>
        </div>
      </div>
      <div class="trail-card trail-card-pad">
        <div class="trail-eyebrow mb-3">Spacing · base 4px</div>
        <div class="flex items-end gap-1.5">
          <div style="width:4px;height:8px;background:var(--trail-accent);"></div>
          <div style="width:8px;height:14px;background:var(--trail-accent);"></div>
          <div style="width:12px;height:20px;background:var(--trail-accent);"></div>
          <div style="width:16px;height:26px;background:var(--trail-accent);"></div>
          <div style="width:24px;height:34px;background:var(--trail-accent);"></div>
          <div style="width:32px;height:42px;background:var(--trail-accent);"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== Components ===================== -->
  <section class="ds-section">
    <div class="flex items-baseline justify-between mb-5">
      <h2 class="text-[13px] font-semibold uppercase tracking-wider" style="color: var(--trail-text-subtle);">04 — Componentes</h2>
      <span class="ds-label">components.css</span>
    </div>

    <div class="grid md:grid-cols-2 gap-4">

      <!-- Buttons -->
      <div class="trail-card">
        <div class="trail-card-head"><h3 class="trail-card-title">Botões</h3><span class="ds-label">.trail-btn</span></div>
        <div class="trail-card-pad space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <button class="trail-btn trail-btn-primary">Primary</button>
            <button class="trail-btn trail-btn-secondary">Secondary</button>
            <button class="trail-btn trail-btn-ghost">Ghost</button>
            <button class="trail-btn trail-btn-soft">Soft</button>
            <button class="trail-btn trail-btn-danger">Danger</button>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button class="trail-btn trail-btn-primary trail-btn-sm">Small</button>
            <button class="trail-btn trail-btn-secondary">Default</button>
            <button class="trail-btn trail-btn-primary trail-btn-lg">Large</button>
            <button class="trail-btn trail-btn-primary" disabled>Disabled</button>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button class="trail-btn trail-btn-primary">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14"/></svg>
              Novo funil
            </button>
            <button class="trail-icon-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.2-8.5"/><path d="M21 3v6h-6"/></svg></button>
            <button class="trail-icon-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
          </div>
        </div>
      </div>

      <!-- Inputs -->
      <div class="trail-card">
        <div class="trail-card-head"><h3 class="trail-card-title">Inputs & seleção</h3><span class="ds-label">.trail-input</span></div>
        <div class="trail-card-pad space-y-3">
          <div class="trail-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input class="trail-input" placeholder="Buscar eventos, atores…">
          </div>
          <div class="grid grid-cols-2 gap-3">
            <input class="trail-input" placeholder="Valor">
            <select class="trail-select"><option>Últimos 7 dias</option><option>Hoje</option><option>30 dias</option></select>
          </div>
          <div class="flex flex-wrap gap-2">
            <span class="trail-tag">user.signed_up</span>
            <span class="trail-tag">order.placed</span>
            <span class="trail-tag">whatsapp.connected</span>
          </div>
        </div>
      </div>

      <!-- Badges + avatars -->
      <div class="trail-card">
        <div class="trail-card-head"><h3 class="trail-card-title">Badges & atores</h3><span class="ds-label">.trail-badge · .trail-avatar</span></div>
        <div class="trail-card-pad space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="trail-badge trail-badge-neutral">neutral</span>
            <span class="trail-badge trail-badge-accent">accent</span>
            <span class="trail-badge trail-badge-success">success</span>
            <span class="trail-badge trail-badge-danger">danger</span>
            <span class="trail-badge trail-badge-warning">warning</span>
            <span class="trail-badge trail-badge-info">info</span>
            <span class="trail-badge trail-badge-outline">outline</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="trail-avatar trail-avatar-sm">JS</span>
            <span class="trail-avatar">MR</span>
            <span class="trail-avatar-lg trail-avatar">AL</span>
            <span class="trail-badge" style="background:var(--trail-success-subtle);color:var(--trail-success)"><span class="trail-dot trail-dot-live"></span>ao vivo</span>
          </div>
          <div class="flex items-center gap-4">
            <span class="trail-delta trail-delta-up"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>12.4%</span>
            <span class="trail-delta trail-delta-down"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7 17 17M17 9v8H9"/></svg>3.1%</span>
            <span class="trail-delta trail-delta-flat">—</span>
          </div>
        </div>
      </div>

      <!-- Tabs / segmented -->
      <div class="trail-card">
        <div class="trail-card-head"><h3 class="trail-card-title">Navegação & tabs</h3><span class="ds-label">.trail-segmented · .trail-tabs</span></div>
        <div class="trail-card-pad space-y-4">
          <div class="trail-segmented">
            <button class="is-active">Hora</button><button>Dia</button><button>Semana</button>
          </div>
          <div class="trail-tabs">
            <button aria-selected="true">Propriedades</button>
            <button>Contexto</button>
            <button>Raw JSON</button>
          </div>
          <div class="space-y-1 max-w-[200px]">
            <a class="trail-nav-item is-active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Overview</a>
            <a class="trail-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>Events</a>
          </div>
        </div>
      </div>

      <!-- Metric card -->
      <div class="trail-card">
        <div class="trail-card-head"><h3 class="trail-card-title">Card de métrica</h3><span class="ds-label">com sparkline + delta</span></div>
        <div class="trail-card-pad">
          <div class="flex items-start justify-between">
            <div>
              <div class="trail-eyebrow mb-1.5">Total de eventos · 7d</div>
              <div class="text-[30px] font-bold tracking-tight trail-tnum leading-none">1.24M</div>
              <div class="mt-2"><span class="trail-delta trail-delta-up"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>12.4%</span> <span class="ds-label">vs 7d ant.</span></div>
            </div>
            <svg width="96" height="44" viewBox="0 0 96 44" preserveAspectRatio="none" fill="none">
              <polyline points="0,36 12,30 24,32 36,22 48,26 60,14 72,18 84,8 96,10" stroke="var(--trail-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <polygon points="0,36 12,30 24,32 36,22 48,26 60,14 72,18 84,8 96,10 96,44 0,44" fill="var(--trail-accent-subtle)"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- Empty / skeleton -->
      <div class="trail-card">
        <div class="trail-card-head"><h3 class="trail-card-title">Estados</h3><span class="ds-label">empty · skeleton</span></div>
        <div class="trail-card-pad grid grid-cols-2 gap-4">
          <div class="trail-empty" style="padding: 20px 8px;">
            <div class="trail-empty-glyph"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-6"/></svg></div>
            <div class="trail-empty-title" style="font-size:13px;">Sem dados ainda</div>
            <div class="trail-empty-body" style="font-size:12px;">Eventos aparecem assim que chegarem.</div>
          </div>
          <div class="space-y-2.5 self-center">
            <div class="trail-skeleton" style="height:12px;width:70%"></div>
            <div class="trail-skeleton" style="height:12px;width:90%"></div>
            <div class="trail-skeleton" style="height:12px;width:55%"></div>
            <div class="trail-skeleton" style="height:32px;width:100%;margin-top:10px"></div>
          </div>
        </div>
      </div>

      <!-- Proportion bars -->
      <div class="trail-card">
        <div class="trail-card-head"><h3 class="trail-card-title">Top eventos</h3><span class="ds-label">.trail-bar</span></div>
        <div class="trail-card-pad space-y-3">
          <div><div class="flex justify-between text-xs mb-1.5"><span class="trail-mono" style="color:var(--trail-text-muted)">order.placed</span><span class="trail-tnum" style="color:var(--trail-text-subtle)">48.2k</span></div><div class="trail-bar-track"><div class="trail-bar-fill" style="width:100%"></div></div></div>
          <div><div class="flex justify-between text-xs mb-1.5"><span class="trail-mono" style="color:var(--trail-text-muted)">user.signed_up</span><span class="trail-tnum" style="color:var(--trail-text-subtle)">31.7k</span></div><div class="trail-bar-track"><div class="trail-bar-fill" style="width:66%"></div></div></div>
          <div><div class="flex justify-between text-xs mb-1.5"><span class="trail-mono" style="color:var(--trail-text-muted)">whatsapp.connected</span><span class="trail-tnum" style="color:var(--trail-text-subtle)">12.4k</span></div><div class="trail-bar-track"><div class="trail-bar-fill" style="width:26%"></div></div></div>
        </div>
      </div>

      <!-- JSON code -->
      <div class="trail-card">
        <div class="trail-card-head"><h3 class="trail-card-title">Payload JSON</h3><span class="ds-label">.trail-code</span></div>
        <div class="trail-card-pad">
<pre class="trail-code"><span class="p">{</span>
  <span class="k">"event"</span><span class="p">:</span> <span class="s">"order.placed"</span><span class="p">,</span>
  <span class="k">"value"</span><span class="p">:</span> <span class="n">149.9</span><span class="p">,</span>
  <span class="k">"properties"</span><span class="p">:</span> <span class="p">{</span>
    <span class="k">"plan"</span><span class="p">:</span> <span class="s">"pro"</span><span class="p">,</span>
    <span class="k">"first_order"</span><span class="p">:</span> <span class="b">true</span>
  <span class="p">}</span>
<span class="p">}</span></pre>
        </div>
      </div>

    </div>

    <!-- Drawer + tooltip trigger row -->
    <div class="flex items-center gap-3 mt-4">
      <button class="trail-btn trail-btn-secondary" id="openDrawer">Abrir drawer →</button>
      <div style="position:relative" class="group">
        <button class="trail-btn trail-btn-ghost">Hover p/ tooltip</button>
        <div class="trail-tooltip" style="position:absolute;bottom:calc(100% + 8px);left:0;opacity:0;pointer-events:none;transition:opacity .15s;white-space:nowrap" id="tt">2.413 atores únicos</div>
      </div>
    </div>
  </section>

  <!-- ===================== Extensibility ===================== -->
  <section class="ds-section">
    <div class="flex items-baseline justify-between mb-5">
      <h2 class="text-[13px] font-semibold uppercase tracking-wider" style="color: var(--trail-text-subtle);">05 — Extensibilidade</h2>
      <span class="ds-label">para quem usa a lib</span>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
      <div class="trail-card trail-card-pad">
        <div class="trail-eyebrow mb-2">Override de tokens</div>
        <p class="text-[13px] mb-3" style="color:var(--trail-text-muted)">No seu app, depois de importar o Trail, sobrescreva qualquer variável:</p>
<pre class="trail-code"><span class="p">/* resources/css/app.css */</span>
<span class="k">@import</span> <span class="s">"tailwindcss"</span><span class="p">;</span>
<span class="k">@import</span> <span class="s">"trail/styles.css"</span><span class="p">;</span>

<span class="p">:root {</span>
  <span class="k">--trail-accent</span><span class="p">:</span> <span class="s">#16a34a</span><span class="p">;</span>
  <span class="k">--trail-radius-lg</span><span class="p">:</span> <span class="n">6px</span><span class="p">;</span>
  <span class="k">--trail-font-sans</span><span class="p">:</span> <span class="s">"Geist"</span><span class="p">;</span>
<span class="p">}</span></pre>
      </div>
      <div class="trail-card trail-card-pad">
        <div class="trail-eyebrow mb-2">Utilitários Tailwind v4</div>
        <p class="text-[13px] mb-3" style="color:var(--trail-text-muted)">O <code class="trail-tag">@theme</code> mapeia os tokens, então os utilitários seguem qualquer override automaticamente:</p>
<pre class="trail-code"><span class="p">&lt;</span><span class="k">div</span> <span class="n">class</span>=<span class="s">"bg-surface border border-border"</span><span class="p">&gt;</span>
  <span class="p">&lt;</span><span class="k">h3</span> <span class="n">class</span>=<span class="s">"text-content font-semibold"</span><span class="p">&gt;</span>…
  <span class="p">&lt;</span><span class="k">span</span> <span class="n">class</span>=<span class="s">"text-muted font-mono"</span><span class="p">&gt;</span>…
  <span class="p">&lt;</span><span class="k">a</span> <span class="n">class</span>=<span class="s">"text-accent rounded-lg"</span><span class="p">&gt;</span>…</pre>
        <p class="text-[13px] mt-3" style="color:var(--trail-text-muted)">Tokens disponíveis: <code class="trail-tag">bg-surface</code> <code class="trail-tag">text-muted</code> <code class="trail-tag">text-accent</code> <code class="trail-tag">border-border</code>.</p>
      </div>
    </div>
  </section>

  <footer class="mt-16 pt-6 text-xs flex items-center justify-between" style="border-top:1px solid var(--trail-border); color: var(--trail-text-faint);">
    <span>Trail · Design System</span>
    <span class="trail-mono">styles.css → tokens/* + components.css</span>
  </footer>
</main>

<!-- Drawer demo -->
<div class="trail-drawer-overlay" id="drawerOverlay"></div>
<aside class="trail-drawer" id="drawer">
  <div class="trail-card-head" style="border-radius:0">
    <div class="flex items-center gap-2"><span class="trail-tag">order.placed</span><span class="trail-badge trail-badge-success">value 149.90</span></div>
    <button class="trail-icon-btn" id="closeDrawer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
  </div>
  <div class="p-5 trail-scroll" style="overflow:auto">
    <div class="flex items-center gap-3 mb-5">
      <span class="trail-avatar trail-avatar-lg">MR</span>
      <div><div class="font-semibold text-sm">Marina Rocha</div><div class="ds-label">User · ator_8821 · há 2 min</div></div>
    </div>
    <div class="trail-tabs mb-4"><button aria-selected="true">Propriedades</button><button>Contexto</button></div>
<pre class="trail-code"><span class="p">{</span>
  <span class="k">"plan"</span><span class="p">:</span> <span class="s">"pro"</span><span class="p">,</span>
  <span class="k">"amount"</span><span class="p">:</span> <span class="n">149.9</span><span class="p">,</span>
  <span class="k">"currency"</span><span class="p">:</span> <span class="s">"BRL"</span><span class="p">,</span>
  <span class="k">"items"</span><span class="p">:</span> <span class="n">3</span><span class="p">,</span>
  <span class="k">"first_order"</span><span class="p">:</span> <span class="b">true</span><span class="p">,</span>
  <span class="k">"coupon"</span><span class="p">:</span> <span class="b">null</span>
<span class="p">}</span></pre>
  </div>
</aside>

@verbatim
<script>
  const root = document.documentElement;
  // theme
  const savedTheme = localStorage.getItem('trail-theme-mode');
  if (savedTheme === 'light') root.classList.remove('dark');
  document.getElementById('themeToggle').onclick = () => {
    root.classList.toggle('dark');
    localStorage.setItem('trail-theme-mode', root.classList.contains('dark') ? 'dark' : 'light');
  };
  // drawer
  const dr = document.getElementById('drawer'), ov = document.getElementById('drawerOverlay');
  dr.style.transform = 'translateX(110%)'; ov.style.opacity = '0'; ov.style.pointerEvents = 'none';
  const open = () => {
    ov.style.pointerEvents = 'auto'; ov.style.opacity = '1';
    dr.style.transition = 'none'; dr.style.transform = 'translateX(110%)'; void dr.offsetWidth;
    dr.style.transition = ''; dr.style.transform = 'translateX(0)';
  };
  const close = () => { dr.style.transform = 'translateX(110%)'; ov.style.opacity = '0'; ov.style.pointerEvents = 'none'; };
  document.getElementById('openDrawer').onclick = open;
  document.getElementById('closeDrawer').onclick = close;
  ov.onclick = close;
  // tooltip
  const ttWrap = document.getElementById('tt').parentElement, tt = document.getElementById('tt');
  ttWrap.addEventListener('mouseenter', () => tt.style.opacity = '1');
  ttWrap.addEventListener('mouseleave', () => tt.style.opacity = '0');
  // tabs/segmented interactive (visual only)
  document.querySelectorAll('.trail-segmented, .trail-tabs').forEach(group => {
    group.querySelectorAll('button').forEach(btn => btn.onclick = () => {
      group.querySelectorAll('button').forEach(b => { b.classList.remove('is-active'); b.removeAttribute('aria-selected'); });
      if (group.classList.contains('trail-segmented')) btn.classList.add('is-active');
      else btn.setAttribute('aria-selected', 'true');
    });
  });
</script>
@endverbatim
</body>
</html>
