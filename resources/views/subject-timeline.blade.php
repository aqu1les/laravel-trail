@extends('trail::layout')

@section('title', 'Subject Timeline')

@push('head')
<style>
  .stat-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid var(--trail-border); }
  .stat-row:last-child { border-bottom: 0; }
  .stat-k { font-size: 12px; color: var(--trail-text-subtle); }
  .stat-v { font-size: 13px; font-weight: 600; }
  .tl-payload { display: none; margin-top: 10px; }
  .trail-tl-card.expanded .tl-payload { display: block; }
  .chev { transition: transform var(--trail-dur) var(--trail-ease); }
  .trail-tl-card.expanded .chev { transform: rotate(90deg); }
</style>
@endpush

@section('main')
<header class="flex items-center justify-between px-6 border-b shrink-0" style="height: var(--trail-header-h); border-color: var(--trail-border);">
  <h1 class="text-[18px] font-semibold tracking-tight">Subject Timeline</h1>
  <div style="position:relative;width:300px">
    <div class="trail-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      <input class="trail-input" id="actorSearch" placeholder="Trocar de ator (nome ou id)…" autocomplete="off">
    </div>
    <div class="trail-menu trail-scroll" id="actorResults" style="position:absolute;top:calc(100% + 6px);right:0;left:0;z-index:30;display:none;max-height:320px;overflow:auto"></div>
  </div>
</header>

<div class="flex-1 overflow-hidden grid" style="grid-template-columns: 320px 1fr;">
  <!-- Profile rail -->
  <div class="border-r overflow-auto trail-scroll p-5" style="border-color: var(--trail-border);" id="profile"></div>

  <!-- Timeline -->
  <div class="overflow-auto trail-scroll" id="timelineScroll">
    <div class="px-7 pt-5 pb-2 sticky top-0 z-10" style="background: color-mix(in srgb, var(--trail-bg) 88%, transparent); backdrop-filter: blur(6px);">
      <div class="flex items-center gap-2 flex-wrap" id="typeFilters"></div>
    </div>
    <div class="px-7 pb-10" id="timeline"></div>
  </div>
</div>
@endsection

@push('scripts')
@verbatim
<script>
  // ---------- Categories + icons ----------
  const ICON = {
    auth:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/></svg>',
    commerce:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>',
    onboarding:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>',
    integration: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2v6M15 2v6M7 8h10v3a5 5 0 0 1-10 0z"/><path d="M12 16v6"/></svg>',
    system:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
  };
  const CATS = {
    auth:        { color: 'var(--trail-chart-3)', label: 'Auth' },
    commerce:    { color: 'var(--trail-chart-1)', label: 'Commerce' },
    onboarding:  { color: 'var(--trail-chart-2)', label: 'Onboarding' },
    integration: { color: 'var(--trail-chart-4)', label: 'Integration' },
    system:      { color: 'var(--trail-chart-5)', label: 'System' },
  };
  const TEMPLATES = [
    { name: 'session.started', cat: 'system', props: () => ({ device: pick(['desktop','mobile']), os: pick(['macOS','Windows','iOS']), app_version: '2.'+rint(1,9)+'.'+rint(0,9) }) },
    { name: 'user.logged_in', cat: 'auth', props: () => ({ method: pick(['email','google','sso']), ip_masked: '187.•••.•••.'+rint(2,254) }) },
    { name: 'onboarding.step_completed', cat: 'onboarding', props: () => ({ step: pick(['profile','workspace','invite','first_event']), index: rint(1,4), duration_ms: rint(800,9000) }) },
    { name: 'whatsapp.connected', cat: 'integration', props: () => ({ provider: 'meta_cloud', phone_masked: '+55 11 9••••-'+rint(1000,9999), verified: true }) },
    { name: 'cart.updated', cat: 'commerce', props: () => ({ items: rint(1,8), total: +(Math.random()*600+10).toFixed(2), currency: 'BRL' }) },
    { name: 'order.placed', cat: 'commerce', value: () => +(Math.random()*400+20).toFixed(2), props: () => ({ amount: +(Math.random()*400+20).toFixed(2), currency: 'BRL', items: rint(1,5), first_order: Math.random()<.3 }) },
    { name: 'invoice.paid', cat: 'commerce', value: () => +(Math.random()*900+49).toFixed(2), props: () => ({ amount: +(Math.random()*900+49).toFixed(2), plan: pick(['pro','team']), period: 'monthly' }) },
    { name: 'user.signed_up', cat: 'auth', props: () => ({ plan: pick(['free','pro','team']), method: pick(['email','google']), referrer: pick(['organic','invite']) }) },
  ];
  const ACTORS = [
    { name: 'Marina Rocha', type: 'User', id: 'ator_8821', email: 'marina@acme.app' },
    { name: 'João Silva', type: 'User', id: 'ator_3390', email: 'joao@acme.app' },
    { name: 'Beatriz Lima', type: 'User', id: 'ator_7745', email: 'bia@studio.co' },
    { name: 'Pedro Alves', type: 'User', id: 'ator_1182', email: 'pedro@acme.app' },
    { name: 'Carla Dias', type: 'User', id: 'ator_5567', email: 'carla@norte.io' },
    { name: 'Acme Team', type: 'Team', id: 'team_204', email: 'ops@acme.app' },
    { name: 'Rafael Souza', type: 'User', id: 'ator_9043', email: 'rafa@acme.app' },
    { name: 'Studio Norte', type: 'Team', id: 'team_511', email: 'hello@norte.io' },
  ];
  function rint(a,b){ return Math.floor(Math.random()*(b-a+1))+a; }
  function pick(a){ return a[Math.floor(Math.random()*a.length)]; }
  const initials = n => n.split(' ').map(w=>w[0]).slice(0,2).join('');

  let SEQ = 1;
  function history(actor){
    const events = [];
    const days = 6, now = Date.now();
    let t0 = now - (days*86400000) - rint(2,8)*3600000;
    events.push(mk('user.signed_up', actor, t0));
    let cursor = now;
    const n = rint(26, 40);
    for (let i=0;i<n;i++){
      cursor -= rint(20*60000, 9*3600000);
      const tpl = pick(TEMPLATES.filter(x=>x.name!=='user.signed_up'));
      events.push(mk(tpl.name, actor, cursor));
    }
    return events.sort((a,b)=>b.ts-a.ts);
  }
  function mk(name, actor, ts){
    const t = TEMPLATES.find(x=>x.name===name);
    return { id: SEQ++, name, cat: t.cat, value: t.value?t.value():null, props: t.props(),
      context: { ip: '187.'+rint(0,255)+'.'+rint(0,255)+'.'+rint(0,255), user_agent: pick(['Chrome/124','Safari/17']), session_id: 'sess_'+rint(10000,99999) }, ts };
  }

  function rel(ts){ const s=Math.floor((Date.now()-ts)/1000); if(s<60)return 'há '+s+'s'; const m=Math.floor(s/60); if(m<60)return 'há '+m+' min'; const h=Math.floor(m/60); if(h<24)return 'há '+h+' h'; const d=Math.floor(h/24); return 'há '+d+'d'; }
  function clock(ts){ return new Date(ts).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}); }
  function startOfDay(x){ const d=new Date(x); return new Date(d.getFullYear(),d.getMonth(),d.getDate()).getTime(); }
  function dayLabel(ts){ const diff=(startOfDay(Date.now())-startOfDay(ts))/86400000; if(diff===0)return 'Hoje'; if(diff===1)return 'Ontem'; return new Date(ts).toLocaleDateString('pt-BR',{weekday:'short',day:'2-digit',month:'short'}); }
  function fullDate(ts){ return new Date(ts).toLocaleDateString('pt-BR',{day:'2-digit',month:'short',year:'numeric'}); }

  function hl(obj){
    let s = JSON.stringify(obj, null, 2).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    return s.replace(/("(?:\\.|[^"\\])*"(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?)/g, m=>{
      let cls='n'; if(/^"/.test(m)) cls=/:$/.test(m)?'k':'s'; else if(/true|false|null/.test(m)) cls='b'; return `<span class="${cls}">${m}</span>`;
    });
  }

  let current = ACTORS[0];
  let events = [];
  const activeTypes = new Set();

  function load(actor){
    current = actor; activeTypes.clear();
    events = history(actor);
    renderProfile(); renderFilters(); renderTimeline();
    document.getElementById('timelineScroll').scrollTop = 0;
  }

  function renderProfile(){
    const total = events.length;
    const first = Math.min(...events.map(e=>e.ts));
    const last = Math.max(...events.map(e=>e.ts));
    const sessions = events.filter(e=>e.name==='session.started').length;
    const counts = {}; events.forEach(e=>counts[e.name]=(counts[e.name]||0)+1);
    const topEvent = Object.entries(counts).sort((a,b)=>b[1]-a[1])[0][0];
    const byDay = {}; events.forEach(e=>{ const k=startOfDay(e.ts); byDay[k]=(byDay[k]||0)+1; });
    const today = startOfDay(Date.now()); const bars=[];
    for(let i=6;i>=0;i--){ const k=today-i*86400000; bars.push(byDay[k]||0); }
    const maxBar = Math.max(1,...bars);
    document.getElementById('profile').innerHTML = `
      <div class="flex flex-col items-center text-center pb-4">
        <span class="trail-avatar" style="width:64px;height:64px;font-size:22px">${initials(current.name)}</span>
        <div class="mt-3 text-[17px] font-semibold tracking-tight">${current.name}</div>
        <div class="flex items-center gap-2 mt-1.5">
          <span class="trail-badge trail-badge-outline">${current.type}</span>
          <span class="trail-tag">${current.id}</span>
        </div>
        <div class="ds-label mt-2">${current.email}</div>
      </div>
      <div class="trail-card trail-card-pad" style="padding:6px 16px">
        <div class="stat-row"><span class="stat-k">Total de eventos</span><span class="stat-v trail-tnum">${total}</span></div>
        <div class="stat-row"><span class="stat-k">Sessões</span><span class="stat-v trail-tnum">${sessions}</span></div>
        <div class="stat-row"><span class="stat-k">Primeira vez visto</span><span class="stat-v">${fullDate(first)}</span></div>
        <div class="stat-row"><span class="stat-k">Última atividade</span><span class="stat-v">${rel(last)}</span></div>
        <div class="stat-row"><span class="stat-k">Evento mais comum</span><span class="stat-v trail-mono" style="font-size:11px">${topEvent}</span></div>
      </div>
      <div class="trail-card trail-card-pad mt-3">
        <div class="trail-eyebrow mb-3">Atividade · 7 dias</div>
        <div style="display:flex;align-items:flex-end;gap:6px;height:48px">
          ${bars.map(b=>`<div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%"><div title="${b} eventos" style="height:${Math.max(4,b/maxBar*100)}%;background:${b?'var(--trail-accent)':'var(--trail-surface-3)'};border-radius:3px"></div></div>`).join('')}
        </div>
        <div style="display:flex;gap:6px;margin-top:6px">${['S','T','Q','Q','S','S','D'].map((d,i)=>`<div style="flex:1;text-align:center" class="ds-label">${d}</div>`).join('')}</div>
      </div>
      <button class="trail-btn trail-btn-secondary w-full mt-3" onclick="navigator.clipboard && navigator.clipboard.writeText('${current.id}')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Copiar ID do ator
      </button>`;
  }

  function renderFilters(){
    const types = [...new Set(events.map(e=>e.name))].sort();
    const wrap = document.getElementById('typeFilters');
    wrap.innerHTML = `<span class="ds-label mr-1" style="align-self:center">${events.length} eventos</span>`
      + types.map(t => {
        const cat = CATS[events.find(e=>e.name===t).cat];
        const on = activeTypes.has(t);
        return `<button class="trail-badge" data-type="${t}" style="cursor:pointer;height:24px;${on?`background:${cat.color};color:#fff;border-color:transparent`:'background:var(--trail-surface-2);color:var(--trail-text-muted);border:1px solid var(--trail-border)'}">
          <span class="cat-dot" style="width:6px;height:6px;border-radius:2px;background:${on?'#fff':cat.color};display:inline-block"></span>
          <span class="trail-mono" style="font-size:11px">${t}</span></button>`;
      }).join('');
    wrap.querySelectorAll('[data-type]').forEach(b => b.onclick = () => {
      const t=b.dataset.type; if(activeTypes.has(t)) activeTypes.delete(t); else activeTypes.add(t);
      renderFilters(); renderTimeline();
    });
  }

  function renderTimeline(){
    const list = activeTypes.size ? events.filter(e=>activeTypes.has(e.name)) : events;
    const tl = document.getElementById('timeline');
    if (!list.length){
      tl.innerHTML = `<div class="trail-empty"><div class="trail-empty-glyph"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><div class="trail-empty-title">Nenhum evento com esse filtro</div><div class="trail-empty-body">Remova os filtros de tipo para ver toda a linha do tempo deste ator.</div></div>`;
      return;
    }
    const groups = []; let cur = null;
    list.forEach(e => { const dl = dayLabel(e.ts); if(!cur || cur.label!==dl){ cur={label:dl, date:fullDate(e.ts), items:[]}; groups.push(cur);} cur.items.push(e); });
    tl.innerHTML = groups.map(g => `
      <div class="trail-tl-day">
        <span class="trail-tl-day-label">${g.label}</span>
        <span class="ds-label">${g.date} · ${g.items.length} evento${g.items.length>1?'s':''}</span>
        <span class="trail-tl-day-line"></span>
      </div>
      <div class="trail-timeline">
        ${g.items.map(node).join('')}
      </div>`).join('');
    tl.querySelectorAll('.trail-tl-card').forEach(card => card.onclick = () => card.classList.toggle('expanded'));
  }
  function node(e){
    const c = CATS[e.cat];
    const chips = Object.entries(e.props).slice(0,4).map(([k,v])=>`<span class="prop-chip">${k}=${typeof v==='string'?v:String(v)}</span>`).join('');
    return `<div class="trail-tl-row">
      <div class="trail-tl-node" style="color:${c.color};border-color:color-mix(in srgb, ${c.color} 35%, var(--trail-border))">${ICON[e.cat]}</div>
      <div class="trail-tl-card">
        <div class="flex items-center gap-2">
          <span class="trail-mono" style="font-size:13px;color:var(--trail-text)">${e.name}</span>
          ${e.value!=null?`<span class="trail-badge trail-badge-success">${e.value.toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>`:''}
          <span class="ds-label ml-auto" style="white-space:nowrap">${clock(e.ts)} · ${rel(e.ts)}</span>
          <svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--trail-text-faint)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </div>
        <div class="flex items-center gap-1.5 flex-wrap mt-2">${chips}</div>
        <div class="tl-payload"><pre class="trail-code">${hl({ properties: e.props, context: e.context })}</pre></div>
      </div>
    </div>`;
  }

  const searchInput = document.getElementById('actorSearch');
  const results = document.getElementById('actorResults');
  function renderResults(q){
    const matches = ACTORS.filter(a => (a.name+' '+a.id+' '+a.email).toLowerCase().includes(q.toLowerCase()));
    results.innerHTML = matches.length ? matches.map(a => `
      <div class="trail-menu-item" data-id="${a.id}" ${a.id===current.id?'aria-checked="true"':''}>
        <span class="trail-avatar trail-avatar-sm">${initials(a.name)}</span>
        <div style="min-width:0;flex:1"><div style="font-size:13px;color:var(--trail-text)">${a.name}</div><div class="ds-label">${a.type} · ${a.id}</div></div>
        ${a.id===current.id?'<span class="trail-badge trail-badge-accent">atual</span>':''}
      </div>`).join('') : '<div class="trail-menu-item" style="pointer-events:none;color:var(--trail-text-faint)">Nenhum ator encontrado</div>';
    results.querySelectorAll('[data-id]').forEach(it => it.onclick = () => {
      const a = ACTORS.find(x=>x.id===it.dataset.id); load(a);
      searchInput.value=''; results.style.display='none';
    });
  }
  searchInput.addEventListener('focus', () => { renderResults(searchInput.value); results.style.display='block'; });
  searchInput.addEventListener('input', () => { renderResults(searchInput.value); results.style.display='block'; });
  document.addEventListener('click', e => { if(!e.target.closest('#actorSearch') && !e.target.closest('#actorResults')) results.style.display='none'; });

  load(current);
</script>
@endverbatim
@endpush
