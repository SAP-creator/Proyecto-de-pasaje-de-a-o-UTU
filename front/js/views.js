/* views.js — pantallas de la aplicación (una por pestaña). Cada vista: root => ({ show() }). */
const RR = { V: 'vecino', O: 'operador camion', AO: 'admin operador', AP: 'admin general', AS: 'admin sistema' };
const is = (...k) => k.some(x => RR[x] === Auth.tipo());
const rows = async (path, payload) => { const r = await Api.r('GET', path, payload); return r.ok ? r.list : []; };
const idLabel = (r, extra) => [r.ID, extra].filter(Boolean).join(' · ');
const contOpts = async () => (await rows('/contenedor')).map(c => [c.ID, `#${c.ID} ${c.UBICACION || ''}`]);
const centroOpts = async () => (await rows('/centro')).map(c => [c.ID, `#${c.ID} ${c.UBICACION || ''}`]);
const rutaOpts = async () => (await rows('/ruta')).map(c => [c.ID, `#${c.ID}`]);
const camOpts = async () => (await rows('/camion')).map(c => [c.ID, `#${c.ID} ${c.MATRICULA || ''}`]);
const num = v => (v === '' || v == null ? v : Number(v));
const notNow = () => ({ ok: false, msg: t('nothing') });

/* ===================== Contenedores (compartido con el mapa) ===================== */
const Cont = {
  async detalle(id) {
    const r = await Api.r('GET', '/contenedor/data', { CONTENEDOR: { ID: id } });
    if (!r.ok) return toast(r.msg, 'error');
    const c = r.d.CONTENEDOR || r.d;
    const hist = {}, base = {};
    for (const [k, v] of Object.entries(c)) (/^(HISTORIAL|VACIADO)/.test(k) ? hist : base)[k] = v;
    const acts = h('div', { class: 'row-btns', style: 'justify-content:flex-start' },
      btn('a.estado', () => Cont.estado(id), 'btn'), btn('a.incidente', () => Cont.incidente(id), 'btn'),
      is('O') && btn('a.llenado', () => Cont.llenado(id), 'btn'),
      is('O') && btn('a.vaciar', () => ask(t('q.vaciar'), () => act(Api.r('POST', '/contenedor/vaciado', { CONTENEDOR: { ID: id } }))), 'btn'));
    Modal.open(`${t('k.CONTENEDOR')} #${id}`, [KV(base), acts, ...Object.entries(hist).map(([k, v]) => h('details', {}, h('summary', { text: t('k.' + k) }), KV(v)))], { wide: true });
  },
  estado(id) {
    const cond = is('V') ? ['Vandalizado', 'Roto', 'Quemado'] : is('AO') ? CONFIG.CONDICIONES : CONFIG.CONDICIONES.filter(x => x !== 'Reparacion');
    const bas = is('V') ? ['Basura_afuera'] : CONFIG.BASURA_EST;
    Form.open({ title: t('a.estado'), fields: [
      { k: 'CONDICION', label: 'k.CONDICION', type: 'multi', opts: cond.map(x => [x, x]) },
      { k: 'BASURA', label: 'k.BASURA', type: 'multi', opts: bas.map(x => [x, x]) },
      { k: 'FECHA', label: 'k.FECHA', hint: 'hint.fecha', ph: '2026-10-31 14:30:00' }],
      submit: v => Object.keys(v).length ? Api.r('POST', '/contenedor/estado', { CONTENEDOR: { ID: id, ESTADO: v } }) : notNow() });
  },
  incidente(id) {
    Form.open({ title: t('a.incidente'), fields: [
      { k: 'TIPO_INCIDENTE', label: 'k.TIPO_INCIDENTE', type: 'select', opts: enumOpts(CONFIG.INC_TIPOS), req: true },
      { k: 'DESCRIPCION', label: 'k.DESCRIPCION', type: 'textarea' }],
      submit: v => Api.r('POST', '/contenedor/incidente', { INCIDENTE: { ID_CONTENEDOR: id, ...v } }) });
  },
  llenado(id) {
    Form.open({ title: t('a.llenado'), fields: [
      { k: 'LLENADO', label: 'k.LLENADO', type: 'number', min: 0, max: 11, req: true, hint: 'hint.llenado' },
      { k: 'FECHA', label: 'k.FECHA', hint: 'hint.fecha' }],
      submit: v => Api.r('POST', '/contenedor/llenado', { CONTENEDOR: { ID: id, ...v, LLENADO: Number(v.LLENADO) } }) });
  },
  edit(c, done) {
    Form.open({ title: t(c ? 'a.editar' : 'a.nuevo'), fields: [
      { k: 'UBICACION', label: 'k.UBICACION', type: 'loc', v: c && c.UBICACION, req: true },
      { k: 'EN_FUNCION', label: 'k.EN_FUNCION', type: 'bool', v: c ? yes(c.EN_FUNCION) : true, keepEmpty: true },
      { k: 'TIPO_BASURAS', label: 'k.TIPO_BASURAS', type: 'multi', opts: CONFIG.BASURAS.map(x => [x, x]), v: c && c.TIPO_BASURAS, req: true },
      { k: 'CANTIDAD', label: 'k.CANTIDAD', type: 'number', min: 1, step: 'any', v: c && c.CANTIDAD, req: true, hint: 'hint.litros' }],
      submit: v => {
        const o = { UBICACION: v.UBICACION, EN_FUNCION: v.EN_FUNCION, ALMACENADO: { TIPO_BASURAS: v.TIPO_BASURAS, CANTIDAD: Number(v.CANTIDAD) } };
        return c ? Api.r('PUT', '/contenedor', { CONTENEDOR: { ID: c.ID, ...o } }) : Api.r('POST', '/contenedor', { CONTENEDOR: o });
      }, onDone: done });
  }
};
const Fill = n => { n = Number(n || 0); return n >= 11 ? '#7a1620' : n >= 8 ? '#c62f3a' : n >= 4 ? '#d99a1b' : '#5f9a22'; };
const fillBar = n => h('div', { class: 'bar', title: `${n ?? 0}/11` }, h('i', { style: `width:${Math.min(100, (Number(n || 0) / 11) * 100)}%;background:${Fill(n)}` }));

const V = {};

V.contenedores = root => {
  const fEn = sel([['', t('all')], ['true', t('on')], ['false', t('off')]]);
  const fTipo = sel(enumOpts(CONFIG.BASURAS, 'all'));
  const out = h('div');
  root.append(head('tab.contenedores', is('AP') && btn('a.nuevo', () => Cont.edit(null, show), 'btn pri')),
    h('div', { class: 'filters' }, filt('f.estado', fEn), filt('f.tipo', fTipo)), out);
  fEn.onchange = fTipo.onchange = () => show();
  async function show() {
    Loading(out);
    const p = {}; if (fEn.value) p.EN_FUNCION = fEn.value === 'true'; if (fTipo.value) p.TIPO_BASURA = fTipo.value;
    const r = await Api.r('GET', '/contenedor', p);
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    out.replaceChildren(Tbl.build([
      ['k.ID', c => '#' + c.ID], ['k.UBICACION', c => c.UBICACION],
      ['k.EN_FUNCION', c => h('span', { class: 'tag' + (yes(c.EN_FUNCION) ? '' : ' off'), text: t(yes(c.EN_FUNCION) ? 'on' : 'off') })],
      ['k.TIPO_BASURAS', c => tags(c.TIPO_BASURAS)], ['k.CANTIDAD', c => c.CANTIDAD != null ? c.CANTIDAD + ' L' : '—'],
      ['k.CONDICION', c => tags(c.CONDICION, 'warn')], ['k.BASURA', c => tags(c.BASURA, 'warn')], ['k.LLENADO', c => fillBar(c.LLENADO)]], r.list,
      c => [btn('a.detalle', () => Cont.detalle(c.ID)),
      is('AP') && btn('a.editar', () => Cont.edit(c, show)),
      is('AP') && btn('a.borrar', () => ask(t('q.borrar'), async () => { (await act(Api.r('DELETE', '/contenedor', { CONTENEDOR: { ID: c.ID } }))).ok && show(); }), 'danger')]));
  }
  return { show };
};

V.incidentes = root => {
  const fEst = sel(enumOpts(CONFIG.INC_EST, 'all'));
  const fCont = h('input', { type: 'number', min: 1, ph: 'f.idcont' });
  const out = h('div');
  const nuevo = async () => {
    const opts = await contOpts();
    Form.open({ title: t('a.incidente'), fields: [
      { k: 'ID_CONTENEDOR', label: 'k.CONTENEDOR', type: 'select', opts, req: true },
      { k: 'TIPO_INCIDENTE', label: 'k.TIPO_INCIDENTE', type: 'select', opts: enumOpts(CONFIG.INC_TIPOS), req: true },
      { k: 'DESCRIPCION', label: 'k.DESCRIPCION', type: 'textarea' }],
      submit: v => Api.r('POST', '/contenedor/incidente', { INCIDENTE: { ...v, ID_CONTENEDOR: Number(v.ID_CONTENEDOR) } }), onDone: show });
  };
  root.append(head('tab.incidentes', btn('a.incidente', nuevo, 'btn pri')), h('div', { class: 'filters' }, filt('f.estado', fEst), filt('f.idcont', fCont)), out);
  fEst.onchange = fCont.onchange = () => show();
  async function show() {
    Loading(out);
    const p = {}; if (fEst.value) p.ESTADO_INCIDENTE = fEst.value; if (fCont.value) p.ID_CONTENEDOR = Number(fCont.value);
    const r = await Api.r('GET', '/contenedor/incidente', p);
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    out.replaceChildren(Tbl.build([
      ['k.ID', i => '#' + i.ID], ['k.CONTENEDOR', i => '#' + i.ID_CONTENEDOR],
      ['k.TIPO_INCIDENTE', i => h('span', { class: 'tag ' + (i.TIPO_INCIDENTE === 'Grave' ? 'bad' : i.TIPO_INCIDENTE === 'Moderado' ? 'warn' : ''), text: E(i.TIPO_INCIDENTE) })],
      ['k.ESTADO_INCIDENTE', i => E(i.ESTADO_INCIDENTE)], ['k.DESCRIPCION', i => i.DESCRIPCION || '—']], r.list,
      is('AO', 'AP') ? i => [btn('a.editar', () => Form.open({ title: t('a.editar'), fields: [
        { k: 'TIPO_INCIDENTE', label: 'k.TIPO_INCIDENTE', type: 'select', opts: enumOpts(CONFIG.INC_TIPOS), v: i.TIPO_INCIDENTE },
        { k: 'ESTADO_INCIDENTE', label: 'k.ESTADO_INCIDENTE', type: 'select', opts: enumOpts(CONFIG.INC_EST), v: i.ESTADO_INCIDENTE },
        { k: 'DESCRIPCION', label: 'k.DESCRIPCION', type: 'textarea', v: i.DESCRIPCION }],
        submit: v => Api.r('PUT', '/contenedor/incidente', { INCIDENTE: { ID: i.ID, ...v } }), onDone: show })),
      btn('a.borrar', () => ask(t('q.borrar'), async () => { (await act(Api.r('DELETE', '/contenedor/incidente', { INCIDENTE: { ID: i.ID } }))).ok && show(); }), 'danger')] : null));
  }
  return { show };
};

/* ===================== Centros ===================== */
V.centros = root => {
  const out = h('div');
  const edit = async c => {
    let cur = null;
    if (c) { const r = await Api.r('GET', '/centro/data', { CENTRO: { ID: c.ID } }); if (!r.ok) return toast(r.msg, 'error'); cur = r.d.CENTRO || r.d; }
    Form.open({ title: t(c ? 'a.editar' : 'a.nuevo'), wide: true, fields: [
      { k: 'UBICACION', label: 'k.UBICACION', type: 'loc', v: c && c.UBICACION, req: true },
      { k: 'ALMACENAMIENTOS', label: 'k.ALMACENAMIENTOS', type: 'alm', v: cur && cur.ALMACENAMIENTOS, req: true }],
      submit: v => c ? Api.r('PUT', '/centro', { CENTRO: { ID: c.ID, ...v } }) : Api.r('POST', '/centro', { CENTRO: v }), onDone: show });
  };
  const retiros = async id => {
    const r = await Api.r('GET', '/centro/retiro', { CENTRO: { ID: id } });
    Modal.open(t('a.retiros'), r.ok ? Tbl.auto(r.list) : h('div', { class: 'msg error', text: r.msg }), { wide: true });
  };
  const detalle = async id => {
    const r = await Api.r('GET', '/centro/data', { CENTRO: { ID: id } });
    if (!r.ok) return toast(r.msg, 'error');
    const c = r.d.CENTRO || r.d, al = arr(c.ALMACENAMIENTOS);
    const tabla = Tbl.build([['k.TIPO_BASURAS', a => tags(a.TIPO_BASURAS)], ['k.CANTIDAD', a => a.CANTIDAD + ' L'],
      ['k.VOLUMEN_ACTUAL', a => h('div', {}, `${a.VOLUMEN_ACTUAL ?? 0} L`, h('div', { class: 'bar' }, h('i', { style: `width:${Math.min(100, (a.VOLUMEN_ACTUAL / a.CANTIDAD) * 100 || 0)}%` })))]], al,
      is('AO') ? a => [btn('a.retirar', () => Form.open({ title: t('a.retirar'), fields: [{ k: 'VOLUMEN', label: 'k.VOLUMEN', type: 'number', min: 0, step: 'any', hint: 'hint.retiro' }],
        submit: v => Api.r('POST', '/centro/retiro', { CENTRO: { ID: id, ID_ALMACENAMIENTO: a.ID, ...(v.VOLUMEN ? { VOLUMEN: Number(v.VOLUMEN) } : {}) } }) }))] : null);
    Modal.open(`${t('k.CENTRO')} #${id}`, [h('p', { text: c.UBICACION || '' }), tabla, is('AO', 'AP') && btn('a.retiros', () => retiros(id), 'btn')], { wide: true });
  };
  root.append(head('tab.centros', is('AP') && btn('a.nuevo', () => edit(null), 'btn pri')), out);
  async function show() {
    Loading(out);
    const r = await Api.r('GET', '/centro');
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    out.replaceChildren(Tbl.build([['k.ID', c => '#' + c.ID], ['k.UBICACION', c => c.UBICACION]], r.list, c => [
      btn('a.detalle', () => detalle(c.ID)), is('AP') && btn('a.editar', () => edit(c)),
      is('AP') && btn('a.borrar', () => ask(t('q.borrar'), async () => { (await act(Api.r('DELETE', '/centro', { CENTRO: { ID: c.ID } }))).ok && show(); }), 'danger')]));
  }
  return { show };
};

/* ===================== Rutas ===================== */
V.rutas = root => {
  const out = h('div');
  const edit = async ru => {
    const [cs, ct] = await Promise.all([is('AP') ? centroOpts() : [], contOpts()]);
    let cur = null;
    if (ru) { const r = await Api.r('GET', '/ruta/data', { RUTA: { ID: ru.ID } }); cur = r.ok ? (r.d.RUTA || r.d) : null; }
    const ids = cur ? arr(cur.CONTENEDORES).map(x => typeof x === 'object' ? (x.ID_CONTENEDOR ?? x.ID) : x) : [];
    Form.open({ title: t(ru ? 'a.editar' : 'a.nuevo'), wide: true, fields: [
      { k: 'ID_CENTRO_INICIO', label: 'k.ID_CENTRO_INICIO', type: 'select', opts: cs, v: ru && ru.ID_CENTRO_INICIO, req: true },
      { k: 'ID_CENTRO_FINAL', label: 'k.ID_CENTRO_FINAL', type: 'select', opts: cs, v: ru && ru.ID_CENTRO_FINAL, req: true },
      { k: 'CONTENEDORES', label: 'k.CONTENEDORES', type: 'ids', opts: [['', '—'], ...ct], v: ids, req: true, keepEmpty: true }],
      submit: v => {
        const o = { ID_CENTRO_INICIO: Number(v.ID_CENTRO_INICIO), ID_CENTRO_FINAL: Number(v.ID_CENTRO_FINAL), CONTENEDORES: v.CONTENEDORES };
        return ru ? Api.r('PUT', '/ruta', { RUTA: { ID: ru.ID, ...o } }) : Api.r('POST', '/ruta', { RUTA: o });
      },
      onDone: r => { const w = r.d.RUTA && r.d.RUTA.ADVERTENCIAS; if (w && (Array.isArray(w) ? w.length : Object.keys(w).length)) Modal.open(t('k.ADVERTENCIAS'), KV(w)); show(); } });
  };
  const detalle = async id => {
    const r = await Api.r('GET', '/ruta/data', { RUTA: { ID: id } });
    if (!r.ok) return toast(r.msg, 'error');
    Modal.open(`${t('k.RUTA')} #${id}`, [KV(r.d.RUTA || r.d), btn('a.vermapa', () => App.go('mapa', { ruta: id }), 'btn pri')], { wide: true });
  };
  root.append(head('tab.rutas', is('AP') && btn('a.nuevo', () => edit(null), 'btn pri')), out);
  async function show() {
    Loading(out);
    const r = await Api.r('GET', '/ruta');
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    out.replaceChildren(Tbl.build([['k.ID', c => '#' + c.ID], ['k.ID_CENTRO_INICIO', c => '#' + c.ID_CENTRO_INICIO], ['k.ID_CENTRO_FINAL', c => '#' + c.ID_CENTRO_FINAL], ['k.CANTIDAD_CONTENEDORES', c => c.CANTIDAD_CONTENEDORES]], r.list, c => [
      btn('a.detalle', () => detalle(c.ID)), btn('a.vermapa', () => App.go('mapa', { ruta: c.ID })), is('AP') && btn('a.editar', () => edit(c)),
      is('AP') && btn('a.borrar', () => ask(t('q.borrar'), async () => { (await act(Api.r('DELETE', '/ruta', { RUTA: { ID: c.ID } }))).ok && show(); }), 'danger')]));
  }
  return { show };
};

/* ===================== Camiones ===================== */
V.camiones = root => {
  const out = h('div');
  const edit = c => Form.open({ title: t(c ? 'a.editar' : 'a.nuevo'), fields: [
    { k: 'MATRICULA', label: 'k.MATRICULA', v: c && c.MATRICULA, req: true },
    { k: 'CANTIDAD', label: 'k.CANTIDAD', type: 'number', min: 1, step: 'any', v: c && c.CANTIDAD, req: true, hint: 'hint.litros' },
    { k: 'ESTADO_CAMION', label: 'k.ESTADO_CAMION', type: 'select', opts: enumOpts(CONFIG.CAMION_EST), v: c && (c.ESTADO_CAMION ?? c.ESTADO) },
    { k: 'TIPO_BASURAS', label: 'k.TIPO_BASURAS', type: 'multi', opts: CONFIG.BASURAS.map(x => [x, x]), v: c && c.TIPO_BASURAS, req: true }],
    submit: v => { const o = { ...v, ...(v.CANTIDAD ? { CANTIDAD: Number(v.CANTIDAD) } : {}) }; return c ? Api.r('PUT', '/camion', { CAMION: { ID: c.ID, ...o } }) : Api.r('POST', '/camion', { CAMION: o }); }, onDone: show });
  root.append(head('tab.camiones', is('AO') && btn('a.nuevo', () => edit(null), 'btn pri')), out);
  async function show() {
    Loading(out);
    const r = await Api.r('GET', '/camion');
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    out.replaceChildren(Tbl.build([['k.ID', c => '#' + c.ID], ['k.MATRICULA', c => c.MATRICULA], ['k.CANTIDAD', c => c.CANTIDAD + ' L'], ['k.TIPO_BASURAS', c => tags(c.TIPO_BASURAS)],
      ['k.ESTADO_CAMION', c => h('span', { class: 'tag' + ((c.ESTADO_CAMION ?? c.ESTADO) === 'disponible' ? '' : ' warn'), text: E(c.ESTADO_CAMION ?? c.ESTADO) })]], r.list,
      is('AO') ? c => [btn('a.editar', () => edit(c)), btn('a.borrar', () => ask(t('q.borrar'), async () => { (await act(Api.r('DELETE', '/camion', { CAMION: { ID: c.ID } }))).ok && show(); }), 'danger')] : null));
  }
  return { show };
};

/* ===================== Trayectos ===================== */
V.trayectos = root => {
  const out = h('div');
  const edit = async tr => {
    const [rs, cs] = await Promise.all([rutaOpts(), camOpts()]);
    Form.open({ title: t(tr ? 'a.editar' : 'a.nuevo'), fields: [
      { k: 'ID_RUTA', label: 'k.RUTA', type: 'select', opts: rs, v: tr && tr.ID_RUTA, req: true },
      { k: 'DIA_SEMANA', label: 'k.DIA_SEMANA', type: 'select', opts: enumOpts(CONFIG.DIAS, 'none'), v: tr && tr.DIA_SEMANA, span: false },
      { k: 'FECHA_ESPECIAL', label: 'k.FECHA_ESPECIAL', type: 'date', v: tr && tr.FECHA_ESPECIAL, span: false, hint: 'hint.diaofecha' },
      { k: 'HORA', label: 'k.HORA', ph: '08:30:00', v: tr && tr.HORA, req: true, hint: 'hint.hora' },
      { k: 'INICIO_AUTOMATICO', label: 'k.INICIO_AUTOMATICO', type: 'bool', v: tr && yes(tr.INICIO_AUTOMATICO), keepEmpty: true },
      { k: 'ID_CAMION_AUTO', label: 'k.ID_CAMION_AUTO', type: 'select', opts: [['', t('none')], ...cs], v: tr && tr.ID_CAMION_AUTO, span: false },
      { k: 'CEDULA_OPERARIO_AUTO', label: 'k.CEDULA_OPERARIO_AUTO', v: tr && tr.CEDULA_OPERARIO_AUTO, span: false }],
      submit: v => {
        const o = { ...v, ID_RUTA: Number(v.ID_RUTA) };
        if (o.ID_CAMION_AUTO) o.ID_CAMION_AUTO = Number(o.ID_CAMION_AUTO);
        return tr ? Api.r('PUT', '/trayecto', { TRAYECTO: { ID: tr.ID, ...o } }) : Api.r('POST', '/trayecto', { TRAYECTO: o });
      }, onDone: show });
  };
  root.append(head('tab.trayectos', is('AO') && btn('a.nuevo', () => edit(null), 'btn pri')), out);
  async function show() {
    Loading(out);
    const r = await Api.r('GET', '/trayecto');
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    out.replaceChildren(Tbl.build([['k.ID', c => '#' + c.ID], ['k.RUTA', c => '#' + c.ID_RUTA], ['k.DIA_SEMANA', c => c.DIA_SEMANA ? E(c.DIA_SEMANA) : (c.FECHA_ESPECIAL || '—')], ['k.HORA', c => c.HORA],
      ['k.INICIO_AUTOMATICO', c => t(yes(c.INICIO_AUTOMATICO) ? 'yes' : 'no')], ['k.ID_CAMION_AUTO', c => c.ID_CAMION_AUTO ? '#' + c.ID_CAMION_AUTO : '—'], ['k.CEDULA_OPERARIO_AUTO', c => c.CEDULA_OPERARIO_AUTO || '—']], r.list,
      is('AO') ? c => [btn('a.editar', () => edit(c)), btn('a.borrar', () => ask(t('q.borrar'), async () => { (await act(Api.r('DELETE', '/trayecto', { TRAYECTO: { ID: c.ID } }))).ok && show(); }), 'danger')] : null));
  }
  return { show };
};

/* ===================== Ejecuciones ===================== */
V.ejecuciones = root => {
  const fEst = sel(enumOpts(CONFIG.EJ_EST, 'all'));
  const out = h('div');
  const post = (path, e, extra) => Api.r('POST', '/ejecucion/' + path, { EJECUCION: { ID: e.ID, ...(extra || {}) } });
  const notas = (path, e, titleKey) => Form.open({ title: t(titleKey), fields: [{ k: 'NOTAS', label: 'k.NOTAS', type: 'textarea' }], submit: v => post(path, e, v), onDone: show });
  const aprobar = async () => {
    const [trs, cs] = await Promise.all([rows('/trayecto'), rows('/camion')]);
    Form.open({ title: t('a.aprobar'), fields: [
      { k: 'ID_TRAYECTO', label: 'k.ID_TRAYECTO', type: 'select', opts: trs.map(x => [x.ID, `#${x.ID} · ${t('k.RUTA')} ${x.ID_RUTA} · ${x.DIA_SEMANA ? E(x.DIA_SEMANA) : x.FECHA_ESPECIAL} ${x.HORA}`]), req: true },
      { k: 'FECHA_PROGRAMADA', label: 'k.FECHA_PROGRAMADA', type: 'date', req: true },
      { k: 'ID_CAMION', label: 'k.ID_CAMION', type: 'select', opts: cs.map(x => [x.ID, `#${x.ID} ${x.MATRICULA} · ${E(x.ESTADO_CAMION ?? x.ESTADO)}`]), req: true },
      { k: 'CEDULA_OPERARIO', label: 'k.CEDULA_OPERARIO', req: true },
      { k: 'NOTAS', label: 'k.NOTAS', type: 'textarea' }],
      submit: v => Api.r('POST', '/ejecucion/aprobar', { EJECUCION: { ...v, ID_TRAYECTO: Number(v.ID_TRAYECTO), ID_CAMION: Number(v.ID_CAMION) } }), onDone: show });
  };
  const acciones = e => {
    const s = e.ESTADO_EJECUCION, a = [btn('a.detalle', () => Modal.open(`${t('tab.ejecuciones')} #${e.ID}`, KV(e), { wide: true }))];
    const go = (path, k) => a.push(btn(k, async () => { (await act(post(path, e))).ok && show(); }));
    if (is('O') && s === 'aprobada') go('iniciar', 'a.iniciar');
    if (is('O') && s === 'pausada') go('reanudar', 'a.reanudar');
    if (is('O', 'AO') && s === 'en_curso') go('pausar', 'a.pausar');
    if (is('O') && (s === 'en_curso' || s === 'pausada')) a.push(btn('a.terminar', () => Form.open({ title: t('a.terminar'), fields: [{ k: 'LLENADO_CAMION', label: 'k.LLENADO_CAMION', type: 'number', min: 0, max: 11, hint: 'hint.llenado' }],
      submit: v => post('terminar', e, v.LLENADO_CAMION !== undefined ? { LLENADO_CAMION: Number(v.LLENADO_CAMION) } : {}), onDone: show })));
    if (is('O', 'AO') && ['aprobada', 'en_curso', 'pausada'].includes(s)) a.push(btn('a.cancelar', () => notas('cancelar', e, 'a.cancelar'), 'danger'));
    if (is('AP') && s === 'aprobada') a.push(btn('a.rechazar', () => notas('rechazar', e, 'a.rechazar'), 'danger'));
    return a;
  };
  root.append(head('tab.ejecuciones', is('AP') && btn('a.aprobar', aprobar, 'btn pri')), h('div', { class: 'filters' }, filt('f.estado', fEst)), out);
  fEst.onchange = () => show();
  async function show() {
    Loading(out);
    const r = await Api.r('GET', '/ejecucion', fEst.value ? { ESTADO_EJECUCION: fEst.value } : {});
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    out.replaceChildren(Tbl.build([['k.ID', e => '#' + e.ID], ['k.ID_TRAYECTO', e => '#' + e.ID_TRAYECTO], ['k.FECHA_PROGRAMADA', e => e.FECHA_PROGRAMADA],
      ['k.ESTADO_EJECUCION', e => h('span', { class: 'tag' + (['cancelada', 'rechazada', 'vencida'].includes(e.ESTADO_EJECUCION) ? ' bad' : e.ESTADO_EJECUCION === 'finalizada' ? ' off' : ''), text: E(e.ESTADO_EJECUCION) })],
      ['k.CEDULA_OPERARIO', e => e.CEDULA_OPERARIO], ['k.ID_CAMION', e => '#' + e.ID_CAMION]], r.list, acciones));
  }
  return { show };
};

/* ===================== Mis datos ===================== */
V.datos = root => {
  const vec = is('V');
  const f = (name, key, extra = {}) => h('label', { class: 'f' + (extra.span ? ' span2' : '') }, t(key), h('input', { name, type: extra.type || 'text', disabled: extra.dis, value: extra.v ?? '', ph: extra.ph, autocomplete: 'off' }));
  const msg = h('div', { class: 'msg hidden', role: 'status' });
  const form = h('form', { class: 'grid2', novalidate: true },
    f('ci', 'k.CI', { dis: true, v: Auth.ci() }), f('tipo', 'k.TYPEUSER', { dis: true, v: E(Auth.tipo()) }),
    !vec && f('FIRSTNAME', 'k.FIRSTNAME'), !vec && f('LASTNAME', 'k.LASTNAME'),
    f('PASSWORD', 'k.newpass', { type: 'password', span: true, ph: 'ph.blank' }), h('div', { class: 'span2' }, msg),
    h('div', { class: 'span2' }, h('button', { class: 'btn pri pill', type: 'submit' }, t('save'))));
  form.addEventListener('submit', async ev => {
    ev.preventDefault();
    const u = {}; for (const k of ['FIRSTNAME', 'LASTNAME', 'PASSWORD']) { const v = form.elements[k] && form.elements[k].value.trim(); if (v) u[k] = v; }
    const ok = (txt, c) => { msg.textContent = txt; msg.className = 'msg ' + c; };
    if (!Object.keys(u).length) return ok(t('nothing'), 'error');
    const r = await Api.call('PUT', '/user/profile', { USER: u });
    ok(r.msg, r.ok ? 'ok' : 'error'); if (r.ok) form.elements.PASSWORD.value = '';
  });
  root.append(head('tab.datos'), h('div', { class: 'panel' }, form));
  return { show() { } };
};

/* ===================== Admin sistema ===================== */
V.usuarios = root => {
  const fTipo = sel(enumOpts(CONFIG.TIPOS_USUARIO, 'all')), fCi = h('input', { ph: 'f.ci', inputmode: 'numeric' });
  const out = h('div'); let all = [];
  root.append(head('tab.usuarios'), h('div', { class: 'filters' }, filt('f.tipo', fTipo), filt('f.ci', fCi)), out);
  const draw = () => out.replaceChildren(Tbl.build([['k.CI', u => u[0]], ['k.TYPEUSER', u => E(u[1])]], all.filter(u => String(u[0]).includes(fCi.value.trim())), u => [
    btn('a.opciones', () => opciones(u)),
    String(u[0]) !== String(Auth.ci()) && btn('a.borrar', () => ask(t('q.borrar'), async () => { (await act(Api.r('DELETE', '/user', { USER: { CI: Number(u[0]) } }))).ok && show(); }), 'danger')]));
  const opciones = async u => {
    const r = await Api.r('GET', '/user/data', { USER: { CI: Number(u[0]) } });
    if (!r.ok) return toast(r.msg, 'error');
    const d = r.d.USER || r.d, worker = u[1] !== 'vecino';
    Form.open({ title: `${t('k.CI')} ${u[0]} · ${E(u[1])}`, fields: [
      ...(worker ? [{ k: 'FIRSTNAME', label: 'k.FIRSTNAME', v: d.FIRSTNAME, span: false }, { k: 'LASTNAME', label: 'k.LASTNAME', v: d.LASTNAME, span: false }] : []),
      { k: 'PASSWORD', label: 'k.newpass', type: 'password', hint: 'ph.blank' }],
      submit: v => Object.keys(v).length ? Api.r('PUT', '/user/data', { USER: { CI: Number(u[0]), ...v } }) : notNow() });
  };
  fTipo.onchange = () => show(); fCi.oninput = draw;
  async function show() {
    Loading(out);
    const r = await Api.r('GET', '/users', fTipo.value ? { TYPEUSER: fTipo.value } : {});
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    all = Object.entries(r.d); draw();
  }
  return { show };
};

V.solicitudes = root => {
  const fTipo = sel(enumOpts(CONFIG.TIPOS_USUARIO, 'all')), fCi = h('input', { ph: 'f.ci', inputmode: 'numeric' });
  const out = h('div'); let all = [];
  root.append(head('tab.solicitudes'), h('div', { class: 'filters' }, filt('f.tipo', fTipo), filt('f.ci', fCi)), out);
  const draw = () => out.replaceChildren(Tbl.build([['k.CI', s => s.CI], ['k.TYPEUSER', s => E(s.TYPEUSER)]], all.filter(s => String(s.CI).includes(fCi.value.trim())), s => [
    btn('a.aceptar', async () => { (await act(Api.r('POST', '/requests/accept', { USER: { CI: Number(s.CI) } }))).ok && show(); }),
    btn('a.borrar', () => ask(t('q.borrar'), async () => { (await act(Api.r('DELETE', '/requests', { USER: { CI: Number(s.CI) } }))).ok && show(); }), 'danger')]));
  fTipo.onchange = () => show(); fCi.oninput = draw;
  async function show() {
    Loading(out);
    const r = await Api.r('GET', '/requests', fTipo.value ? { TYPEUSER: fTipo.value } : {});
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    all = r.list; draw();
  }
  return { show };
};

V.logs = root => {
  const out = h('div'); let tab = 'users'; let all = [];
  const fTxt = h('input', { ph: 'f.texto' });
  const tabs = h('div', { class: 'subtabs' }, [['users', 'logs.users'], ['sql', 'logs.sql']].map(([k, l]) => h('button', { type: 'button', class: 'tab' + (k === tab ? ' active' : ''), 'data-k': k, onclick: ev => { tab = k; tabs.querySelectorAll('.tab').forEach(b => b.classList.toggle('active', b === ev.currentTarget)); show(); } }, t(l))));
  root.append(head('tab.logs'), tabs, h('div', { class: 'filters' }, filt('f.texto', fTxt)), out);
  const draw = () => {
    const q = fTxt.value.toLowerCase(), f = all.filter(l => JSON.stringify(l).toLowerCase().includes(q));
    out.replaceChildren(tab === 'users'
      ? Tbl.build([['k.FECHA', l => l.FECHA], ['k.CI', l => l.CEDULA_USUARIO ?? l.CI], ['k.TYPELOG', l => l.TYPELOG ?? l.TIPO_LOG], ['k.TEXTO', l => l.TEXTO]], f)
      : Tbl.build([['k.FECHA', l => l.FECHA], ['k.TIPO_MODELO', l => l.TIPO_MODELO], ['k.TEXTO', l => h('div', {}, l.TEXTO, (l.query || l.parametros) && h('details', {}, h('summary', { i18n: 'detalle' }), h('pre', { text: [l.query, l.parametros].filter(Boolean).join('\n'), style: 'white-space:pre-wrap;margin:.4rem 0' })))]], f));
  };
  fTxt.oninput = draw;
  async function show() {
    Loading(out);
    const r = await Api.r('GET', tab === 'users' ? '/logs/users' : '/logs/sql');
    if (!r.ok) return out.replaceChildren(h('div', { class: 'msg error', text: r.msg }));
    all = r.list; draw();
  }
  return { show };
};

/* ===================== Mapa ===================== */
V.mapa = root => {
  const selRuta = h('select'); const box = h('div', { class: 'map-box' }); const note = h('div', { class: 'msg hidden' });
  const seeCentros = is('O', 'AO', 'AP');
  let map, markers = [], line, info, conts = [], centros = [], ready = false;
  root.append(head('map.title'), h('div', { class: 'filters' }, filt('map.ruta', selRuta)), note, box,
    h('div', { class: 'legend' }, [['#5f9a22', 'lg.ok'], ['#d99a1b', 'lg.mid'], ['#c62f3a', 'lg.high'], ['#7a1620', 'lg.over'], ['#7b8680', 'lg.off']].map(([c, k]) => h('span', {}, h('i', { style: 'background:' + c }), t(k))), seeCentros && h('span', {}, h('i', { style: 'background:#2f6f2a;border-radius:2px' }), t('lg.centro'))));
  const mk = (p, color, sq, title, content) => {
    const m = new google.maps.Marker({ map, position: p, title, icon: { path: sq ? 'M-8,-8 h16 v16 h-16 z' : google.maps.SymbolPath.CIRCLE, scale: sq ? 1 : 9, fillColor: color, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 } });
    m.addListener('click', () => { info.setContent(content); info.open(map, m); });
    markers.push(m); return m;
  };
  const cardCont = c => h('div', { style: 'min-width:12rem' }, h('strong', { text: `${t('k.CONTENEDOR')} #${c.ID}` }), h('div', { text: c.UBICACION }), tags(c.TIPO_BASURAS), h('div', { text: `${t('k.LLENADO')}: ${c.LLENADO ?? 0}/11` }), btn('a.detalle', () => Cont.detalle(c.ID), 'btn sm'));
  async function draw() {
    markers.forEach(m => m.setMap(null)); markers = [];
    const b = new google.maps.LatLngBounds();
    for (const c of conts) { const p = Loc.parse(c.UBICACION); if (!p) continue; b.extend(p); mk(p, c.EN_FUNCION == null || yes(c.EN_FUNCION) ? Fill(c.LLENADO) : '#7b8680', false, '#' + c.ID, cardCont(c)); }
    for (const c of centros) { const p = Loc.parse(c.UBICACION); if (!p) continue; b.extend(p); mk(p, '#2f6f2a', true, `${t('k.CENTRO')} #${c.ID}`, h('div', {}, h('strong', { text: `${t('k.CENTRO')} #${c.ID}` }), h('div', { text: c.UBICACION }))); }
    if (!b.isEmpty() && !selRuta.value) map.fitBounds(b);
  }
  async function ruta(id) {
    if (line) { line.setMap(null); line = null; }
    if (!id) return;
    const r = await Api.r('GET', '/ruta/data', { RUTA: { ID: Number(id) } });
    if (!r.ok) return toast(r.msg, 'error');
    const ru = r.d.RUTA || r.d, pts = [];
    const at = (list, i) => { const x = list.find(c => String(c.ID) === String(i)); return x && Loc.parse(x.UBICACION); };
    const a = at(centros, ru.ID_CENTRO_INICIO); a && pts.push(a);
    arr(ru.CONTENEDORES).forEach(x => { const p = at(conts, typeof x === 'object' ? (x.ID_CONTENEDOR ?? x.ID) : x); p && pts.push(p); });
    const z = at(centros, ru.ID_CENTRO_FINAL); z && pts.push(z);
    if (pts.length < 2) return toast(t('map.noroute'));
    line = new google.maps.Polyline({ map, path: pts, strokeColor: '#2f6f2a', strokeOpacity: .9, strokeWeight: 4 });
    const b = new google.maps.LatLngBounds(); pts.forEach(p => b.extend(p)); map.fitBounds(b);
  }
  selRuta.onchange = () => ruta(selRuta.value);
  async function show(arg) {
    note.classList.add('hidden');
    if (!CONFIG.MAPS_KEY) { note.textContent = t('map.nokey'); note.className = 'msg error'; }
    try { await Maps.load(); } catch { note.textContent = t('map.err'); note.className = 'msg error'; return; }
    if (!map) { map = new google.maps.Map(box, { center: CONFIG.CENTER, zoom: CONFIG.ZOOM, streetViewControl: false, mapTypeControl: false }); info = new google.maps.InfoWindow(); }
    const [c, ce, ru] = await Promise.all([rows('/contenedor'), seeCentros ? rows('/centro') : [], rows('/ruta')]);
    conts = c; centros = ce;
    const cur = (arg && arg.ruta) || selRuta.value;
    selRuta.replaceChildren(...[['', t('none')], ...ru.map(x => [x.ID, `#${x.ID} (${x.CANTIDAD_CONTENEDORES ?? 0})`])].map(([v, l]) => opt(v, l, String(v) === String(cur))));
    await draw(); await ruta(selRuta.value);
  }
  return { show };
};
