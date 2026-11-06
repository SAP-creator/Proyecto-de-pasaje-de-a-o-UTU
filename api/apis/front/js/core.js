/* core.js — DOM, sesión, cliente de API, modales, formularios y tablas. */
const CONFIG = {
  ...SGER_CONFIG,
  ROLES: { 'vecino': '/user/vecino', 'operador camion': '/user/operador', 'admin operador': '/user/adminoperador', 'admin general': '/user/planificador', 'admin sistema': '/user/adminsys' },
  BASURAS: ['Plástico', 'Vidrio', 'Papel y cartón', 'Metales', 'Biodegradable'],
  CONDICIONES: ['Normal', 'Vandalizado', 'Roto', 'Quemado', 'Reparacion'],
  BASURA_EST: ['Basura_afuera', 'Limpio'],
  DIAS: ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
  CAMION_EST: ['disponible', 'no disponible', 'en trayecto'],
  INC_TIPOS: ['Grave', 'Moderado', 'Leve'],
  INC_EST: ['Pendiente', 'En proceso', 'Terminado'],
  EJ_EST: ['aprobada', 'en_curso', 'pausada', 'finalizada', 'cancelada', 'rechazada', 'vencida'],
  TIPOS_USUARIO: ['vecino', 'operador camion', 'admin operador', 'admin general', 'admin sistema']
};

/* ---------- DOM ---------- */
const h = (tag, p = {}, ...kids) => {
  const e = document.createElement(tag);
  for (const [a, v] of Object.entries(p || {})) {
    if (v == null || v === false) continue;
    if (a === 'class') e.className = v;
    else if (a === 'text') e.textContent = v;
    else if (a === 'i18n') { e.dataset.i18n = v; e.textContent = t(v); }
    else if (a === 'ph') { e.dataset.i18nPh = v; e.placeholder = t(v); }
    else if (a.startsWith('on')) e.addEventListener(a.slice(2), v);
    else e.setAttribute(a, v === true ? '' : v);
  }
  for (const c of kids.flat()) if (c != null && c !== false) e.append(c.nodeType ? c : document.createTextNode(c));
  return e;
};
const opt = (v, l, sel) => h('option', { value: v, selected: sel }, l);
const sel = (pairs, cur) => h('select', {}, pairs.map(([v, l]) => opt(v, l, String(v) === String(cur))));
const enumOpts = (list, blank) => [...(blank ? [['', t(blank)]] : []), ...list.map(v => [v, E(v)])];
const arr = v => { if (Array.isArray(v)) return v; if (typeof v === 'string' && v.startsWith('[')) { try { return JSON.parse(v); } catch { } } return v == null || v === '' ? [] : [v]; };
const tags = (list, cls = '') => h('div', { class: 'tags' }, arr(list).map(x => h('span', { class: 'tag ' + cls, text: E(x) })));
const yes = v => v === true || v === 1 || v === '1' || v === 'true';

/* ---------- Sesión ---------- */
const Auth = {
  get s() { try { return JSON.parse(sessionStorage.getItem('sger')); } catch { return null; } },
  set(tok) {
    const u = tok.USER;
    sessionStorage.setItem('sger', JSON.stringify({ USER: { CI: u.CI, TYPEUSER: u.TYPEUSER, COMPLETEUSER: !!u.COMPLETEUSER }, SIGNATURE: tok.SIGNATURE }));
  },
  user() { return this.s && this.s.USER; },
  ci() { return this.user() && this.user().CI; },
  tipo() { return this.user() && this.user().TYPEUSER; },
  completo() { return !!(this.user() && this.user().COMPLETEUSER); },
  out() { sessionStorage.removeItem('sger'); location.href = ROOT + 'pages/login.html'; }
};

/* ---------- API ---------- */
const Api = {
  /** GET/DELETE llevan el token en el header X-Token y el payload en ?payload=; POST/PUT llevan TOKEN dentro del body. */
  async call(method, path, payload) {
    const tok = Auth.s;
    const o = { method, headers: {} };
    let url = CONFIG.API_BASE + path;
    if (method === 'GET' || method === 'DELETE') {
      if (tok) o.headers['X-Token'] = btoa(JSON.stringify(tok));
      if (payload && Object.keys(payload).length) url += '?payload=' + encodeURIComponent(JSON.stringify(payload));
    } else {
      o.headers['Content-Type'] = 'application/json';
      o.body = JSON.stringify(tok ? { ...(payload || {}), TOKEN: tok } : (payload || {}));
    }
    const res = { ok: false, status: 0, code: null, d: {}, list: [], msg: '' };
    try {
      const r = await fetch(url, o);
      res.status = r.status;
      const txt = await r.text();
      let j = null;
      try { j = txt ? JSON.parse(txt) : null; } catch { j = null; if (txt) res.code = 'Error U ApiCliente ConnectionFailed'; }
      if (Array.isArray(j)) {
        // Formato antiguo: [datos..., {CODE}]
        for (const b of j) { if (Array.isArray(b)) res.list = b; else if (b && typeof b === 'object') for (const [k, v] of Object.entries(b)) { if (k === 'CODE') res.code = v; else res.d[k] = v; } }
      } else if (j && typeof j === 'object') {
        // Formato actual: {"CODE": "...", "DATA": ...}. DATA puede ser lista (filas) u objeto.
        if (j.CODE) res.code = j.CODE;
        if (Array.isArray(j.DATA)) res.list = j.DATA; else if (j.DATA && typeof j.DATA === 'object') res.d = j.DATA;
      }
      const bad = res.code && !/^OK\b/.test(res.code);
      res.ok = r.ok && !bad;
    } catch (e) { console.error('[API]', method, path, e); res.code = 'Error U ApiCliente ConnectionFailed'; }
    res.msg = codeMsg(res.code, res.ok, res.status);
    if (!res.ok) console.error(`[API] ${method} ${path} → ${res.status} ${res.code || ''}`);
    if (res.status === 401 && /InvalidToken/.test(res.code || '') && Auth.s) Auth.out();
    return res;
  },
  /** Igual que call, pero con el prefijo del rol logueado. */
  r(method, path, payload) { return this.call(method, CONFIG.ROLES[Auth.tipo()] + path, payload); }
};

/* ---------- Toast y modal ---------- */
const toast = (msg, kind = '') => {
  let box = document.getElementById('toasts');
  if (!box) { box = h('div', { id: 'toasts', 'aria-live': 'polite' }); document.body.append(box); }
  const n = h('div', { class: 'toast ' + kind, text: msg });
  box.append(n); setTimeout(() => n.remove(), 4500);
};
const Modal = {
  open(title, body, { wide = false, closable = true } = {}) {
    const close = () => ov.remove();
    const ov = h('div', { class: 'ov', role: 'dialog', 'aria-modal': 'true' },
      h('div', { class: 'modal' + (wide ? ' wide' : '') },
        h('div', { class: 'modal-h' }, h('h3', { text: title }),
          closable && h('button', { class: 'btn ghost sm', type: 'button', 'aria-label': t('close'), onclick: close }, '✕')),
        h('div', { class: 'modal-b' }, body)));
    document.body.append(ov);
    return { close, el: ov };
  },
  confirm(msg) {
    return new Promise(res => {
      const done = v => { m.close(); res(v); };
      const m = this.open(t('confirm'), [h('p', { text: msg }), h('div', { class: 'row-btns' },
        h('button', { class: 'btn', type: 'button', onclick: () => done(false) }, t('no')),
        h('button', { class: 'btn pri', type: 'button', onclick: () => done(true) }, t('yes')))], { closable: false });
    });
  }
};
/** Ejecuta una llamada, avisa el resultado y devuelve la respuesta. */
const act = async (promise, okKey) => {
  const r = await promise;
  if (r.ok) toast(okKey ? t(okKey) : r.msg, 'ok'); else toast(r.msg, 'error');
  return r;
};
const ask = async (msg, fn) => (await Modal.confirm(msg)) ? fn() : null;

/* ---------- Ubicación (lat,lng) ---------- */
const Loc = {
  parse(s) {
    const m = String(s == null ? '' : s).match(/(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)/);
    if (!m) return null;
    const lat = +m[1], lng = +m[2];
    return Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? { lat, lng } : null;
  },
  fmt: p => `${p.lat.toFixed(6)},${p.lng.toFixed(6)}`
};
const Maps = {
  _p: null,
  load() {
    if (window.google && google.maps) return Promise.resolve();
    if (this._p) return this._p;
    this._p = new Promise((ok, no) => {
      window.__gmReady = ok;
      const s = h('script', { src: `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(CONFIG.MAPS_KEY)}&callback=__gmReady&loading=async`, async: true });
      s.onerror = () => { this._p = null; no(new Error('maps')); };
      document.head.append(s);
    });
    return this._p;
  }
};

/* ---------- Formularios ---------- */
/** Cada campo devuelve { node, get() }. */
const Fld = {
  text: f => { const i = h('input', { type: f.type || 'text', value: f.v ?? '', placeholder: f.ph || '', step: f.step, min: f.min, max: f.max, autocomplete: f.type === 'password' ? 'new-password' : 'off' }); return { node: i, get: () => i.value.trim() }; },
  textarea: f => { const i = h('textarea', {}, f.v ?? ''); return { node: i, get: () => i.value.trim() }; },
  select: f => { const i = sel(f.opts, f.v); return { node: i, get: () => i.value }; },
  bool: f => { const i = h('input', { type: 'checkbox', checked: f.v ?? false }); return { node: h('div', {}, i), get: () => i.checked }; },
  multi: f => {
    const cur = arr(f.v).map(String);
    const boxes = f.opts.map(([v, l]) => [v, h('input', { type: 'checkbox', value: v, checked: cur.includes(String(v)) })]);
    return { node: h('div', { class: 'checks' }, boxes.map(([v, b]) => h('label', {}, b, E(v)))), get: () => boxes.filter(([, b]) => b.checked).map(([v]) => v) };
  },
  loc: f => {
    const i = h('input', { value: f.v ?? '', placeholder: 'lat,lng  (-34.9011,-56.1645)', autocomplete: 'off' });
    const box = h('div', { class: 'hidden' });
    let map, mk;
    const put = p => { i.value = Loc.fmt(p); if (mk) mk.setPosition(p); };
    const btn = h('button', { type: 'button', class: 'btn sm', onclick: async () => {
      box.classList.toggle('hidden');
      if (box.classList.contains('hidden') || map) return;
      const holder = h('div', { class: 'map-pick' }); box.append(holder);
      try { await Maps.load(); } catch { holder.textContent = t('map.err'); return; }
      const c = Loc.parse(i.value) || CONFIG.CENTER;
      map = new google.maps.Map(holder, { center: c, zoom: 13, streetViewControl: false, mapTypeControl: false });
      mk = new google.maps.Marker({ map, position: c });
      map.addListener('click', ev => put({ lat: ev.latLng.lat(), lng: ev.latLng.lng() }));
    } }, t('pickmap'));
    return { node: h('div', {}, i, h('div', { class: 'row-btns', style: 'justify-content:flex-start;margin-top:.4rem' }, btn), box), get: () => i.value.trim() };
  },
  /** Lista ordenada de ids (rutas). opts: [[id, etiqueta]] */
  ids: f => {
    let list = arr(f.v).map(Number);
    const box = h('div', { class: 'ilist' });
    const pick = sel(f.opts);
    const label = id => (f.opts.find(o => Number(o[0]) === id) || [id, '#' + id])[1];
    const draw = () => {
      box.replaceChildren(...list.map((id, n) => h('div', { class: 'it' }, h('span', { text: `${n + 1}. ${label(id)}` }),
        h('button', { type: 'button', class: 'btn sm ghost', disabled: n === 0, onclick: () => { [list[n - 1], list[n]] = [list[n], list[n - 1]]; draw(); } }, '↑'),
        h('button', { type: 'button', class: 'btn sm ghost', disabled: n === list.length - 1, onclick: () => { [list[n + 1], list[n]] = [list[n], list[n + 1]]; draw(); } }, '↓'),
        h('button', { type: 'button', class: 'btn sm danger', onclick: () => { list.splice(n, 1); draw(); } }, '✕'))));
    };
    draw();
    const add = h('button', { type: 'button', class: 'btn sm', onclick: () => { const v = Number(pick.value); if (v && !list.includes(v)) { list.push(v); draw(); } } }, t('add'));
    return { node: h('div', { class: 'sub' }, box, h('div', { class: 'row-btns', style: 'justify-content:flex-start' }, pick, add)), get: () => list.slice() };
  },
  /** Almacenamientos de un centro: [{ID?, TIPO_BASURAS, CANTIDAD}] */
  alm: f => {
    const box = h('div', { class: 'sub' });
    const rows = [];
    const addRow = (a = {}) => {
      const tipos = Fld.multi({ opts: CONFIG.BASURAS.map(x => [x, x]), v: a.TIPO_BASURAS });
      const cant = Fld.text({ type: 'number', v: a.CANTIDAD, min: 1, step: 'any', ph: t('f.cantidad') });
      const row = { id: a.ID, tipos, cant };
      const node = h('div', { class: 'sub' }, tipos.node, cant.node, h('button', { type: 'button', class: 'btn sm danger', onclick: () => { rows.splice(rows.indexOf(row), 1); node.remove(); } }, t('remove')));
      row.node = node; rows.push(row); box.insertBefore(node, addBtn);
    };
    const addBtn = h('button', { type: 'button', class: 'btn sm', onclick: () => addRow() }, t('add'));
    box.append(addBtn);
    arr(f.v).forEach(addRow); if (!rows.length) addRow();
    return { node: box, get: () => rows.map(r => ({ ...(r.id ? { ID: r.id } : {}), TIPO_BASURAS: r.tipos.get(), CANTIDAD: Number(r.cant.get()) })) };
  }
};
const Form = {
  /** fields: [{k,label,type,opts,v,req,hint,span}]; submit(vals) → respuesta de Api */
  open({ title, fields, submit, onDone, wide }) {
    const gs = fields.map(f => ({ f, g: (Fld[f.type || 'text'])(f) }));
    const err = h('div', { class: 'msg error hidden', role: 'alert' });
    const go = h('button', { type: 'submit', class: 'btn pri' }, t('save'));
    const m = Modal.open(title, null, { wide });
    const form = h('form', { class: 'grid2', novalidate: true },
      gs.map(({ f, g }) => h('label', { class: 'f' + (f.span === false ? '' : ' span2') }, t(f.label) + (f.req ? ' *' : ''), g.node, f.hint && h('span', { class: 'hint', text: t(f.hint) }))),
      h('div', { class: 'span2' }, err),
      h('div', { class: 'span2 row-btns' }, h('button', { type: 'button', class: 'btn', onclick: m.close }, t('cancel')), go));
    m.el.querySelector('.modal-b').append(form);
    form.addEventListener('submit', async ev => {
      ev.preventDefault(); err.classList.add('hidden');
      const vals = {};
      for (const { f, g } of gs) {
        const v = g.get();
        const vacio = v === '' || (Array.isArray(v) && !v.length);
        if (f.req && vacio) { err.textContent = t('req') + ': ' + t(f.label); err.classList.remove('hidden'); return; }
        if (!vacio || f.keepEmpty) vals[f.k] = v;
      }
      go.disabled = true;
      const r = await submit(vals);
      go.disabled = false;
      if (r.ok) { m.close(); toast(r.msg, 'ok'); onDone && onDone(r); }
      else { err.textContent = r.msg; err.classList.remove('hidden'); }
    });
    return m;
  }
};

/* ---------- Tablas y vistas genéricas ---------- */
const Tbl = {
  /** cols: [[claveI18n, fila => texto|Nodo]]; acts: fila => Nodo[] */
  build(cols, rows, acts) {
    if (!rows.length) return h('div', { class: 'empty', i18n: 'empty' });
    return h('div', { class: 'tbl-wrap' }, h('table', {},
      h('thead', {}, h('tr', {}, cols.map(c => h('th', { text: t(c[0]) })), acts && h('th'))),
      h('tbody', {}, rows.map(r => h('tr', {}, cols.map(c => h('td', {}, c[1](r) ?? '—')), acts && h('td', { class: 'acts' }, acts(r)))))));
  },
  auto(rows) {
    const keys = [...new Set(rows.flatMap(r => Object.keys(r)))];
    return this.build(keys.map(k => [k, r => show(r[k])]).map(([k, f]) => [lbl(k), f]), rows);
  }
};
const lbl = k => (I18N.has('k.' + k) ? 'k.' + k : null) || k;
const show = v => (v !== null && typeof v === 'object') ? KV(v) : (typeof v === 'boolean' ? t(v ? 'yes' : 'no') : String(v ?? '—'));
/** Lista clave/valor recursiva para objetos de detalle. */
const KV = o => {
  if (Array.isArray(o)) return o.every(x => x === null || typeof x !== 'object') ? o.map(E).join(', ') : h('div', {}, o.map(x => KV(x)));
  return h('dl', { class: 'kv' }, Object.entries(o).flatMap(([k, v]) => [h('dt', { text: I18N.has('k.' + k) ? t('k.' + k) : k }), h('dd', {}, show(v))]));
};
const btn = (key, fn, cls = 'link') => h('button', { type: 'button', class: 'btn sm ' + cls, onclick: fn }, t(key));
const head = (titleKey, ...ctrls) => h('div', { class: 'sec-head' }, h('h2', { i18n: titleKey }), ...ctrls);
const filt = (key, node) => h('label', { class: 'f' }, t(key), node);
const Loading = box => box.replaceChildren(h('div', { class: 'empty', i18n: 'loading' }));

/* ---------- Encabezado y pie comunes ---------- */
const Layout = {
  brand: () => h('a', { class: 'brand', href: ROOT + 'index.html', 'aria-label': 'SGeR' },
    h('img', { src: ROOT + 'assets/logo-empresa.svg', alt: t('company') }), h('span', { class: 'brand-sep' }), h('img', { src: ROOT + 'assets/logo-sger.svg', alt: 'SGeR' })),
  lang: () => { const s = sel(I18N.LANGS.map(l => [l[0], l[1]]), I18N.cur); s.className = 'lang'; s.setAttribute('aria-label', t('lang')); s.addEventListener('change', () => { I18N.set(s.value); location.reload(); }); return s; },
  footer: () => h('footer', { class: 'footer', id: 'contacto' }, t('footer')),
  mount() { document.documentElement.lang = I18N.cur; const f = document.getElementById('foot'); if (f) f.replaceWith(this.footer()); }
};
