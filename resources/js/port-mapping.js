/**
 * resources/js/port-mapping.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Módulo ES — Mapeo de puertos para planificación de migración de switches.
 * Basado en el prototipo validado (mapeo-puertos-switch.html).
 *
 * Cambios respecto al prototipo:
 *   • alert()   → showToast()  (toast ligero, sin dependencia de Flowbite)
 *   • confirm() → await showConfirm()  (modal Promise-based, no bloqueante)
 *   • Guardar en servidor vía fetch POST/PUT con CSRF token
 *   • Botones JSON exportar/importar se conservan como respaldo
 *   • Hidratación desde window.__PORT_MAPPING__ (vista show)
 *   • Notificación de guardado con toast de éxito/error
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Paleta de colores (idéntica al prototipo) ─────────────────────────────────
const COLORS = {
  unset:      { bg: '#e8e9eb', bd: '#c4c7cc', label: 'Sin definir'   },
  active:     { bg: '#b5dd8f', bd: '#7fb84e', label: 'Activo'        },
  nolink:     { bg: '#fbfbfb', bd: '#b7bbc2', label: 'Sin link'      },
  disabled:   { bg: '#f5b5b5', bd: '#d97070', label: 'Deshabilitado' },
  reassigned: { bg: '#ffd98e', bd: '#e0a83c', label: 'Re-asignado'   },
};

// ── Estado global ─────────────────────────────────────────────────────────────
let state        = null;
let mappingFrom  = null;
let selectedJack = null;

// Referencias al DOM (inicializadas en init())
let popover, popBody, popInfo;

// ─────────────────────────────────────────────────────────────────────────────
// TOAST — reemplaza alert()
// ─────────────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
  const palette = {
    success: { bg: '#dcfce7', border: '#86efac', text: '#166534', icon: '✓' },
    error:   { bg: '#fee2e2', border: '#fca5a5', text: '#991b1b', icon: '✕' },
    warning: { bg: '#fef9c3', border: '#fde047', text: '#854d0e', icon: '⚠' },
    info:    { bg: '#dbeafe', border: '#93c5fd', text: '#1e3a8a', icon: 'ℹ' },
  };
  const p = palette[type] ?? palette.info;
  const t = document.createElement('div');
  t.style.cssText = [
    'position:fixed', 'top:20px', 'right:20px', 'z-index:9999',
    'display:flex', 'align-items:center', 'gap:10px',
    'padding:12px 18px', 'border-radius:10px', 'border:1px solid',
    'font-size:13px', 'font-family:inherit', 'font-weight:500',
    'box-shadow:0 4px 16px rgba(0,0,0,.12)',
    'transition:opacity .3s ease',
    `background:${p.bg}`, `border-color:${p.border}`, `color:${p.text}`,
  ].join(';');
  t.innerHTML = `<span style="font-weight:700">${p.icon}</span><span>${msg}</span>`;
  document.body.appendChild(t);
  setTimeout(() => {
    t.style.opacity = '0';
    setTimeout(() => t.remove(), 350);
  }, 3500);
}

// ─────────────────────────────────────────────────────────────────────────────
// CONFIRM MODAL — reemplaza confirm() (Promise-based, no bloqueante)
// El modal HTML está definido en tool.blade.php (@push('modals')).
// ─────────────────────────────────────────────────────────────────────────────
let _confirmResolve = null;

function showConfirm(msg) {
  return new Promise(resolve => {
    _confirmResolve = resolve;
    const el = document.getElementById('pm-confirm-msg');
    if (el) el.textContent = msg;
    document.getElementById('pm-confirm-modal')?.classList.remove('hidden');
  });
}

function _bindConfirmButtons() {
  const modal = document.getElementById('pm-confirm-modal');
  document.getElementById('pm-confirm-ok')?.addEventListener('click', () => {
    modal?.classList.add('hidden');
    _confirmResolve?.(true);
    _confirmResolve = null;
  });
  document.getElementById('pm-confirm-cancel')?.addEventListener('click', () => {
    modal?.classList.add('hidden');
    _confirmResolve?.(false);
    _confirmResolve = null;
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// ESTADO — estructuras de datos (idéntico al prototipo)
// ─────────────────────────────────────────────────────────────────────────────
function mkPorts(copper, fiber) {
  const arr = [];
  for (let i = 1; i <= copper; i++)
    arr.push({ local: i, kind: 'cu',  state: 'unset', mapTo: null, mapFrom: null, auto: false });
  for (let i = copper + 1; i <= copper + fiber; i++)
    arr.push({ local: i, kind: 'sfp', state: 'unset', mapTo: null, mapFrom: null, auto: false });
  return arr;
}

function newState(originType, originFiber, destCopper, destFiber) {
  const units        = originType === '2x24' ? 2 : 1;
  const copperPerUnit = originType === '2x24' ? 24 : parseInt(originType);
  return {
    ip: '',
    origin: {
      type: originType, units, copperPerUnit, fiber: parseInt(originFiber),
      model: '', serials: ['', ''],
      ports: Array.from({ length: units }, () => mkPorts(copperPerUnit, parseInt(originFiber))),
    },
    dest: {
      copper: parseInt(destCopper), fiber: parseInt(destFiber),
      model: '', serial: '',
      ports: mkPorts(parseInt(destCopper), parseInt(destFiber)),
    },
  };
}

/** Etiqueta del puerto origen: "10" (single) o "2:10" (stack) */
function oLabel(unit, local) {
  return state.origin.units > 1 ? (unit + 1) + ':' + local : String(local);
}

// ─────────────────────────────────────────────────────────────────────────────
// RENDER (idéntico al prototipo validado)
// ─────────────────────────────────────────────────────────────────────────────
function renderAll() {
  renderOrigin();
  renderDest();
  updateTitles();
  requestAnimationFrame(drawLines);
}

function updateTitles() {
  const o = state.origin, d = state.dest;
  document.querySelectorAll('.origin-sub').forEach((el, u) => {
    el.textContent = [o.model, o.serials[u] ? 'S/N ' + o.serials[u] : '', state.ip ? 'IP ' + state.ip : '']
      .filter(Boolean).join('  ·  ');
  });
  document.getElementById('titleDest').textContent =
    'SWITCH DESTINO (' + d.copper + (d.fiber ? ' + ' + d.fiber + ' SFP' : '') + ')';
  document.getElementById('subDest').textContent =
    [d.model, d.serial ? 'S/N ' + d.serial : '', state.ip ? 'IP ' + state.ip : '']
      .filter(Boolean).join('  ·  ');
}

function renderOrigin() {
  const row = document.getElementById('originRow');
  row.innerHTML = '';
  const o = state.origin;
  for (let u = 0; u < o.units; u++) {
    const panel = document.createElement('div');
    panel.className = 'panel';
    panel.id = 'panel-o-' + u;
    const title = 'SWITCH ORIGEN' + (o.units > 1 ? ' ' + (u + 1) : '') +
      ' (' + o.copperPerUnit + (o.fiber ? ' + ' + o.fiber + ' SFP' : '') + ')';
    panel.innerHTML =
      '<div class="panel-title">' + title + '</div>' +
      '<div class="panel-sub origin-sub"></div>';
    const area = document.createElement('div');
    area.className = 'ports-area';
    buildPortsArea(area, o.ports[u], 'o', u);
    panel.appendChild(area);
    // Nota de bloque (solo en stacks): rango del destino y aviso de orden
    if (o.units > 1) {
      const start = u * o.copperPerUnit + 1, end = start + o.copperPerUnit - 1;
      const re = o.ports[u].filter(p => p.state === 'reassigned').length;
      const note = document.createElement('div');
      note.className = 'block-note';
      note.textContent = '→ Destino ' + start + '–' + end +
        ' · el orden se mantiene' +
        (re ? ' (excepto ' + re + ' re-asignado' + (re > 1 ? 's' : '') + ')' : '');
      panel.appendChild(note);
    }
    row.appendChild(panel);
  }
}

function renderDest() {
  const area = document.getElementById('areaDest');
  area.innerHTML = '';
  buildPortsArea(area, state.dest.ports, 'd', null);
}

function buildPortsArea(area, ports, side, unit) {
  const cu = ports.filter(p => p.kind === 'cu');
  const fi = ports.filter(p => p.kind === 'sfp');
  const BLOCK_COLS = 8;
  let block = null;
  for (let c = 0; c < cu.length / 2; c++) {
    if (c % BLOCK_COLS === 0) {
      block = document.createElement('div');
      block.className = 'block';
      area.appendChild(block);
    }
    block.appendChild(makeCol(cu[c * 2], cu[c * 2 + 1], side, unit));
  }
  if (fi.length) {
    const sec = document.createElement('div');
    sec.className = 'fiber-sec';
    sec.innerHTML = '<span class="fiber-tag">SFP</span>';
    for (let c = 0; c < fi.length / 2; c++)
      sec.appendChild(makeCol(fi[c * 2], fi[c * 2 + 1], side, unit));
    area.appendChild(sec);
  }
}

function makeCol(odd, even, side, unit) {
  const col = document.createElement('div');
  col.className = 'col';
  col.appendChild(makeNum(odd.local));
  col.appendChild(makeJack(odd, side, unit, false));
  if (even) {
    col.appendChild(makeJack(even, side, unit, true));
    col.appendChild(makeNum(even.local));
  }
  return col;
}

function makeNum(n) {
  const el = document.createElement('div');
  el.className = 'num';
  el.textContent = n;
  return el;
}

function makeJack(p, side, unit, flip) {
  const el = document.createElement('div');
  el.className = 'jack ' + p.state +
    (flip        ? ' flip' : '') +
    (p.kind === 'sfp' ? ' sfp'  : '');
  el.id = side === 'o'
    ? 'jack-o-' + unit + '-' + p.local
    : 'jack-d-'         + p.local;
  if (p.state === 'reassigned') {
    el.textContent = side === 'o'
      ? '→' + p.mapTo
      : oLabel(p.mapFrom.unit, p.mapFrom.local);
  }
  // Candidato válido para re-asignación
  if (mappingFrom && side === 'd' && p.state === 'unset' && p.kind === mappingFrom.kind)
    el.classList.add('candidate');

  el.addEventListener('click', e => { e.stopPropagation(); onJackClick(el, p, side, unit); });
  el.addEventListener('mouseenter', () => highlightLine(side, unit, p, true));
  el.addEventListener('mouseleave',  () => highlightLine(side, unit, p, false));
  return el;
}

// ─────────────────────────────────────────────────────────────────────────────
// INTERACCIÓN (lógica idéntica al prototipo)
// ─────────────────────────────────────────────────────────────────────────────
function onJackClick(el, p, side, unit) {
  if (mappingFrom) {
    if (side !== 'd') return;
    if (p.kind !== mappingFrom.kind) {
      showToast('El puerto debe ser del mismo tipo (' +
        (mappingFrom.kind === 'cu' ? 'cobre' : 'fibra SFP') + ').', 'warning');
      return;
    }
    if (p.state !== 'unset') {
      showToast('Ese puerto destino ya está ocupado. Elige uno libre.', 'warning');
      return;
    }
    const arr = state.origin.ports[mappingFrom.unit];
    const o   = arr[idxOf(arr, mappingFrom.local)];
    clearAutoMirror(mappingFrom.unit, o.local);
    o.state = 'reassigned'; o.mapTo   = p.local;
    p.state = 'reassigned'; p.mapFrom = { unit: mappingFrom.unit, local: o.local };
    exitMappingMode();
    renderAll();
    return;
  }
  openPopover(el, p, side, unit);
}

function idxOf(arr, local) { return arr.findIndex(x => x.local === local); }

function setPortState(p, side, unit, newSt) {
  if (p.state === 'reassigned') removeMapping(p, side);
  if (side === 'o') clearAutoMirror(unit, p.local);
  p.state = newSt;
  // Espejo automático: origen activo (cobre) → mismo puerto en destino
  if (side === 'o' && newSt === 'active' && p.kind === 'cu') {
    const destLocal = unit * state.origin.copperPerUnit + p.local;
    const d = state.dest.ports[idxOf(state.dest.ports, destLocal)];
    if (d && d.kind === 'cu' && d.state === 'unset') { d.state = 'active'; d.auto = true; }
  }
  closePopover();
  renderAll();
}

function clearAutoMirror(unit, local) {
  const destLocal = unit * state.origin.copperPerUnit + local;
  const d = state.dest.ports[idxOf(state.dest.ports, destLocal)];
  if (d && d.auto) { d.state = 'unset'; d.auto = false; }
}

function removeMapping(p, side) {
  if (side === 'o' && p.mapTo != null) {
    const d = state.dest.ports[idxOf(state.dest.ports, p.mapTo)];
    if (d) { d.state = 'unset'; d.mapFrom = null; }
    p.mapTo = null;
  }
  if (side === 'd' && p.mapFrom) {
    const arr = state.origin.ports[p.mapFrom.unit];
    const o   = arr[idxOf(arr, p.mapFrom.local)];
    if (o) { o.state = 'unset'; o.mapTo = null; }
    p.mapFrom = null;
  }
}

function enterMappingMode(unit, p) {
  mappingFrom = { unit, local: p.local, kind: p.kind };
  document.getElementById('mapBanner').classList.add('visible');
  document.getElementById('mapBannerText').innerHTML =
    'Re-asignando el puerto <b>' + oLabel(unit, p.local) + '</b> (' +
    (p.kind === 'cu' ? 'cobre' : 'fibra SFP') +
    ') — haz clic en el puerto <b>destino</b>…';
  document.getElementById('panelDest').classList.add('map-target');
  closePopover();
  renderAll();
}

function exitMappingMode() {
  mappingFrom = null;
  document.getElementById('mapBanner').classList.remove('visible');
  document.getElementById('panelDest').classList.remove('map-target');
}

// ─────────────────────────────────────────────────────────────────────────────
// POPOVER (JS vanilla — no migrado a Flowbite, tal como se solicitó)
// ─────────────────────────────────────────────────────────────────────────────
function openPopover(jackEl, p, side, unit) {
  if (selectedJack) selectedJack.classList.remove('selected');
  selectedJack = jackEl;
  jackEl.classList.add('selected');

  document.getElementById('popTitle').textContent =
    'Puerto ' + (side === 'o' ? oLabel(unit, p.local) : p.local) +
    (p.kind === 'sfp' ? ' (SFP)' : '') +
    ' — ' + (side === 'o' ? 'Origen' : 'Destino');

  popBody.innerHTML = '';
  const addBtn = (txt, colorKey, fn) => {
    const b = document.createElement('button');
    b.className = 'state-btn';
    const c = COLORS[colorKey];
    b.innerHTML = `<span class="dot" style="background:${c.bg};border-color:${c.bd}"></span>${txt}`;
    b.addEventListener('click', fn);
    popBody.appendChild(b);
  };

  addBtn('Activo',        'active',     () => setPortState(p, side, unit, 'active'));
  addBtn('Sin link',      'nolink',     () => setPortState(p, side, unit, 'nolink'));
  addBtn('Deshabilitado', 'disabled',   () => setPortState(p, side, unit, 'disabled'));
  if (side === 'o')
    addBtn('Re-asignar a…', 'reassigned', () => enterMappingMode(unit, p));
  if (p.state === 'reassigned')
    addBtn('Quitar re-asignación', 'unset', () => setPortState(p, side, unit, 'unset'));
  else if (p.state !== 'unset')
    addBtn('Limpiar estado', 'unset',       () => setPortState(p, side, unit, 'unset'));

  popInfo.textContent = p.state === 'reassigned'
    ? (side === 'o'
        ? 'Mapeado al puerto ' + p.mapTo + ' del destino'
        : 'Recibe el puerto ' + oLabel(p.mapFrom.unit, p.mapFrom.local) + ' del origen')
    : 'Estado actual: ' + COLORS[p.state].label +
      (side === 'o' && p.state === 'active' && p.kind === 'cu'
        ? ' (se refleja automáticamente en el destino)' : '');

  // Posicionamiento relativo al .pm-app (igual que en el prototipo)
  const wrap     = document.getElementById('pm-app');
  const wrapRect = wrap.getBoundingClientRect();
  const jr       = jackEl.getBoundingClientRect();
  let left = jr.left - wrapRect.left + jr.width / 2 - 117;
  let top  = jr.bottom - wrapRect.top + 10;
  left = Math.max(8, Math.min(left, wrap.clientWidth - 245));
  popover.style.left = left + 'px';
  popover.style.top  = top  + 'px';
  popover.classList.add('visible');
}

function closePopover() {
  popover.classList.remove('visible');
  if (selectedJack) { selectedJack.classList.remove('selected'); selectedJack = null; }
}

// ─────────────────────────────────────────────────────────────────────────────
// SVG DE LÍNEAS (idéntico al prototipo)
// ─────────────────────────────────────────────────────────────────────────────
function drawLines() {
  const svg  = document.getElementById('linesSvg');
  const wrap = document.getElementById('stackWrap');
  svg.setAttribute('width',  wrap.clientWidth);
  svg.setAttribute('height', wrap.clientHeight);
  svg.innerHTML = '';
  const wr = wrap.getBoundingClientRect();

  // Bandas verdes translúcidas de bloque (stack): una por unidad
  if (state.origin.units > 1) {
    const destTop = document.getElementById('panelDest').getBoundingClientRect().top - wr.top;
    for (let u = 0; u < state.origin.units; u++) {
      const start   = u * state.origin.copperPerUnit + 1;
      const end     = start + state.origin.copperPerUnit - 1;
      const panelEl = document.getElementById('panel-o-' + u);
      const j1 = document.getElementById('jack-d-' + start);
      const j2 = document.getElementById('jack-d-' + end);
      if (!panelEl || !j1 || !j2) continue;
      const pr = panelEl.getBoundingClientRect();
      const r1 = j1.getBoundingClientRect(), r2 = j2.getBoundingClientRect();
      const x1  = pr.left - wr.left + pr.width / 2;
      const y1  = pr.bottom - wr.top;
      const x2  = (r1.left + r2.left) / 2 - wr.left + r1.width / 2;
      const midY = (y1 + destTop) / 2;
      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${destTop}`);
      path.classList.add('band');
      svg.appendChild(path);
    }
  }

  // Líneas ámbar de re-asignaciones individuales
  state.origin.ports.forEach((unitPorts, u) => {
    unitPorts.forEach(o => {
      if (o.state !== 'reassigned' || o.mapTo == null) return;
      const a = document.getElementById('jack-o-' + u + '-' + o.local);
      const b = document.getElementById('jack-d-' + o.mapTo);
      if (!a || !b) return;
      const ar = a.getBoundingClientRect(), br = b.getBoundingClientRect();
      const x1 = ar.left - wr.left + ar.width / 2,  y1 = ar.bottom - wr.top;
      const x2 = br.left - wr.left + br.width / 2,  y2 = br.top    - wr.top;
      const midY = (y1 + y2) / 2;
      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`);
      path.dataset.pair = u + ':' + o.local + '-' + o.mapTo;
      svg.appendChild(path);
    });
  });
}

function highlightLine(side, unit, p, on) {
  let pair = null;
  if (side === 'o' && p.mapTo  != null) pair = unit + ':' + p.local + '-' + p.mapTo;
  if (side === 'd' && p.mapFrom)         pair = p.mapFrom.unit + ':' + p.mapFrom.local + '-' + p.local;
  if (!pair) return;
  document.querySelectorAll('#linesSvg path').forEach(path => {
    if (path.dataset.pair === pair) path.classList.toggle('hl', on);
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// CONFIGURACIÓN — botón Aplicar (async por los await showConfirm())
// ─────────────────────────────────────────────────────────────────────────────
function _bindApply() {
  document.getElementById('btnApply')?.addEventListener('click', async () => {
    const oType   = document.getElementById('selOriginType').value;
    const oFiber  = parseInt(document.getElementById('selOriginFiber').value);
    const dCopper = parseInt(document.getElementById('selDestType').value);
    const dFiber  = parseInt(document.getElementById('selDestFiber').value);

    const oUnits       = oType === '2x24' ? 2 : 1;
    const oCopperTotal = (oType === '2x24' ? 24 : parseInt(oType)) * oUnits;
    const oFiberTotal  = oFiber * oUnits;

    // Validación bloqueante: cobre insuficiente
    if (dCopper < oCopperTotal) {
      showToast(
        'El destino debe tener igual o más puertos de cobre que el origen (' +
        oCopperTotal + ' vs ' + dCopper + ').',
        'error'
      );
      return;
    }

    // Advertencia no bloqueante: fibra insuficiente
    if (dFiber < oFiberTotal) {
      const ok = await showConfirm(
        'El destino tiene menos puertos de fibra (' + dFiber + ') que el origen (' +
        oFiberTotal + ').\nAlgunas fibras no podrán mapearse 1 a 1. ¿Continuar?'
      );
      if (!ok) return;
    }

    // Confirmación de reinicio si ya hay un mapeo activo
    if (state) {
      const ok = await showConfirm('Esto reinicia el mapeo actual. ¿Continuar?');
      if (!ok) return;
    }

    const meta = grabMeta();
    exitMappingMode();
    state = newState(oType, oFiber, dCopper, dFiber);
    applyMeta(meta);
    syncSerial2Visibility();
    renderAll();
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// METADATOS (idéntico al prototipo)
// ─────────────────────────────────────────────────────────────────────────────
function grabMeta() {
  return {
    ip:  document.getElementById('inpIp').value,
    om:  document.getElementById('inpOriginModel').value,
    os1: document.getElementById('inpOriginSerial1').value,
    os2: document.getElementById('inpOriginSerial2').value,
    dm:  document.getElementById('inpDestModel').value,
    ds:  document.getElementById('inpDestSerial').value,
  };
}

function applyMeta(m) {
  if (!state) return;
  state.ip             = m.ip;
  state.origin.model   = m.om;
  state.origin.serials = [m.os1, m.os2];
  state.dest.model     = m.dm;
  state.dest.serial    = m.ds;
}

function syncSerial2Visibility() {
  const show = state.origin.units > 1;
  document.getElementById('lblSerial1').textContent    = show ? 'Serial 1:' : 'Serial:';
  document.getElementById('lblSerial2').style.display  = show ? '' : 'none';
  document.getElementById('inpOriginSerial2').style.display = show ? '' : 'none';
}

function _bindMetaInputs() {
  ['inpIp','inpOriginModel','inpOriginSerial1','inpOriginSerial2','inpDestModel','inpDestSerial']
    .forEach(id => document.getElementById(id)?.addEventListener('input', () => {
      if (!state) return;
      applyMeta(grabMeta());
      updateTitles();
    }));
}

// ─────────────────────────────────────────────────────────────────────────────
// GUARDAR EN SERVIDOR (nuevo en la integración Laravel)
// ─────────────────────────────────────────────────────────────────────────────
async function saveToServer() {
  if (!state) { showToast('No hay mapeo activo para guardar.', 'warning'); return; }
  applyMeta(grabMeta());

  const nameEl = document.getElementById('inpMappingName');
  const name   = nameEl?.value.trim() ?? '';
  if (!name) {
    showToast('Escribe un nombre para el mapeo antes de guardar.', 'warning');
    nameEl?.focus();
    return;
  }

  const payload = {
    name,
    ip:            state.ip,
    origin_config: {
      type:    state.origin.type,
      fiber:   state.origin.fiber,
      model:   state.origin.model,
      serials: state.origin.serials,
    },
    dest_config: {
      copper: state.dest.copper,
      fiber:  state.dest.fiber,
      model:  state.dest.model,
      serial: state.dest.serial,
    },
    mapping_state: state,
  };

  const mappingId = window.__PORT_MAPPING_ID__;
  const url    = mappingId
    ? `/admin/port-mapping/${mappingId}`
    : '/admin/port-mapping';
  const method = mappingId ? 'PUT' : 'POST';

  try {
    const resp = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        'Accept':       'application/json',
      },
      body: JSON.stringify(payload),
    });

    if (!resp.ok) {
      const err = await resp.json().catch(() => ({}));
      showToast(err.message ?? 'Error al guardar el mapeo.', 'error');
      return;
    }

    const result = await resp.json();
    if (!mappingId) {
      window.__PORT_MAPPING_ID__ = result.id;
      // Actualizar URL sin recargar la página
      history.replaceState({}, '', '/admin/port-mapping/' + result.id);
    }
    showToast('Mapeo guardado correctamente.', 'success');

  } catch {
    showToast('Error de conexión al guardar. Verifica tu red.', 'error');
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// JSON — EXPORTAR / IMPORTAR (botones de respaldo, idéntico al prototipo)
// ─────────────────────────────────────────────────────────────────────────────
function triggerDownload(url, name) {
  const a = document.createElement('a');
  a.href = url; a.download = name;
  document.body.appendChild(a); a.click(); a.remove();
}

function _bindJsonButtons() {
  // Exportar JSON
  document.getElementById('btnSave')?.addEventListener('click', () => {
    if (!state) return;
    const blob = new Blob([JSON.stringify(state, null, 2)], { type: 'application/json' });
    triggerDownload(URL.createObjectURL(blob), 'mapeo-puertos.json');
  });

  // Importar JSON
  document.getElementById('btnLoad')?.addEventListener('click', () =>
    document.getElementById('fileLoad')?.click()
  );
  document.getElementById('fileLoad')?.addEventListener('change', e => {
    const f = e.target.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = () => {
      try {
        const s = JSON.parse(r.result);
        if (!s.origin || !s.dest || !s.origin.ports) throw new Error('formato');
        state = s;
        document.getElementById('selOriginType').value        = s.origin.type;
        document.getElementById('selOriginFiber').value       = s.origin.fiber;
        document.getElementById('selDestType').value          = s.dest.copper;
        document.getElementById('selDestFiber').value         = s.dest.fiber;
        document.getElementById('inpIp').value                = s.ip ?? '';
        document.getElementById('inpOriginModel').value       = s.origin.model ?? '';
        document.getElementById('inpOriginSerial1').value     = s.origin.serials?.[0] ?? '';
        document.getElementById('inpOriginSerial2').value     = s.origin.serials?.[1] ?? '';
        document.getElementById('inpDestModel').value         = s.dest.model ?? '';
        document.getElementById('inpDestSerial').value        = s.dest.serial ?? '';
        exitMappingMode();
        syncSerial2Visibility();
        renderAll();
        showToast('Mapeo cargado desde archivo JSON.', 'success');
      } catch {
        showToast('El archivo no es un JSON de mapeo válido.', 'error');
      }
    };
    r.readAsText(f);
    e.target.value = '';
  });

  // Guardar en servidor
  document.getElementById('btnServerSave')?.addEventListener('click', saveToServer);

  // Cancelar modo mapeo
  document.getElementById('btnCancelMap')?.addEventListener('click', () => {
    exitMappingMode(); renderAll();
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// EXPORTAR PNG (función íntegra del prototipo, sin cambios)
// ─────────────────────────────────────────────────────────────────────────────
const JW = 34, JH = 32, SFPH = 22, GAP = 8, BLOCK_GAP = 22, FIBER_GAP = 18, NUMH = 16, PPAD = 20;

function panelDims(copper, fiber) {
  const cols   = copper / 2;
  const blocks = Math.ceil(cols / 8);
  let w = cols * (JW + GAP) - GAP + (blocks - 1) * BLOCK_GAP;
  if (fiber) w += FIBER_GAP + (fiber / 2) * (JW + GAP) - GAP;
  return { w: w + PPAD * 2, h: NUMH * 2 + JH * 2 + 6 + PPAD * 2 + 30 };
}

function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

function exportPNG() {
  const SCALE = 2, MARGIN = 40, UNIT_GAP = 24, LINES_H = 110;
  const o = state.origin, d = state.dest;
  const oDim = panelDims(o.copperPerUnit, o.fiber);
  const dDim = panelDims(d.copper, d.fiber);
  const originRowW = oDim.w * o.units + UNIT_GAP * (o.units - 1);
  const W = Math.max(originRowW, dDim.w) + MARGIN * 2;

  const maps = [];
  o.ports.forEach((up, u) => up.forEach(p => {
    if (p.state === 'reassigned' && p.mapTo != null) maps.push({ u, local: p.local, to: p.mapTo });
  }));
  const blockNotes = [];
  if (o.units > 1) {
    for (let u = 0; u < o.units; u++) {
      const start = u * o.copperPerUnit + 1, end = start + o.copperPerUnit - 1;
      blockNotes.push('Switch ' + (u + 1) + ': puertos 1–' + o.copperPerUnit +
        ' → ' + start + '–' + end + ' del destino (el orden se mantiene)');
    }
  }
  const listH = (maps.length || blockNotes.length)
    ? 55 + blockNotes.length * 20 + (maps.length ? 24 + Math.ceil(maps.length / 4) * 22 : 0) : 0;
  const headerH = 95;
  const H = headerH + oDim.h + LINES_H + dDim.h + 60 + listH + MARGIN;

  const cv = document.createElement('canvas');
  cv.width = W * SCALE; cv.height = H * SCALE;
  const ctx = cv.getContext('2d');
  ctx.scale(SCALE, SCALE);
  ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, W, H);

  // Encabezado
  ctx.fillStyle = '#222'; ctx.font = 'bold 18px Arial';
  ctx.fillText('Mapeo de puertos — Switch origen → destino', MARGIN, 34);
  ctx.font = '12px Arial'; ctx.fillStyle = '#666';
  const meta1 = ['Generado: ' + new Date().toLocaleString(),
                  state.ip ? 'IP (se conserva): ' + state.ip : ''].filter(Boolean).join('   |   ');
  const oSer  = o.units > 1
    ? 'S/N: ' + (o.serials[0]||'—') + ' / ' + (o.serials[1]||'—')
    : 'S/N: ' + (o.serials[0]||'—');
  ctx.fillText(meta1, MARGIN, 56);
  ctx.fillText('Origen: '+(o.model||'—')+'  ('+oSer+')     Destino: '+(d.model||'—')+'  (S/N: '+(d.serial||'—')+')', MARGIN, 74);

  const portPos = {};

  function drawPanel(ports, label, x0, y0, dims, kp) {
    ctx.strokeStyle='#e0e2e6'; ctx.fillStyle='#fafafa'; ctx.lineWidth=1;
    roundRect(ctx,x0,y0,dims.w,dims.h,8); ctx.fill(); ctx.stroke();
    ctx.fillStyle='#222'; ctx.font='bold 12px Arial'; ctx.fillText(label,x0+PPAD,y0+20);
    const cu=ports.filter(p=>p.kind==='cu'), fi=ports.filter(p=>p.kind==='sfp');
    const yTop=y0+34+NUMH, yBot=yTop+JH+6; let x=x0+PPAD;
    for(let c=0;c<cu.length/2;c++){
      if(c>0&&c%8===0)x+=BLOCK_GAP;
      drawPair(cu[c*2],cu[c*2+1],x,yTop,yBot,kp); x+=JW+GAP;
    }
    if(fi.length){
      x+=FIBER_GAP-GAP; ctx.strokeStyle='#e0e2e6';
      ctx.beginPath(); ctx.moveTo(x-FIBER_GAP/2,y0+30); ctx.lineTo(x-FIBER_GAP/2,y0+dims.h-14); ctx.stroke();
      for(let c=0;c<fi.length/2;c++){ drawPair(fi[c*2],fi[c*2+1],x,yTop,yBot,kp); x+=JW+GAP; }
    }
  }
  function drawPair(odd,even,x,yTop,yBot,kp){
    ctx.font='10px Arial'; ctx.fillStyle='#444'; ctx.textAlign='center';
    ctx.fillText(odd.local,x+JW/2,yTop-5); drawJack(odd,x,yTop,kp);
    if(even){ drawJack(even,x,yBot,kp); ctx.fillStyle='#444'; ctx.fillText(even.local,x+JW/2,yBot+JH+12); }
    ctx.textAlign='left';
    portPos[kp+odd.local]={x,y:yTop}; if(even)portPos[kp+even.local]={x,y:yBot};
  }
  function drawJack(p,x,y,kp){
    const c=COLORS[p.state],h=p.kind==='sfp'?SFPH:JH,yAdj=p.kind==='sfp'?y+(JH-SFPH)/2:y;
    ctx.fillStyle=c.bg; ctx.strokeStyle=c.bd; ctx.lineWidth=1.5;
    roundRect(ctx,x,yAdj,JW,h,p.kind==='sfp'?3:5); ctx.fill(); ctx.stroke();
    if(p.state==='reassigned'){
      ctx.fillStyle='#7a5410'; ctx.font='bold 9px Arial'; ctx.textAlign='center';
      ctx.fillText(kp.startsWith('o')?'→'+p.mapTo:oLabel(p.mapFrom.unit,p.mapFrom.local),x+JW/2,yAdj+h/2+3.5);
      ctx.textAlign='left';
    }
    ctx.lineWidth=1;
  }

  const yOrigin=headerH, unitX=[];
  let ox=MARGIN+(W-MARGIN*2-originRowW)/2;
  for(let u=0;u<o.units;u++){
    const lbl='SWITCH ORIGEN'+(o.units>1?' '+(u+1):'')+' ('+o.copperPerUnit+(o.fiber?' + '+o.fiber+' SFP':'')+')';
    unitX.push(ox); drawPanel(o.ports[u],lbl,ox,yOrigin,oDim,'o'+u+'-'); ox+=oDim.w+UNIT_GAP;
  }
  const yDest=yOrigin+oDim.h+LINES_H,dx=MARGIN+(W-MARGIN*2-dDim.w)/2;
  drawPanel(d.ports,'SWITCH DESTINO ('+d.copper+(d.fiber?' + '+d.fiber+' SFP':'')+')' ,dx,yDest,dDim,'d-');

  if(o.units>1){
    ctx.strokeStyle='#7fb84e'; ctx.lineWidth=12; ctx.globalAlpha=.28;
    for(let u=0;u<o.units;u++){
      const start=u*o.copperPerUnit+1,end=start+o.copperPerUnit-1;
      const p1=portPos['d-'+start],p2=portPos['d-'+end]; if(!p1||!p2)continue;
      const x1=unitX[u]+oDim.w/2,y1=yOrigin+oDim.h,x2=(p1.x+p2.x)/2+JW/2,y2=yDest,midY=(y1+y2)/2;
      ctx.beginPath(); ctx.moveTo(x1,y1); ctx.bezierCurveTo(x1,midY,x2,midY,x2,y2); ctx.stroke();
    }
    ctx.globalAlpha=1; ctx.lineWidth=1; ctx.fillStyle='#3d6320'; ctx.font='11px Arial';
    for(let u=0;u<o.units;u++){
      const start=u*o.copperPerUnit+1,end=start+o.copperPerUnit-1;
      const re=o.ports[u].filter(p=>p.state==='reassigned').length;
      ctx.fillText('→ Destino '+start+'–'+end+' · el orden se mantiene'+(re?' (excepto '+re+' re-asignado'+(re>1?'s':'')+')'  :''),unitX[u]+PPAD,yOrigin+oDim.h+16);
    }
  }
  ctx.strokeStyle='#e0a83c'; ctx.lineWidth=2; ctx.globalAlpha=.85;
  maps.forEach(m=>{
    const a=portPos['o'+m.u+'-'+m.local],b=portPos['d-'+m.to]; if(!a||!b)return;
    const x1=a.x+JW/2,y1=a.y+JH,x2=b.x+JW/2,y2=b.y,midY=(y1+y2)/2;
    ctx.beginPath(); ctx.moveTo(x1,y1); ctx.bezierCurveTo(x1,midY,x2,midY,x2,y2); ctx.stroke();
  });
  ctx.globalAlpha=1; ctx.lineWidth=1;

  let lx=MARGIN, ly=yDest+dDim.h+30; ctx.font='12px Arial';
  Object.values(COLORS).forEach(c=>{
    ctx.fillStyle=c.bg; ctx.strokeStyle=c.bd;
    roundRect(ctx,lx,ly-10,12,12,3); ctx.fill(); ctx.stroke();
    ctx.fillStyle='#444'; ctx.fillText(c.label,lx+17,ly); lx+=17+ctx.measureText(c.label).width+22;
  });

  if(maps.length||blockNotes.length){
    ly+=32; ctx.fillStyle='#222'; ctx.font='bold 13px Arial'; ctx.fillText('Resumen del mapeo:',MARGIN,ly);
    ctx.font='12px Arial'; ctx.fillStyle='#3d6320';
    blockNotes.forEach((t,i)=>ctx.fillText('• '+t,MARGIN,ly+20+i*20));
    if(maps.length){
      const baseY=ly+20+blockNotes.length*20+(blockNotes.length?6:0);
      ctx.fillStyle='#7a5410'; ctx.font='bold 12px Arial'; ctx.fillText('Re-asignaciones ('+maps.length+'):',MARGIN,baseY);
      ctx.font='12px Arial'; ctx.fillStyle='#444';
      maps.forEach((m,i)=>{
        const col=i%4,row=Math.floor(i/4);
        ctx.fillText('Puerto '+oLabel(m.u,m.local)+' → '+m.to,MARGIN+col*170,baseY+18+row*22);
      });
    }
  }
  triggerDownload(cv.toDataURL('image/png'),'mapeo-puertos.png');
}

// ─────────────────────────────────────────────────────────────────────────────
// INICIALIZACIÓN
// ─────────────────────────────────────────────────────────────────────────────
function init() {
  // Inicializar referencias DOM
  popover = document.getElementById('popover');
  popBody = document.getElementById('popBody');
  popInfo = document.getElementById('popInfo');

  // Configurar listeners
  _bindConfirmButtons();
  _bindApply();
  _bindMetaInputs();
  _bindJsonButtons();

  // Popover: cerrar con X, click fuera, Escape
  document.getElementById('popClose')?.addEventListener('click', closePopover);
  document.addEventListener('click', e => { if (!popover.contains(e.target)) closePopover(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closePopover(); if (mappingFrom) { exitMappingMode(); renderAll(); } }
  });

  // Líneas al redimensionar
  window.addEventListener('resize', drawLines);

  // Botón exportar PNG
  document.getElementById('btnPng')?.addEventListener('click', exportPNG);

  // Hidratar desde un mapeo guardado (vista show)
  if (window.__PORT_MAPPING__) {
    const pm = window.__PORT_MAPPING__;
    state = pm.mapping_state;
    window.__PORT_MAPPING_ID__ = pm.id;
    document.getElementById('selOriginType').value        = state.origin.type;
    document.getElementById('selOriginFiber').value       = state.origin.fiber;
    document.getElementById('selDestType').value          = state.dest.copper;
    document.getElementById('selDestFiber').value         = state.dest.fiber;
    document.getElementById('inpIp').value                = state.ip ?? '';
    document.getElementById('inpOriginModel').value       = state.origin.model ?? '';
    document.getElementById('inpOriginSerial1').value     = state.origin.serials?.[0] ?? '';
    document.getElementById('inpOriginSerial2').value     = state.origin.serials?.[1] ?? '';
    document.getElementById('inpDestModel').value         = state.dest.model ?? '';
    document.getElementById('inpDestSerial').value        = state.dest.serial ?? '';
    const nameEl = document.getElementById('inpMappingName');
    if (nameEl) nameEl.value = pm.name ?? '';
  } else {
    // Nuevo mapeo: valores por defecto (mismos que el prototipo)
    state = newState('48', 4, 48, 4);
  }

  syncSerial2Visibility();
  renderAll();
}

document.addEventListener('DOMContentLoaded', init);
