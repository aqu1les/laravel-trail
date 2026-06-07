@extends('trail::layout')

@section('title', 'Events')

@section('main')
<header class="flex items-center justify-between px-6 border-b shrink-0" style="height: var(--trail-header-h); border-color: var(--trail-border);">
  <div class="flex items-center gap-3">
    <h1 class="text-[18px] font-semibold tracking-tight">Events</h1>
    <span class="ds-label" id="countLabel">—</span>
  </div>
  <div class="flex items-center gap-2.5">
    <button class="trail-btn trail-btn-secondary" id="liveBtn">
      <span class="trail-dot trail-dot-live" id="liveDot"></span><span id="liveLabel">Ao vivo</span>
    </button>
    <div class="trail-segmented" id="periodSwitch">
      <button>Hoje</button><button class="is-active">7d</button><button>30d</button>
    </div>
  </div>
</header>

<!-- Filter bar -->
<div class="flex items-center gap-2.5 px-6 py-3 border-b shrink-0" style="border-color: var(--trail-border);">
  <div class="trail-search" style="width:280px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input class="trail-input" id="search" placeholder="Buscar evento, ator, propriedade…">
  </div>
  <div style="position:relative">
    <button class="trail-btn trail-btn-secondary" id="evtFilterBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18M6 12h12M10 20h4"/></svg>
      Evento<span class="trail-badge trail-badge-accent" id="evtFilterCount" style="display:none"></span>
    </button>
    <div class="trail-menu trail-scroll" id="evtMenu" style="position:absolute;top:calc(100% + 6px);left:0;z-index:20;display:none;max-height:300px;overflow:auto"></div>
  </div>
  <div style="position:relative">
    <button class="trail-btn trail-btn-secondary" id="actorFilterBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span id="actorFilterLabel">Todos os atores</span>
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="trail-menu trail-scroll" id="actorMenu" style="position:absolute;top:calc(100% + 6px);left:0;z-index:20;display:none;max-height:300px;overflow:auto"></div>
  </div>
  <div class="flex-1"></div>
  <button class="trail-btn trail-btn-ghost trail-btn-sm" id="clearBtn" style="display:none">Limpar filtros</button>
</div>

<!-- Table -->
<div class="flex-1 overflow-auto trail-scroll" id="tableScroll">
  <table class="trail-table trail-table-rows">
    <thead>
      <tr>
        <th style="width:34%">Evento</th>
        <th style="width:22%">Ator</th>
        <th>Propriedades</th>
        <th style="width:110px;text-align:right">Valor</th>
        <th style="width:96px;text-align:right">Quando</th>
      </tr>
    </thead>
    <tbody id="rows"></tbody>
  </table>
  <div id="emptyState" style="display:none"></div>
  <div class="flex items-center justify-center py-4 text-xs" style="color:var(--trail-text-faint)" id="loadMore">
    <span class="trail-skeleton" style="width:120px;height:10px"></span>
  </div>
</div>
@endsection

@section('overlay')
<div class="trail-drawer-overlay" id="drawerOverlay"></div>
<aside class="trail-drawer trail-scroll" id="drawer" style="overflow:auto"></aside>
@endsection

@push('scripts')
<script>window.TRAIL_URLS = { timeline: "{{ route('trail.timeline') }}" };</script>
@verbatim
<script>
  // ---------- Data model ----------
  const CATS = {
    auth:        { color: 'var(--trail-chart-3)', label: 'Auth' },
    commerce:    { color: 'var(--trail-chart-1)', label: 'Commerce' },
    onboarding:  { color: 'var(--trail-chart-2)', label: 'Onboarding' },
    integration: { color: 'var(--trail-chart-4)', label: 'Integration' },
    system:      { color: 'var(--trail-chart-5)', label: 'System' },
  };
  const TEMPLATES = [
    { name: 'order.placed', cat: 'commerce', value: () => (Math.random()*400+20).toFixed(2),
      props: () => ({ amount: +(Math.random()*400+20).toFixed(2), currency: 'BRL', items: rint(1,5), first_order: Math.random()<.3 }) },
    { name: 'user.signed_up', cat: 'auth', props: () => ({ plan: pick(['free','pro','team']), method: pick(['email','google','github']), referrer: pick(['organic','ads','invite']) }) },
    { name: 'onboarding.step_completed', cat: 'onboarding', props: () => ({ step: pick(['profile','workspace','invite','first_event']), index: rint(1,4), duration_ms: rint(800,9000) }) },
    { name: 'whatsapp.connected', cat: 'integration', props: () => ({ provider: 'meta_cloud', phone_masked: '+55 11 9••••-'+rint(1000,9999), verified: true }) },
    { name: 'cart.updated', cat: 'commerce', props: () => ({ items: rint(1,8), total: +(Math.random()*600+10).toFixed(2), currency: 'BRL' }) },
    { name: 'invoice.paid', cat: 'commerce', value: () => (Math.random()*900+49).toFixed(2),
      props: () => ({ amount: +(Math.random()*900+49).toFixed(2), plan: pick(['pro','team']), period: 'monthly' }) },
    { name: 'user.logged_in', cat: 'auth', props: () => ({ method: pick(['email','google','sso']), ip_masked: '187.•••.•••.'+rint(2,254) }) },
    { name: 'session.started', cat: 'system', props: () => ({ device: pick(['desktop','mobile','tablet']), os: pick(['macOS','Windows','iOS','Android']), app_version: '2.'+rint(1,9)+'.'+rint(0,9) }) },
  ];
  const ACTORS = [
    { name: 'Marina Rocha', type: 'User', id: 'ator_8821' },
    { name: 'João Silva', type: 'User', id: 'ator_3390' },
    { name: 'Beatriz Lima', type: 'User', id: 'ator_7745' },
    { name: 'Pedro Alves', type: 'User', id: 'ator_1182' },
    { name: 'Carla Dias', type: 'User', id: 'ator_5567' },
    { name: 'Acme Team', type: 'Team', id: 'team_204' },
    { name: 'Rafael Souza', type: 'User', id: 'ator_9043' },
    { name: 'Studio Norte', type: 'Team', id: 'team_511' },
  ];
  function rint(a,b){ return Math.floor(Math.random()*(b-a+1))+a; }
  function pick(a){ return a[Math.floor(Math.random()*a.length)]; }
  const initials = n => n.split(' ').map(w=>w[0]).slice(0,2).join('');

  let SEQ = 1;
  function makeEvent(ts) {
    const t = pick(TEMPLATES), a = pick(ACTORS);
    return { id: SEQ++, name: t.name, cat: t.cat, actor: a,
      value: t.value ? +t.value() : null, props: t.props(),
      context: { ip: '187.'+rint(0,255)+'.'+rint(0,255)+'.'+rint(0,255), user_agent: pick(['Chrome/124','Safari/17','Firefox/126']), session_id: 'sess_'+rint(10000,99999), source: pick(['web','mobile_sdk','api']) },
      ts };
  }
  let EVENTS = [];
  let now = Date.now();
  for (let i=0;i<50;i++){ EVENTS.push(makeEvent(now - i*rint(20000, 600000))); }
  EVENTS.sort((a,b)=>b.ts-a.ts);

  const state = { search: '', events: new Set(), actor: null };

  function rel(ts){
    const s = Math.max(1, Math.floor((Date.now()-ts)/1000));
    if (s < 5) return 'agora';
    if (s < 60) return 'há '+s+'s';
    const m = Math.floor(s/60); if (m < 60) return 'há '+m+' min';
    const h = Math.floor(m/60); if (h < 24) return 'há '+h+' h';
    const d = Math.floor(h/24); return 'há '+d+'d';
  }

  function visible(){
    return EVENTS.filter(e => {
      if (state.events.size && !state.events.has(e.name)) return false;
      if (state.actor && e.actor.id !== state.actor) return false;
      if (state.search){
        const q = state.search.toLowerCase();
        const hay = (e.name+' '+e.actor.name+' '+e.actor.id+' '+JSON.stringify(e.props)).toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });
  }
  function propChips(e){
    const entries = Object.entries(e.props).slice(0,3);
    return entries.map(([k,v]) => `<span class="prop-chip">${k}=${typeof v==='string'?v:String(v)}</span>`).join('');
  }
  function render(newIds){
    const list = visible();
    const tbody = document.getElementById('rows');
    document.getElementById('countLabel').textContent = list.length.toLocaleString('pt-BR') + ' eventos';
    document.getElementById('emptyState').style.display = list.length ? 'none' : 'block';
    document.getElementById('loadMore').style.display = list.length ? 'flex' : 'none';
    tbody.innerHTML = list.map(e => {
      const c = CATS[e.cat];
      const isNew = newIds && newIds.has(e.id);
      return `<tr data-id="${e.id}" class="${isNew?'trail-row-new':''}">
        <td><div class="flex items-center gap-2.5">
          <span class="cat-dot" style="background:${c.color}"></span>
          <span class="trail-mono" style="font-size:13px">${e.name}</span>
        </div></td>
        <td><div class="flex items-center gap-2">
          <span class="trail-avatar trail-avatar-sm">${initials(e.actor.name)}</span>
          <div class="min-w-0"><div class="text-[13px] truncate">${e.actor.name}</div></div>
        </div></td>
        <td><div class="flex items-center gap-1.5 flex-wrap" style="max-height:22px;overflow:hidden">${propChips(e)}</div></td>
        <td style="text-align:right" class="trail-tnum">${e.value!=null?`<span class="trail-mono" style="color:var(--trail-success)">${e.value.toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>`:'<span style="color:var(--trail-text-faint)">—</span>'}</td>
        <td style="text-align:right;white-space:nowrap" class="ds-label" data-ts="${e.ts}">${rel(e.ts)}</td>
      </tr>`;
    }).join('');
    if (list.length === 0){
      document.getElementById('emptyState').innerHTML = `<div class="trail-empty">
        <div class="trail-empty-glyph"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></div>
        <div class="trail-empty-title">Nenhum evento corresponde</div>
        <div class="trail-empty-body">Ajuste a busca ou os filtros para ver eventos.</div>
        <button class="trail-btn trail-btn-secondary trail-btn-sm" style="margin-top:8px" onclick="resetFilters()">Limpar filtros</button></div>`;
    }
    document.querySelectorAll('#rows tr').forEach(tr => tr.onclick = () => openDrawer(+tr.dataset.id));
    document.getElementById('clearBtn').style.display = (state.search||state.events.size||state.actor)?'inline-flex':'none';
  }
  window.resetFilters = function(){
    state.search=''; state.events.clear(); state.actor=null;
    document.getElementById('search').value='';
    document.getElementById('actorFilterLabel').textContent='Todos os atores';
    updateEvtCount(); render();
  };

  function hl(obj){
    let s = JSON.stringify(obj, null, 2).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    return s.replace(/("(?:\\.|[^"\\])*"(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?)/g, m=>{
      let cls='n';
      if (/^"/.test(m)) cls = /:$/.test(m) ? 'k' : 's';
      else if (/true|false|null/.test(m)) cls='b';
      return `<span class="${cls}">${m}</span>`;
    });
  }

  const dr = document.getElementById('drawer'), ov = document.getElementById('drawerOverlay');
  dr.style.transform = 'translateX(110%)'; ov.style.opacity = '0'; ov.style.pointerEvents = 'none';
  function openDrawer(id){
    const e = EVENTS.find(x=>x.id===id); if(!e) return;
    const c = CATS[e.cat];
    dr.innerHTML = `
      <div class="trail-card-head" style="border-radius:0;position:sticky;top:0;background:var(--trail-surface);z-index:2">
        <div class="flex items-center gap-2 min-w-0">
          <span class="cat-dot" style="background:${c.color}"></span>
          <span class="trail-tag" style="font-size:13px">${e.name}</span>
          ${e.value!=null?`<span class="trail-badge trail-badge-success">value ${e.value.toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>`:''}
        </div>
        <button class="trail-icon-btn" onclick="closeDrawer()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>
      <div class="p-5">
        <div class="flex items-center gap-3 mb-4">
          <span class="trail-avatar trail-avatar-lg">${initials(e.actor.name)}</span>
          <div class="min-w-0">
            <div class="font-semibold text-sm flex items-center gap-2">${e.actor.name}<span class="trail-badge trail-badge-outline">${e.actor.type}</span></div>
            <div class="ds-label">${e.actor.id} · ${rel(e.ts)} · ${new Date(e.ts).toLocaleString('pt-BR')}</div>
          </div>
          <a href="${window.TRAIL_URLS.timeline}" class="trail-btn trail-btn-ghost trail-btn-sm ml-auto shrink-0">Ver timeline →</a>
        </div>
        <div class="trail-tabs mb-3" id="drawerTabs">
          <button aria-selected="true" data-tab="props">Propriedades</button>
          <button data-tab="context">Contexto</button>
          <button data-tab="raw">Raw JSON</button>
        </div>
        <pre class="trail-code" id="drawerCode">${hl(e.props)}</pre>
      </div>`;
    const tabs = dr.querySelector('#drawerTabs'), code = dr.querySelector('#drawerCode');
    tabs.querySelectorAll('button').forEach(b => b.onclick = () => {
      tabs.querySelectorAll('button').forEach(x=>x.removeAttribute('aria-selected'));
      b.setAttribute('aria-selected','true');
      const map = { props: e.props, context: e.context, raw: { event: e.name, value: e.value, properties: e.props, context: e.context } };
      code.innerHTML = hl(map[b.dataset.tab]);
    });
    ov.style.pointerEvents = 'auto'; ov.style.opacity = '1';
    dr.style.transition = 'none';
    dr.style.transform = 'translateX(110%)';
    void dr.offsetWidth;
    dr.style.transition = '';
    dr.style.transform = 'translateX(0)';
  }
  window.closeDrawer = () => { dr.style.transform = 'translateX(110%)'; ov.style.opacity = '0'; ov.style.pointerEvents = 'none'; };
  ov.onclick = window.closeDrawer;
  document.addEventListener('keydown', e => { if(e.key==='Escape') window.closeDrawer(); });

  const evtMenu = document.getElementById('evtMenu');
  const allNames = [...new Set(EVENTS.map(e=>e.name))].sort();
  function buildEvtMenu(){
    evtMenu.innerHTML = allNames.map(n => `<div class="trail-menu-item" data-name="${n}" aria-checked="${state.events.has(n)}">
      <span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>
      <span class="trail-mono" style="font-size:12px">${n}</span></div>`).join('');
    evtMenu.querySelectorAll('.trail-menu-item').forEach(it => it.onclick = (ev) => {
      ev.stopPropagation();
      const n = it.dataset.name;
      if (state.events.has(n)) state.events.delete(n); else state.events.add(n);
      it.setAttribute('aria-checked', state.events.has(n));
      updateEvtCount(); render();
    });
  }
  function updateEvtCount(){
    const c = document.getElementById('evtFilterCount');
    if (state.events.size){ c.style.display='inline-flex'; c.textContent=state.events.size; }
    else c.style.display='none';
  }
  document.getElementById('evtFilterBtn').onclick = (e) => { e.stopPropagation(); buildEvtMenu(); toggleMenu(evtMenu); };

  const actorMenu = document.getElementById('actorMenu');
  function buildActorMenu(){
    actorMenu.innerHTML = `<div class="trail-menu-item" data-id="" aria-checked="${!state.actor}"><span class="trail-menu-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 6"/></svg></span>Todos os atores</div>`
      + ACTORS.map(a => `<div class="trail-menu-item" data-id="${a.id}" aria-checked="${state.actor===a.id}">
        <span class="trail-avatar trail-avatar-sm">${initials(a.name)}</span>
        <div style="min-width:0"><div style="font-size:13px;color:var(--trail-text)">${a.name}</div><div class="ds-label">${a.type} · ${a.id}</div></div></div>`).join('');
    actorMenu.querySelectorAll('.trail-menu-item').forEach(it => it.onclick = (ev) => {
      ev.stopPropagation();
      state.actor = it.dataset.id || null;
      document.getElementById('actorFilterLabel').textContent = state.actor ? ACTORS.find(a=>a.id===state.actor).name : 'Todos os atores';
      closeMenus(); render();
    });
  }
  document.getElementById('actorFilterBtn').onclick = (e) => { e.stopPropagation(); buildActorMenu(); toggleMenu(actorMenu); };

  function toggleMenu(m){ const open = m.style.display==='block'; closeMenus(); m.style.display = open?'none':'block'; }
  function closeMenus(){ evtMenu.style.display='none'; actorMenu.style.display='none'; }
  document.addEventListener('click', closeMenus);

  document.getElementById('search').addEventListener('input', e => { state.search = e.target.value; render(); });
  document.getElementById('clearBtn').onclick = resetFilters;
  document.getElementById('periodSwitch').querySelectorAll('button').forEach(b => b.onclick = () => {
    document.getElementById('periodSwitch').querySelectorAll('button').forEach(x=>x.classList.remove('is-active')); b.classList.add('is-active');
  });

  let live = true, timer = null;
  function setLive(on){
    live = on;
    document.getElementById('liveDot').style.animationPlayState = on?'running':'paused';
    document.getElementById('liveDot').style.background = on?'var(--trail-success)':'var(--trail-text-faint)';
    document.getElementById('liveLabel').textContent = on?'Ao vivo':'Pausado';
    if (on) startTimer(); else clearInterval(timer);
  }
  function startTimer(){
    clearInterval(timer);
    timer = setInterval(() => {
      const e = makeEvent(Date.now());
      EVENTS.unshift(e);
      if (EVENTS.length > 200) EVENTS.pop();
      render(new Set([e.id]));
      const sc = document.getElementById('tableScroll');
      if (sc.scrollTop < 40) sc.scrollTop = 0;
    }, 2600);
  }
  document.getElementById('liveBtn').onclick = () => setLive(!live);

  setInterval(() => { document.querySelectorAll('[data-ts]').forEach(el => el.textContent = rel(+el.dataset.ts)); }, 15000);

  render(); updateEvtCount(); setLive(true);
</script>
@endverbatim
@endpush
