@extends('trail::layout')

@section('title', 'Overview')

@push('head')
<style>
  .grid-line { stroke: var(--trail-border); stroke-width: 1; vector-effect: non-scaling-stroke; }
  .x-label { fill: var(--trail-text-faint); font-size: 11px; font-family: var(--trail-font-mono); }
</style>
@endpush

@section('main')
<!-- Header -->
<header class="flex items-center justify-between px-6 border-b shrink-0" style="height: var(--trail-header-h); border-color: var(--trail-border);">
  <div class="flex items-center gap-3">
    <h1 class="text-[18px] font-semibold tracking-tight">Overview</h1>
    <span class="trail-badge" style="background:var(--trail-success-subtle);color:var(--trail-success)"><span class="trail-dot trail-dot-live"></span>tracking ativo</span>
  </div>
  <div class="flex items-center gap-2.5">
    <div class="trail-search" style="width:240px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      <input class="trail-input" placeholder="Buscar eventos, atores…">
    </div>
    <div class="trail-segmented" id="periodSwitch">
      <button>Hoje</button><button class="is-active">7d</button><button>30d</button>
    </div>
    <button class="trail-btn trail-btn-secondary trail-btn-icon" title="Período custom">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    </button>
  </div>
</header>

<!-- Scroll content -->
<div class="flex-1 overflow-auto trail-scroll px-6 py-5">

  <!-- Metric cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4" id="metrics"></div>

  <!-- Main chart -->
  <div class="trail-card mb-4">
    <div class="trail-card-head">
      <div class="flex items-center gap-3">
        <h3 class="trail-card-title">Eventos ao longo do tempo</h3>
        <span class="inline-flex items-center gap-1.5 text-xs" style="color:var(--trail-text-subtle)"><span style="width:8px;height:8px;border-radius:2px;background:var(--trail-accent);display:inline-block"></span>todos os eventos</span>
      </div>
      <div class="trail-segmented" id="granSwitch">
        <button>Hora</button><button class="is-active">Dia</button><button>Semana</button>
      </div>
    </div>
    <div class="trail-card-pad">
      <div class="flex items-baseline gap-3 mb-4">
        <span class="text-[28px] font-bold tracking-tight trail-tnum leading-none" id="chartTotal">1.24M</span>
        <span class="trail-delta trail-delta-up"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>12.4%</span>
        <span class="ds-label">vs período anterior</span>
      </div>
      <svg id="chart" width="100%" height="240" viewBox="0 0 820 240" preserveAspectRatio="none" style="display:block;overflow:visible"></svg>
    </div>
  </div>

  <!-- Two columns -->
  <div class="grid lg:grid-cols-2 gap-4">
    <div class="trail-card">
      <div class="trail-card-head">
        <h3 class="trail-card-title">Top eventos</h3>
        <span class="ds-label">por contagem · 7d</span>
      </div>
      <div class="trail-card-pad space-y-3.5" id="topEvents"></div>
    </div>

    <div class="trail-card">
      <div class="trail-card-head">
        <h3 class="trail-card-title">Atores mais ativos</h3>
        <span class="ds-label">por eventos · 7d</span>
      </div>
      <div class="px-2 py-1.5" id="topActors"></div>
    </div>
  </div>
  <div class="h-4"></div>
</div>
@endsection

@push('scripts')
@verbatim
<script>
  // ---- Metric cards ----
  const spark = (pts, color) => {
    const w = 88, h = 32, max = Math.max(...pts), min = Math.min(...pts);
    const map = pts.map((v,i) => [i/(pts.length-1)*w, h - (v-min)/(max-min||1)*(h-4) - 2]);
    const line = map.map(p => p.join(',')).join(' ');
    const area = `${line} ${w},${h} 0,${h}`;
    return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" style="overflow:visible">
      <polygon points="${area}" fill="${color}" opacity="0.12"/>
      <polyline points="${line}" fill="none" stroke="${color}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>`;
  };
  const metrics = [
    { label: 'Total de eventos', value: '1.24M', delta: '+12.4%', dir: 'up', spark: [8,10,9,13,12,17,16,21,19,24], accent: true },
    { label: 'Atores únicos ativos', value: '8.412', delta: '+4.8%', dir: 'up', spark: [14,15,13,16,18,17,19,20,22,23] },
    { label: 'Evento mais frequente', value: 'order.placed', mono: true, sub: '48.2k disparos', spark: [20,18,22,19,24,21,25,23,27,26] },
    { label: 'Taxa de conversão', value: '6.7%', delta: '−1.2%', dir: 'down', spark: [12,13,11,12,10,11,9,10,8,9] },
  ];
  document.getElementById('metrics').innerHTML = metrics.map(m => {
    const color = m.accent ? 'var(--trail-accent)' : 'var(--trail-text-faint)';
    const deltaCls = m.dir === 'up' ? 'trail-delta-up' : m.dir === 'down' ? 'trail-delta-down' : 'trail-delta-flat';
    const arrow = m.dir === 'up'
      ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>'
      : m.dir === 'down'
      ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7 17 17M17 9v8H9"/></svg>' : '';
    const valCls = m.mono ? 'trail-mono text-[17px] font-semibold' : 'text-[26px] font-bold trail-tnum';
    return `<div class="trail-card trail-card-pad">
      <div class="trail-eyebrow mb-2">${m.label}</div>
      <div class="flex items-end justify-between gap-2">
        <div class="min-w-0">
          <div class="${valCls} tracking-tight leading-none truncate">${m.value}</div>
          <div class="mt-2 flex items-center gap-1.5">
            ${m.delta ? `<span class="trail-delta ${deltaCls}">${arrow}${m.delta}</span>` : ''}
            <span class="ds-label">${m.sub || 'vs 7d ant.'}</span>
          </div>
        </div>
        <div class="shrink-0">${spark(m.spark, color)}</div>
      </div>
    </div>`;
  }).join('');

  // ---- Main chart ----
  const datasets = {
    'Hora': { labels: ['00','03','06','09','12','15','18','21','23'], data: [320,180,140,520,880,760,1020,1240,640], total: '142k' },
    'Dia':  { labels: ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'], data: [148,162,139,205,243,128,176], total: '1.24M' },
    'Semana':{ labels: ['S-5','S-4','S-3','S-2','S-1','Atual'], data: [820,910,760,1080,1190,1320], total: '5.9M' },
  };
  function drawChart(key) {
    const ds = datasets[key];
    const W = 820, H = 240, padL = 8, padR = 8, padB = 26, padT = 8;
    const innerW = W - padL - padR, innerH = H - padT - padB;
    const max = Math.max(...ds.data) * 1.1, min = 0;
    const xs = ds.data.map((_,i) => padL + i/(ds.data.length-1)*innerW);
    const ys = ds.data.map(v => padT + innerH - (v-min)/(max-min)*innerH);
    const linePts = xs.map((x,i) => `${x.toFixed(1)},${ys[i].toFixed(1)}`).join(' ');
    const areaPts = `${linePts} ${xs[xs.length-1].toFixed(1)},${padT+innerH} ${padL},${padT+innerH}`;
    let grid = '';
    for (let g=0; g<=3; g++){ const y = padT + g/3*innerH; grid += `<line class="grid-line" x1="${padL}" y1="${y}" x2="${W-padR}" y2="${y}"/>`; }
    const labels = ds.labels.map((l,i) => `<text class="x-label" x="${xs[i]}" y="${H-8}" text-anchor="middle">${l}</text>`).join('');
    const dots = xs.map((x,i)=>`<circle cx="${x}" cy="${ys[i]}" r="2.5" fill="var(--trail-surface)" stroke="var(--trail-accent)" stroke-width="1.5"/>`).join('');
    document.getElementById('chart').innerHTML = `
      <defs><linearGradient id="ag" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="var(--trail-accent)" stop-opacity="0.22"/>
        <stop offset="100%" stop-color="var(--trail-accent)" stop-opacity="0"/>
      </linearGradient></defs>
      ${grid}
      <polygon points="${areaPts}" fill="url(#ag)"/>
      <polyline points="${linePts}" fill="none" stroke="var(--trail-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
      ${dots}
      ${labels}`;
    document.getElementById('chartTotal').textContent = ds.total;
  }
  drawChart('Dia');

  // ---- Top events ----
  const events = [
    { name: 'order.placed', count: '48.2k', pct: 100 },
    { name: 'onboarding.step_completed', count: '37.9k', pct: 79 },
    { name: 'user.signed_up', count: '31.7k', pct: 66 },
    { name: 'whatsapp.connected', count: '18.3k', pct: 38 },
    { name: 'cart.updated', count: '12.4k', pct: 26 },
    { name: 'invoice.paid', count: '6.1k', pct: 13 },
  ];
  document.getElementById('topEvents').innerHTML = events.map(e => `
    <div>
      <div class="flex items-center justify-between mb-1.5">
        <span class="trail-tag">${e.name}</span>
        <span class="text-xs trail-tnum" style="color:var(--trail-text-subtle)">${e.count}</span>
      </div>
      <div class="trail-bar-track"><div class="trail-bar-fill" style="width:${e.pct}%"></div></div>
    </div>`).join('');

  // ---- Top actors ----
  const actors = [
    { name: 'Marina Rocha', meta: 'User · ator_8821', count: '1.842', spark:[6,8,7,10,12,11,14] },
    { name: 'Acme Team', meta: 'Team · team_204', count: '1.530', spark:[10,9,12,11,13,12,15] },
    { name: 'João Silva', meta: 'User · ator_3390', count: '1.214', spark:[8,7,9,8,10,9,11] },
    { name: 'Beatriz Lima', meta: 'User · ator_7745', count: '982', spark:[5,6,5,7,6,8,7] },
    { name: 'Pedro Alves', meta: 'User · ator_1182', count: '774', spark:[4,5,4,6,5,5,6] },
  ];
  const initials = n => n.split(' ').map(w=>w[0]).slice(0,2).join('');
  document.getElementById('topActors').innerHTML = actors.map(a => `
    <div class="flex items-center gap-3 px-2 rounded-md" style="height:48px;transition:background .12s" onmouseover="this.style.background='var(--trail-surface-2)'" onmouseout="this.style.background='transparent'">
      <span class="trail-avatar">${initials(a.name)}</span>
      <div class="min-w-0 flex-1">
        <div class="text-[13px] font-medium truncate">${a.name}</div>
        <div class="ds-label truncate">${a.meta}</div>
      </div>
      <div class="shrink-0">${spark(a.spark, 'var(--trail-text-faint)')}</div>
      <div class="text-[13px] font-semibold trail-tnum shrink-0 w-12 text-right">${a.count}</div>
    </div>`).join('');

  // ---- Interactions ----
  document.querySelectorAll('.trail-segmented').forEach(group => {
    group.querySelectorAll('button').forEach(btn => btn.onclick = () => {
      group.querySelectorAll('button').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      if (group.id === 'granSwitch') drawChart(btn.textContent.trim());
    });
  });
</script>
@endverbatim
@endpush
