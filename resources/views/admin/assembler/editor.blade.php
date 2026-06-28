{{-- Editor de Ensamblaje — Fabric.js 5.3 --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $project->name }} — Ensamblador</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="/assets/remix-icon/remixicon.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin:0; padding:0; overflow:hidden; height:100%;
                     font-family:'Figtree',sans-serif; background:#94A3B8; }

        /* ── Shell ───────────────────────────────────────── */
        #app { display:flex; flex-direction:column; height:100vh; }

        /* ── Toolbar ──────────────────────────────────────── */
        #toolbar {
            height:48px; background:#1E293B; display:flex; align-items:center;
            padding:0 10px; gap:6px; flex-shrink:0; position:relative; z-index:100;
            /* NO overflow-x:auto — let children wrap/shrink instead */
        }
        .tb-btn {
            display:inline-flex; align-items:center; gap:4px; padding:4px 9px;
            border-radius:6px; border:none; cursor:pointer; font-size:12px; font-weight:500;
            white-space:nowrap; flex-shrink:0;
            transition:background .12s,color .12s; background:transparent; color:#CBD5E1;
        }
        .tb-btn:hover  { background:#334155; color:#fff; }
        .tb-btn.active { background:#3B82F6; color:#fff; }
        .tb-btn:disabled { opacity:.4; cursor:not-allowed; }
        .tb-sep { width:1px; height:22px; background:#334155; flex-shrink:0; margin:0 2px; }
        #proj-name {
            background:transparent; border:1px solid transparent; border-radius:4px;
            outline:none; color:#F1F5F9; font-size:13px; font-weight:600;
            min-width:120px; max-width:200px; padding:2px 4px; flex-shrink:1;
        }
        #proj-name:hover,#proj-name:focus { border-color:#475569; background:#334155; }
        #zoom-label { font-size:11px; color:#94A3B8; min-width:38px; text-align:center; flex-shrink:0; }
        #autosave-badge { font-size:11px; color:#4ADE80; flex-shrink:0; transition:opacity .3s; }
        .tb-spacer { flex:1; min-width:0; }

        /* ── Export dropdown — FIXED position via JS ─────── */
        #export-menu {
            position:fixed; background:#fff; border:1px solid #E2E8F0;
            border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.16);
            z-index:9999; min-width:180px; padding:4px; display:none;
        }
        .exp-opt {
            display:flex; align-items:center; gap:8px; width:100%;
            padding:9px 12px; font-size:12px; border:none; background:none;
            cursor:pointer; color:#374151; border-radius:7px; text-align:left;
        }
        .exp-opt:hover { background:#F1F5F9; }

        /* ── Body row ─────────────────────────────────────── */
        #body-row { display:flex; flex:1; overflow:hidden; }

        /* ── Sidebar ──────────────────────────────────────── */
        #sidebar {
            width:260px; flex-shrink:0; background:#fff;
            border-right:1px solid #E2E8F0; display:flex; flex-direction:column; overflow:hidden;
        }
        #sb-head { padding:10px 12px; border-bottom:1px solid #E2E8F0; flex-shrink:0; }
        #sb-head h2 { font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.05em; margin:0 0 6px; }
        #batch-filter { width:100%; border:1px solid #E2E8F0; border-radius:6px; padding:5px 7px; font-size:12px; color:#374151; }
        #lib-list { flex:1; overflow-y:auto; padding:8px; display:flex; flex-direction:column; gap:5px; }
        .lib-item {
            display:flex; align-items:center; gap:7px; padding:6px 8px;
            border-radius:8px; border:1px solid #E2E8F0; cursor:grab;
            background:#FAFAFA; transition:background .1s, border-color .1s;
        }
        .lib-item:hover { background:#EFF6FF; border-color:#BFDBFE; }
        .lib-thumb { width:50px; height:38px; object-fit:cover; border-radius:4px; background:#E2E8F0; border:1px solid #CBD5E1; flex-shrink:0; }
        .lib-meta { flex:1; min-width:0; }
        .lib-name { font-size:11px; font-weight:600; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .lib-sub  { font-size:10px; color:#94A3B8; margin-top:1px; }
        .lib-msg  { text-align:center; padding:20px 12px; color:#94A3B8; font-size:12px; line-height:1.6; }
        .lib-link { display:inline-block; margin-top:8px; padding:5px 10px; background:#16A34A; color:#fff; border-radius:6px; font-size:11px; text-decoration:none; }

        /* ── Canvas wrap ──────────────────────────────────── */
        #canvas-wrap { flex:1; position:relative; overflow:hidden; }
        #canvas-wrap.tool-conn canvas { cursor:crosshair !important; }
        #grid-cvs    { position:absolute; top:0; left:0; pointer-events:none; opacity:.25; z-index:1; }
        #conn-svg    { position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:3; }
        /* Fabric's canvas-container sits at z-index 2 between grid and svg */
        #canvas-wrap .canvas-container { position:absolute !important; top:0; left:0; z-index:2; }

        /* ── Props panel ──────────────────────────────────── */
        #props {
            width:300px; flex-shrink:0; background:#fff;
            border-left:1px solid #E2E8F0; overflow-y:auto; display:flex; flex-direction:column;
        }
        .pp-sec   { padding:14px 14px 10px; border-bottom:1px solid #F1F5F9; }
        .pp-title { font-size:10px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px; }
        .pp-grid  { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .pp-field { display:flex; flex-direction:column; gap:3px; }
        .pp-field.full { grid-column:1/-1; }
        .pp-lbl   { font-size:10px; font-weight:600; color:#64748B; }
        .pp-inp   { border:1px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:12px; color:#374151; width:100%; }
        .pp-inp:focus { outline:none; border-color:#3B82F6; box-shadow:0 0 0 2px #BFDBFE; }
        .pp-del   { width:100%; margin-top:10px; padding:7px; border:1px solid #FCA5A5;
                    border-radius:7px; background:#FFF1F2; color:#DC2626; font-size:12px;
                    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:5px; }
        .pp-del:hover { background:#FEE2E2; }
        .pp-empty { padding:24px 14px; text-align:center; font-size:12px; color:#94A3B8; line-height:1.6; }
        .pp-hint  { font-size:10px; color:#CBD5E1; line-height:1.9; }
        .pp-hint kbd { background:#F1F5F9; border:1px solid #E2E8F0; border-radius:3px;
                       padding:0 4px; font-size:10px; font-family:monospace; }
    </style>
</head>
<body>
<div id="app">

    {{-- ══ TOOLBAR ══ --}}
    <div id="toolbar">
        <a href="{{ route('admin.assembler.index') }}" class="tb-btn" title="Volver">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div class="tb-sep"></div>

        <input id="proj-name" type="text" value="{{ $project->name }}" maxlength="120" title="Clic para renombrar">

        <div class="tb-sep"></div>
        <button class="tb-btn" id="btn-undo" title="Deshacer (Ctrl+Z)"><i class="ri-arrow-go-back-line"></i></button>
        <button class="tb-btn" id="btn-redo" title="Rehacer (Ctrl+Y)"><i class="ri-arrow-go-forward-line"></i></button>

        <div class="tb-sep"></div>
        <button class="tb-btn active" id="btn-sel"  title="Selección (V)"><i class="ri-cursor-line"></i> Selección</button>
        <button class="tb-btn"        id="btn-conn" title="Conector (C)"><i class="ri-share-line"></i> Conector</button>

        <div class="tb-sep"></div>
        <button class="tb-btn" id="btn-grid" title="Grid"><i class="ri-grid-line"></i> Grid</button>

        <button class="tb-btn" id="btn-zo-"  title="Alejar (-)"><i class="ri-zoom-out-line"></i></button>
        <span   id="zoom-label">100%</span>
        <button class="tb-btn" id="btn-zo+"  title="Acercar (+)"><i class="ri-zoom-in-line"></i></button>
        <button class="tb-btn" id="btn-zrst" title="Reset zoom">Reset</button>

        <div class="tb-sep"></div>
        <button class="tb-btn" id="btn-auto" title="Posicionamiento automático"><i class="ri-magic-line"></i> Auto-layout</button>

        <div class="tb-spacer"></div>
        <span id="autosave-badge" style="opacity:0">✓ Guardado</span>
        <button class="tb-btn" id="btn-save" style="background:#16A34A;color:#fff;">
            <i class="ri-save-line"></i> Guardar
        </button>

        {{-- Export — dropdown posicionado con fixed via JS --}}
        <button class="tb-btn" id="btn-exp-toggle" style="background:#7C3AED;color:#fff;">
            <i class="ri-download-2-line"></i> Exportar <i class="ri-arrow-down-s-line"></i>
        </button>
    </div>

    {{-- Export menu (fixed, posicionado por JS) --}}
    <div id="export-menu">
        <button class="exp-opt" data-fmt="png"><i class="ri-image-line text-indigo-500"></i> PNG alta resolución</button>
        <button class="exp-opt" data-fmt="svg"><i class="ri-file-code-line text-blue-500"></i> SVG vectorial</button>
        <button class="exp-opt" data-fmt="pdf"><i class="ri-file-pdf-line text-red-500"></i> PDF</button>
    </div>

    {{-- ══ BODY ══ --}}
    <div id="body-row">

        {{-- ═ SIDEBAR ═ --}}
        <div id="sidebar">
            <div id="sb-head">
                <h2><i class="ri-image-2-line mr-1"></i>Biblioteca de imágenes</h2>
                <select id="batch-filter"><option value="">Todas las áreas</option></select>
            </div>
            <div id="lib-list"><div class="lib-msg"><i class="ri-loader-4-line"></i> Cargando…</div></div>
        </div>

        {{-- ═ CANVAS ═ --}}
        <div id="canvas-wrap">
            <canvas id="main-canvas"></canvas>
            <svg id="conn-svg"></svg>
        </div>

        {{-- ═ PROPS PANEL ═ --}}
        <div id="props">
            {{-- Objeto seleccionado --}}
            <div class="pp-sec" id="pp-empty-sec">
                <div class="pp-title"><i class="ri-settings-3-line mr-1"></i>Propiedades</div>
                <div class="pp-empty">Selecciona un objeto<br>en el canvas para ver<br>sus propiedades.</div>
            </div>

            <div class="pp-sec" id="pp-img-sec" style="display:none">
                <div class="pp-title"><i class="ri-image-line mr-1"></i>Imagen</div>
                <div class="pp-grid">
                    <div class="pp-field">
                        <label class="pp-lbl">Pos. X</label>
                        <input class="pp-inp" id="pp-x" type="number" step="1">
                    </div>
                    <div class="pp-field">
                        <label class="pp-lbl">Pos. Y</label>
                        <input class="pp-inp" id="pp-y" type="number" step="1">
                    </div>
                    <div class="pp-field">
                        <label class="pp-lbl">Ancho</label>
                        <input class="pp-inp" id="pp-w" type="number" step="1" min="10">
                    </div>
                    <div class="pp-field">
                        <label class="pp-lbl">Alto</label>
                        <input class="pp-inp" id="pp-h" type="number" step="1" min="10">
                    </div>
                    <div class="pp-field">
                        <label class="pp-lbl">Ángulo °</label>
                        <input class="pp-inp" id="pp-angle" type="number" step="1" min="0" max="360">
                    </div>
                    <div class="pp-field full">
                        <label class="pp-lbl">Etiqueta</label>
                        <input class="pp-inp" id="pp-label" type="text" placeholder="Nombre del área…">
                    </div>
                </div>
                <button class="pp-del" id="pp-del-obj"><i class="ri-delete-bin-line"></i> Eliminar objeto</button>
            </div>

            <div class="pp-sec" id="pp-conn-sec" style="display:none">
                <div class="pp-title"><i class="ri-share-line mr-1"></i>Conector</div>
                <div class="pp-grid">
                    <div class="pp-field full">
                        <label class="pp-lbl">Etiqueta</label>
                        <input class="pp-inp" id="pc-label" type="text" placeholder="Ej: 10G trunk">
                    </div>
                    <div class="pp-field">
                        <label class="pp-lbl">Color</label>
                        <input class="pp-inp" id="pc-color" type="color" value="#F97316" style="padding:2px 4px;height:32px;">
                    </div>
                    <div class="pp-field">
                        <label class="pp-lbl">Grosor</label>
                        <input class="pp-inp" id="pc-stroke" type="number" min="1" max="10" value="2">
                    </div>
                    <div class="pp-field full">
                        <label class="pp-lbl">Tipo de línea</label>
                        <select class="pp-inp" id="pc-type">
                            <option value="straight">Recta</option>
                            <option value="orthogonal">Ortogonal (codos 90°)</option>
                        </select>
                    </div>
                </div>
                <button class="pp-del" id="pp-del-conn"><i class="ri-delete-bin-line"></i> Eliminar conector</button>
            </div>

            {{-- Canvas stats --}}
            <div class="pp-sec">
                <div class="pp-title">Canvas</div>
                <div style="font-size:11px;color:#64748B;line-height:2;">
                    <div>Tamaño: <b>5000 × 4000 px</b></div>
                    <div id="stat-obj">Objetos: 0</div>
                    <div id="stat-conn">Conectores: 0</div>
                </div>
            </div>

            {{-- Help --}}
            <div class="pp-sec">
                <div class="pp-title">Atajos</div>
                <div class="pp-hint">
                    <div><kbd>Ctrl+S</kbd> Guardar</div>
                    <div><kbd>Ctrl+Z</kbd> Deshacer</div>
                    <div><kbd>Ctrl+Y</kbd> Rehacer</div>
                    <div><kbd>Supr / ⌫</kbd> Eliminar</div>
                    <div><kbd>Rueda</kbd> Zoom</div>
                    <div><kbd>Espacio + arrastrar</kbd> Pan</div>
                    <div><kbd>V</kbd> Herr. selección</div>
                    <div><kbd>C</kbd> Herr. conector</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════
   CONFIG
═══════════════════════════════════════════════════════ */
const SAVE_URL       = "{{ route('admin.assembler.update',   $project) }}";
const LIBRARY_URL    = "{{ route('admin.assembler.library',  $project) }}";
const AUTOLAYOUT_URL = "{{ route('admin.assembler.autolayout', $project) }}";
const EXPORT_URL     = "{{ route('admin.assembler.export',   $project) }}";
const INV_URL        = "{{ route('admin.inventario.index') }}";
const CSRF           = document.querySelector('meta[name="csrf-token"]').content;
const CANVAS_W = 5000, CANVAS_H = 4000, GRID = 20, AUTOSAVE_MS = 30000;
const INITIAL_JSON = @json($project->canvas_json);

/* ═══════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════ */
let canvas, tool = 'select', gridOn = false, spaceDown = false;
let connSrc = null;          // connector source object
let history = [], hIdx = -1, noHistory = false;
let autosaveT = null, connDebT = null, zoomRAF = null, restoreT = null;
let isPanning = false, panLast = { x:0, y:0 };
let libImages = [];

/* ═══════════════════════════════════════════════════════
   CANVAS INIT
═══════════════════════════════════════════════════════ */
window.addEventListener('load', () => {
    const wrap = document.getElementById('canvas-wrap');

    canvas = new fabric.Canvas('main-canvas', {
        width : wrap.clientWidth,
        height: wrap.clientHeight,
        backgroundColor: '#F8FAFC',
        selection: true,
        preserveObjectStacking: true,
        renderOnAddRemove: false,
        enableRetinaScaling: false,
        skipOffscreen: true,          // ← don't render objects outside viewport
    });

    /* ── Zoom with wheel ──────────────────────────────── */
    canvas.on('mouse:wheel', opt => {
        const delta = opt.e.deltaY, ox = opt.e.offsetX, oy = opt.e.offsetY;
        opt.e.preventDefault(); opt.e.stopPropagation();

        if (zoomRAF) return;   // one render per animation frame
        zoomRAF = requestAnimationFrame(() => {
            zoomRAF = null;
            // Low-quality mode during zoom
            setLowQuality(true);
            let z = Math.min(4, Math.max(0.06, canvas.getZoom() * (0.999 ** delta)));
            canvas.zoomToPoint({ x: ox, y: oy }, z);
            updateZoomLabel(z);
            if (gridOn) drawGrid();
            // Restore quality 200ms after last wheel event
            clearTimeout(restoreT);
            restoreT = setTimeout(() => setLowQuality(false), 200);
        });
    });

    /* ── Pan: Space+drag or middle button ─────────────── */
    canvas.on('mouse:down', opt => {
        if (opt.e.button === 1 || spaceDown) {
            isPanning = true; panLast = { x: opt.e.clientX, y: opt.e.clientY };
            canvas.selection = false;
            canvas.skipTargetFind = true;   // skip hit-testing during pan → faster
            return;
        }
        if (tool === 'connector') handleConnClick(opt);
    });
    canvas.on('mouse:move', opt => {
        if (isPanning) {
            const vpt = canvas.viewportTransform;
            vpt[4] += opt.e.clientX - panLast.x;
            vpt[5] += opt.e.clientY - panLast.y;
            panLast = { x: opt.e.clientX, y: opt.e.clientY };
            canvas.requestRenderAll();
            if (gridOn) drawGrid();
            return;
        }
        if (tool === 'connector' && connSrc) drawConnPreview(opt);
    });
    canvas.on('mouse:up', () => {
        if (isPanning) {
            isPanning = false;
            canvas.selection = tool === 'select';
            canvas.skipTargetFind = false;
        }
    });

    /* ── Object events ────────────────────────────────── */
    canvas.on('object:modified', () => { schedAutoSave(); pushHistory(); schedConnUpdate(); });
    canvas.on('object:moving',   () => schedConnUpdate());
    canvas.on('object:scaling',  () => schedConnUpdate());

    /* ── Snap to grid ─────────────────────────────────── */
    canvas.on('object:moving', opt => {
        if (!gridOn) return;
        const o = opt.target;
        o.set({ left: Math.round(o.left/GRID)*GRID, top: Math.round(o.top/GRID)*GRID });
    });

    /* ── Selection ────────────────────────────────────── */
    canvas.on('selection:created', syncProps);
    canvas.on('selection:updated', syncProps);
    canvas.on('selection:cleared', clearProps);

    /* ── Resize ───────────────────────────────────────── */
    window.addEventListener('resize', () => {
        canvas.setWidth(wrap.clientWidth);
        canvas.setHeight(wrap.clientHeight);
        canvas.requestRenderAll(); drawGrid();
    });

    /* ── Siempre empezar con canvas en blanco ────────────
       Si hay un estado guardado, mostrar banner para restaurarlo.
       El usuario agrega imágenes manualmente desde la biblioteca. ── */
    pushHistory();
    if (INITIAL_JSON && (INITIAL_JSON.objects||[]).length > 0) {
        showRestoreBanner(INITIAL_JSON);
    }
});

/* ─── Low-quality toggle during zoom/pan ─────────────── */
function setLowQuality(low) {
    // Disable image smoothing for faster zoom rendering
    const ctx = canvas.getContext();
    ctx.imageSmoothingEnabled   = !low;
    ctx.mozImageSmoothingEnabled    = !low;
    ctx.webkitImageSmoothingEnabled = !low;
    ctx.msImageSmoothingEnabled     = !low;
    if (!low) canvas.requestRenderAll();
}

/* ═══════════════════════════════════════════════════════
   LIBRARY
═══════════════════════════════════════════════════════ */
function initLibrary() {
    fetch(LIBRARY_URL, { headers:{ 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' } })
        .then(r => r.json())
        .then(d => { libImages = d.images||[]; buildBatchFilter(d.batches||[]); renderLib(libImages); })
        .catch(() => { document.getElementById('lib-list').innerHTML = '<div class="lib-msg">Error al cargar la biblioteca.</div>'; });
}

function buildBatchFilter(batches) {
    const sel = document.getElementById('batch-filter');
    batches.forEach(b => { const o = document.createElement('option'); o.value=b.id; o.textContent=b.name; sel.appendChild(o); });
    sel.addEventListener('change', () => {
        const v = sel.value;
        renderLib(v ? libImages.filter(i=>i.batch_id==v) : libImages);
    });
}

function renderLib(imgs) {
    const list = document.getElementById('lib-list');
    if (!imgs.length) {
        list.innerHTML = `<div class="lib-msg">No hay imágenes disponibles.<br>Genera topologías primero.<br>
            <a href="${INV_URL}" class="lib-link"><i class="ri-image-add-line"></i> Ir a Inventario</a></div>`;
        return;
    }
    list.innerHTML = '';
    imgs.forEach(img => {
        const el = document.createElement('div');
        el.className = 'lib-item'; el.draggable = true; el.title = img.label;
        el.innerHTML = `<img class="lib-thumb" src="${img.url}" loading="lazy" alt="" onerror="this.style.background='#E2E8F0'">
            <div class="lib-meta">
                <div class="lib-name">${img.label}</div>
                <div class="lib-sub">${img.categoria} · área ${img.batch_id}</div>
            </div>`;
        el.addEventListener('click', () => addImg(img));
        el.addEventListener('dragstart', e => e.dataTransfer.setData('asm-img', JSON.stringify(img)));
        list.appendChild(el);
    });

    // Drop zone
    const wrap = document.getElementById('canvas-wrap');
    wrap.ondragover = e => e.preventDefault();
    wrap.ondrop = e => {
        e.preventDefault();
        const raw = e.dataTransfer.getData('asm-img');
        if (!raw) return;
        const img  = JSON.parse(raw);
        const rect = wrap.getBoundingClientRect();
        const pt   = canvas.restorePointerVpt({ x: e.clientX-rect.left, y: e.clientY-rect.top });
        addImg(img, pt.x, pt.y);
    };
}

function addImg(img, cx, cy) {
    fabric.Image.fromURL(img.url, fab => {
        if (!fab) return;
        // objectCaching=true (default) so Fabric pre-renders to offscreen canvas → fast redraws
        if (fab.width > 800) fab.scale(800 / fab.width);
        if (cx === undefined) {
            const vpt = canvas.viewportTransform, z = canvas.getZoom();
            cx = (canvas.width/2  - vpt[4]) / z;
            cy = (canvas.height/2 - vpt[5]) / z;
        }
        fab.set({
            left: cx - fab.width*fab.scaleX/2,
            top : cy - fab.height*fab.scaleY/2,
            __imgId: img.id, __imgSrc: img.src, __imgUrl: img.url,
            __meta : { batch_id:img.batch_id, batch_name:img.batch_name,
                       client_id:img.client_id, categoria:img.categoria, label:img.label },
        });
        canvas.add(fab); canvas.setActiveObject(fab);
        canvas.requestRenderAll();
        schedAutoSave(); pushHistory(); updateStats();
    }, { crossOrigin:'anonymous' });
}

/* ═══════════════════════════════════════════════════════
   TOOLBAR
═══════════════════════════════════════════════════════ */
function initToolbar() {
    document.getElementById('proj-name').addEventListener('change', e =>
        fetch(SAVE_URL, { method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body:JSON.stringify({ name:e.target.value }) }));

    document.getElementById('btn-sel').addEventListener('click',  () => setTool('select'));
    document.getElementById('btn-conn').addEventListener('click', () => setTool('connector'));
    document.getElementById('btn-undo').addEventListener('click', undo);
    document.getElementById('btn-redo').addEventListener('click', redo);
    document.getElementById('btn-grid').addEventListener('click', toggleGrid);
    document.getElementById('btn-zo-').addEventListener('click',  () => doZoom(-0.15));
    document.getElementById('btn-zo+').addEventListener('click',  () => doZoom( 0.15));
    document.getElementById('btn-zrst').addEventListener('click', resetZoom);
    document.getElementById('btn-auto').addEventListener('click', runAutoLayout);
    document.getElementById('btn-save').addEventListener('click', saveNow);

    // Export dropdown — positioned with fixed coords
    const expBtn  = document.getElementById('btn-exp-toggle');
    const expMenu = document.getElementById('export-menu');

    expBtn.addEventListener('click', e => {
        e.stopPropagation();
        const open = expMenu.style.display !== 'none' && expMenu.style.display !== '';
        if (open) { expMenu.style.display = 'none'; return; }
        // Position below the button, aligned to its right edge
        const r = expBtn.getBoundingClientRect();
        expMenu.style.top     = (r.bottom + 6) + 'px';
        expMenu.style.left    = 'auto';
        expMenu.style.right   = (window.innerWidth - r.right) + 'px';
        expMenu.style.display = 'block';
    });
    document.addEventListener('click', () => expMenu.style.display = 'none');
    expMenu.addEventListener('click', e => e.stopPropagation());

    document.querySelectorAll('.exp-opt').forEach(b =>
        b.addEventListener('click', () => { expMenu.style.display='none'; exportAs(b.dataset.fmt); }));
}

function setTool(t) {
    tool = t;
    document.getElementById('btn-sel').classList.toggle('active',  t==='select');
    document.getElementById('btn-conn').classList.toggle('active', t==='connector');
    document.getElementById('canvas-wrap').classList.toggle('tool-conn', t==='connector');
    canvas.selection     = t === 'select';
    canvas.defaultCursor = t === 'connector' ? 'crosshair' : 'default';
    canvas.hoverCursor   = t === 'connector' ? 'crosshair' : 'move';
    connSrc = null;
    document.getElementById('conn-svg').innerHTML = '';
    // Restore opacity of any previously highlighted source
    canvas.getObjects().forEach(o => { if (o.opacity === 0.6) o.set({ opacity:1 }); });
    canvas.requestRenderAll();
}

/* ═══════════════════════════════════════════════════════
   GRID
═══════════════════════════════════════════════════════ */
function toggleGrid() {
    gridOn = !gridOn;
    document.getElementById('btn-grid').classList.toggle('active', gridOn);
    drawGrid();
}

function drawGrid() {
    let gc = document.getElementById('grid-cvs');
    if (!gc) { gc = document.createElement('canvas'); gc.id='grid-cvs'; document.getElementById('canvas-wrap').prepend(gc); }
    if (!gridOn) { gc.style.display='none'; return; }
    gc.style.display='block'; gc.width=canvas.width; gc.height=canvas.height;
    const ctx = gc.getContext('2d');
    ctx.clearRect(0,0,gc.width,gc.height);
    ctx.strokeStyle='#64748B'; ctx.lineWidth=0.5;
    const z=canvas.getZoom(), vpt=canvas.viewportTransform, s=GRID*z;
    const ox=((vpt[4]%s)+s)%s, oy=((vpt[5]%s)+s)%s;
    for(let x=ox;x<=gc.width; x+=s){ ctx.beginPath();ctx.moveTo(x,0);ctx.lineTo(x,gc.height);ctx.stroke(); }
    for(let y=oy;y<=gc.height;y+=s){ ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(gc.width,y);ctx.stroke(); }
}

/* ═══════════════════════════════════════════════════════
   ZOOM
═══════════════════════════════════════════════════════ */
function doZoom(delta) {
    const z = Math.min(4, Math.max(0.06, canvas.getZoom()+delta));
    canvas.zoomToPoint({ x:canvas.width/2, y:canvas.height/2 }, z);
    updateZoomLabel(z); drawGrid();
}
function resetZoom() {
    canvas.setZoom(1); canvas.viewportTransform=[1,0,0,1,0,0];
    canvas.requestRenderAll(); updateZoomLabel(1); drawGrid();
}
function updateZoomLabel(z) { document.getElementById('zoom-label').textContent = Math.round(z*100)+'%'; }

/* ═══════════════════════════════════════════════════════
   CONNECTORS
═══════════════════════════════════════════════════════ */
function handleConnClick(opt) {
    const t = opt.target;
    if (!t || t.__isConn || t.__isConnLabel) return;
    if (!connSrc) {
        connSrc = t; t.set({ opacity:0.65 }); canvas.requestRenderAll();
    } else {
        if (t !== connSrc) { connSrc.set({ opacity:1 }); makeConnector(connSrc, t); }
        connSrc = null;
        canvas.requestRenderAll();
        document.getElementById('conn-svg').innerHTML='';
    }
}

function drawConnPreview(opt) {
    if (!connSrc) return;
    const p  = canvas.getPointer(opt.e);
    const fc = objCenter(connSrc);
    const z  = canvas.getZoom(), vpt = canvas.viewportTransform;
    const x1=fc.x*z+vpt[4], y1=fc.y*z+vpt[5], x2=p.x*z+vpt[4], y2=p.y*z+vpt[5];
    document.getElementById('conn-svg').innerHTML =
        `<defs><marker id="ah" markerWidth="7" markerHeight="7" refX="5" refY="3.5" orient="auto">
            <path d="M0,0 L0,7 L7,3.5 z" fill="#F97316"/></marker></defs>
         <line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"
               stroke="#F97316" stroke-width="2.5" stroke-dasharray="7,4" marker-end="url(#ah)" stroke-linecap="round"/>`;
}

function objCenter(o) {
    const b = o.getBoundingRect(true,true);
    return { x: b.left+b.width/2, y: b.top+b.height/2 };
}

function connPoints(from, to, type) {
    if (type === 'orthogonal') {
        const mx = (from.x+to.x)/2;
        return [from, {x:mx,y:from.y}, {x:mx,y:to.y}, to];
    }
    return [from, to];
}

function makeConnector(fromObj, toObj, opts={}) {
    const from  = objCenter(fromObj), to = objCenter(toObj);
    const type  = opts.type||'straight', color=opts.color||'#F97316';
    const sw    = opts.strokeWidth||2, label=opts.label||'';
    const pts   = connPoints(from, to, type);

    const line = new fabric.Polyline(pts, {
        fill:'transparent', stroke:color, strokeWidth:sw,
        selectable:true, evented:true, hasBorders:false, hasControls:false,
        objectCaching:false, perPixelTargetFind:true,
        __isConn:true,
        __cd:{ fromId:fromObj.__imgId, toId:toObj.__imgId, type, color, label, strokeWidth:sw },
    });
    canvas.add(line); canvas.sendToBack(line);

    if (label) {
        const mid = { x:(from.x+to.x)/2, y:(from.y+to.y)/2 };
        const lbl = new fabric.Text(label, {
            left:mid.x, top:mid.y-10, fontSize:11, fill:color,
            selectable:true, evented:true, originX:'center', originY:'center',
            __isConnLabel:true,
        });
        canvas.add(lbl); line.__lbl = lbl;
    }

    canvas.requestRenderAll(); schedAutoSave(); pushHistory(); updateStats();
    return line;
}

function schedConnUpdate() {
    clearTimeout(connDebT);
    connDebT = setTimeout(refreshConnectors, 60);
}

function refreshConnectors() {
    let dirty=false;
    canvas.getObjects().forEach(o => {
        if (!o.__isConn || !o.__cd) return;
        const fo = canvas.getObjects().find(x=>x.__imgId===o.__cd.fromId);
        const to = canvas.getObjects().find(x=>x.__imgId===o.__cd.toId);
        if (!fo||!to) return;
        const from=objCenter(fo), dest=objCenter(to);
        const pts=connPoints(from,dest,o.__cd.type);
        o.set({ points:pts }); o._setPositionDimensions({}); o.setCoords();
        if (o.__lbl) {
            o.__lbl.set({ left:(from.x+dest.x)/2, top:(from.y+dest.y)/2-10 });
            o.__lbl.setCoords();
        }
        dirty=true;
    });
    if (dirty) canvas.requestRenderAll();
}

/* ═══════════════════════════════════════════════════════
   HISTORY
═══════════════════════════════════════════════════════ */
const HIST_KEYS = ['__imgId','__imgSrc','__imgUrl','__meta','__isConn','__cd','__isConnLabel'];

function pushHistory() {
    if (noHistory) return;
    const snap = JSON.stringify(canvas.toJSON(HIST_KEYS));
    history = history.slice(0, hIdx+1);
    history.push(snap);
    if (history.length > 60) history.shift();
    hIdx = history.length-1;
}

function undo() { if (hIdx>0)  restoreSnap(history[--hIdx]); }
function redo() { if (hIdx<history.length-1) restoreSnap(history[++hIdx]); }

function restoreSnap(snap) {
    noHistory=true;
    canvas.loadFromJSON(JSON.parse(snap), () => {
        canvas.requestRenderAll(); refreshConnectors(); noHistory=false; updateStats();
    });
}

/* ═══════════════════════════════════════════════════════
   PROPS PANEL
═══════════════════════════════════════════════════════ */
function initPropsPanel() {
    ['pp-x','pp-y','pp-w','pp-h','pp-angle','pp-label'].forEach(id =>
        document.getElementById(id).addEventListener('change', applyImgProps));
    ['pc-label','pc-color','pc-type','pc-stroke'].forEach(id =>
        document.getElementById(id).addEventListener('change', applyConnProps));
    document.getElementById('pp-del-obj').addEventListener('click',  () => delSelected());
    document.getElementById('pp-del-conn').addEventListener('click', () => delSelected());
}

function syncProps() {
    const obj = canvas.getActiveObject();
    show('pp-empty-sec', false); show('pp-img-sec', false); show('pp-conn-sec', false);
    if (!obj) { show('pp-empty-sec', true); return; }

    if (obj.__isConn) {
        show('pp-conn-sec', true);
        const d = obj.__cd||{};
        set('pc-label',  d.label||'');
        set('pc-color',  d.color||'#F97316');
        set('pc-type',   d.type||'straight');
        set('pc-stroke', d.strokeWidth||2);
    } else if (!obj.__isConnLabel) {
        show('pp-img-sec', true);
        set('pp-x',     Math.round(obj.left));
        set('pp-y',     Math.round(obj.top));
        set('pp-w',     Math.round(obj.width  * (obj.scaleX||1)));
        set('pp-h',     Math.round(obj.height * (obj.scaleY||1)));
        set('pp-angle', Math.round(obj.angle||0));
        set('pp-label', obj.__meta?.label||'');
    } else {
        show('pp-empty-sec', true);
    }
}

function clearProps() { show('pp-empty-sec',true); show('pp-img-sec',false); show('pp-conn-sec',false); }
function show(id, v)  { document.getElementById(id).style.display = v?'':'none'; }
function set(id, v)   { document.getElementById(id).value = v; }
function get(id)      { return document.getElementById(id).value; }

function applyImgProps() {
    const obj = canvas.getActiveObject();
    if (!obj || obj.__isConn) return;
    const newW = parseFloat(get('pp-w'))||obj.width*obj.scaleX;
    const newH = parseFloat(get('pp-h'))||obj.height*obj.scaleY;
    obj.set({
        left: parseFloat(get('pp-x'))||obj.left,
        top : parseFloat(get('pp-y'))||obj.top,
        scaleX: newW/obj.width,
        scaleY: newH/obj.height,
        angle : parseFloat(get('pp-angle'))||0,
    });
    if (obj.__meta) obj.__meta.label = get('pp-label');
    obj.setCoords(); canvas.requestRenderAll();
    schedConnUpdate(); schedAutoSave();
}

function applyConnProps() {
    const obj = canvas.getActiveObject();
    if (!obj||!obj.__isConn) return;
    const color=get('pc-color'), label=get('pc-label'), type=get('pc-type'), sw=parseInt(get('pc-stroke'))||2;
    obj.set({ stroke:color, strokeWidth:sw });
    if (!obj.__cd) obj.__cd={};
    Object.assign(obj.__cd, { color, label, type, strokeWidth:sw });
    // Rebuild polyline for type changes
    const fo=canvas.getObjects().find(x=>x.__imgId===obj.__cd.fromId);
    const to=canvas.getObjects().find(x=>x.__imgId===obj.__cd.toId);
    if (fo&&to) { obj.set({ points:connPoints(objCenter(fo),objCenter(to),type) }); obj._setPositionDimensions({}); }
    if (obj.__lbl) obj.__lbl.set({ text:label, fill:color });
    canvas.requestRenderAll(); schedAutoSave();
}

function delSelected() {
    const obj = canvas.getActiveObject(); if (!obj) return;
    const list = obj.type==='activeSelection' ? obj.getObjects() : [obj];
    list.forEach(o => { if(o.__lbl) canvas.remove(o.__lbl); canvas.remove(o); });
    canvas.discardActiveObject(); canvas.requestRenderAll();
    schedAutoSave(); pushHistory(); updateStats();
}

function updateStats() {
    const all   = canvas.getObjects();
    const imgs  = all.filter(o=>!o.__isConn&&!o.__isConnLabel);
    const conns = all.filter(o=>o.__isConn);
    document.getElementById('stat-obj').textContent  = `Objetos: ${imgs.length}`;
    document.getElementById('stat-conn').textContent = `Conectores: ${conns.length}`;
}

/* ═══════════════════════════════════════════════════════
   KEYBOARD
═══════════════════════════════════════════════════════ */
function initKeyboard() {
    window.addEventListener('keydown', e => {
        if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;
        if (e.key===' ')      { spaceDown=true; e.preventDefault(); }
        if (e.key==='Delete'||e.key==='Backspace') delSelected();
        if (e.ctrlKey&&e.key==='z') { e.preventDefault(); undo(); }
        if (e.ctrlKey&&e.key==='y') { e.preventDefault(); redo(); }
        if (e.ctrlKey&&e.key==='s') { e.preventDefault(); saveNow(); }
        if (!e.ctrlKey&&e.key==='v') setTool('select');
        if (!e.ctrlKey&&e.key==='c') setTool('connector');
    });
    window.addEventListener('keyup', e => { if(e.key===' ') spaceDown=false; });
}

/* ═══════════════════════════════════════════════════════
   SAVE / AUTOSAVE
═══════════════════════════════════════════════════════ */
function schedAutoSave() { clearTimeout(autosaveT); autosaveT=setTimeout(saveNow, AUTOSAVE_MS); }

function saveNow() {
    clearTimeout(autosaveT);
    const json = buildJson();
    fetch(SAVE_URL, {
        method:'PUT',
        headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json' },
        body: JSON.stringify({ canvas_json: JSON.stringify(json) }),
    }).then(r=>r.json()).then(d=>{ if(d.ok) flashBadge(); }).catch(console.error);
}

function buildJson() {
    const objects=[], connectors=[];
    canvas.getObjects().forEach(o => {
        if (o.__isConnLabel) return;
        if (o.__isConn) {
            const d=o.__cd||{};
            connectors.push({ id:'c'+Math.random().toString(36).slice(2),
                from:d.fromId, to:d.toId, type:d.type||'straight',
                label:d.label||'', color:d.color||'#F97316', strokeWidth:d.strokeWidth||2 });
        } else if (o.__imgSrc) {
            objects.push({ id:o.__imgId, type:'image', src:o.__imgSrc, url:o.__imgUrl||null,
                x:Math.round(o.left), y:Math.round(o.top),
                width:Math.round(o.width), height:Math.round(o.height),
                scaleX:o.scaleX, scaleY:o.scaleY, angle:o.angle||0, metadata:o.__meta||null });
        }
    });
    return { version:'1.0', canvas:{width:CANVAS_W,height:CANVAS_H,background:'#F8FAFC'}, objects, connectors };
}

function flashBadge() {
    const b=document.getElementById('autosave-badge');
    b.style.opacity='1'; setTimeout(()=>b.style.opacity='0', 2500);
}

/* ═══════════════════════════════════════════════════════
   LOAD FROM JSON
═══════════════════════════════════════════════════════ */
function loadJson(json) {
    if (!json||!json.objects) { pushHistory(); return; }
    const ps = (json.objects||[]).map(obj => new Promise(res => {
        if (obj.type!=='image') return res();
        const url = obj.url || ('/storage/'+obj.src);
        fabric.Image.fromURL(url, fab => {
            if (!fab) return res();
            fab.set({ left:obj.x, top:obj.y, scaleX:obj.scaleX||1, scaleY:obj.scaleY||1,
                      angle:obj.angle||0, __imgId:obj.id, __imgSrc:obj.src,
                      __imgUrl:obj.url||url, __meta:obj.metadata||null });
            canvas.add(fab); res();
        }, { crossOrigin:'anonymous' });
    }));
    Promise.all(ps).then(() => {
        (json.connectors||[]).forEach(c => {
            const fo=canvas.getObjects().find(o=>o.__imgId===c.from);
            const to=canvas.getObjects().find(o=>o.__imgId===c.to);
            if (fo&&to) makeConnector(fo,to,{ type:c.type,color:c.color,label:c.label,strokeWidth:c.strokeWidth });
        });
        canvas.requestRenderAll(); pushHistory(); updateStats();
    });
}

/* ═══════════════════════════════════════════════════════
   BANNER RESTAURAR TRABAJO GUARDADO
═══════════════════════════════════════════════════════ */
function showRestoreBanner(savedJson) {
    const banner = document.createElement('div');
    banner.id = 'restore-banner';
    banner.style.cssText = [
        'position:fixed','top:60px','left:50%','transform:translateX(-50%)',
        'background:#1e40af','color:#fff','border-radius:8px','padding:10px 18px',
        'display:flex','align-items:center','gap:12px','z-index:9999',
        'box-shadow:0 4px 16px rgba(0,0,0,.35)','font-size:13px'
    ].join(';');
    banner.innerHTML = `
        <i class="ri-save-line"></i>
        <span>Tienes un diagrama guardado.</span>
        <button onclick="restoreSaved()"
            style="background:#3b82f6;border:none;color:#fff;padding:4px 12px;border-radius:5px;cursor:pointer;font-size:12px;">
            Restaurar
        </button>
        <button onclick="document.getElementById('restore-banner').remove()"
            style="background:transparent;border:none;color:#fff;cursor:pointer;font-size:16px;line-height:1;">
            ×
        </button>`;
    document.body.appendChild(banner);
}

function restoreSaved() {
    const saved = @json($project->canvas_json);
    if (saved) loadJson(saved);
    const b = document.getElementById('restore-banner');
    if (b) b.remove();
}

/* ═══════════════════════════════════════════════════════
   AUTO-LAYOUT  (reordena objetos YA en el canvas)
   Ya no descarga ni limpia el canvas.
═══════════════════════════════════════════════════════ */
function runAutoLayout() {
    const imgs = canvas.getObjects().filter(o => o.__imgId);
    if (imgs.length === 0) {
        alert('El canvas está vacío.\nArrastra imágenes desde la biblioteca para empezar.');
        return;
    }

    const btn = document.getElementById('btn-auto');
    btn.disabled = true; btn.innerHTML = '<i class="ri-loader-4-line"></i> Ordenando…';

    // Distribuir en cuadrícula los objetos que ya están en el canvas
    const cols  = Math.ceil(Math.sqrt(imgs.length));
    const cellW = 820, cellH = 640, padX = 60, padY = 60;

    imgs.forEach((obj, i) => {
        const col = i % cols;
        const row = Math.floor(i / cols);
        const maxW = cellW - padX * 2;
        const sc   = (obj.width || 1) > maxW ? maxW / obj.width : (obj.scaleX || 1);
        obj.set({ left: padX + col * cellW, top: padY + row * cellH, scaleX: sc, scaleY: sc });
        obj.setCoords();
    });

    refreshConnectors();
    canvas.requestRenderAll();
    schedAutoSave(); pushHistory(); updateStats();
    btn.disabled = false; btn.innerHTML = '<i class="ri-magic-line"></i> Auto-layout';
}

/* ═══════════════════════════════════════════════════════
   EXPORT
═══════════════════════════════════════════════════════ */
function exportAs(fmt) {
    if (fmt==='svg') { exportSvgWithEmbeddedImages(); return; }
    if (fmt==='pdf') {
        const { jsPDF }=window.jspdf;
        const img=canvas.toDataURL({format:'jpeg',quality:.85,multiplier:1});
        const pdf=new jsPDF({orientation:'landscape',unit:'px',format:[canvas.width,canvas.height]});
        pdf.addImage(img,'JPEG',0,0,canvas.width,canvas.height); pdf.save('diagrama.pdf'); return;
    }
    // PNG via Python — save first, then submit form
    saveNow();
    setTimeout(()=>{
        const f=document.createElement('form'); f.method='POST'; f.action=EXPORT_URL;
        f.innerHTML=`<input name="_token" value="${CSRF}"><input name="format" value="png">`;
        document.body.appendChild(f); f.submit(); document.body.removeChild(f);
    }, 800);
}

/* Convierte todas las fabric.Image en data-URL antes de llamar toSVG(),
   para que el SVG descargado sea autocontenido (sin referencias HTTP externas). */
async function exportSvgWithEmbeddedImages() {
    const fabImages = canvas.getObjects().filter(o => o.type === 'image');
    // Convertir cada imagen a dataURL via offscreen canvas
    const restores = [];
    for (const fab of fabImages) {
        const el = fab.getElement();
        if (!el) continue;
        try {
            const oc = document.createElement('canvas');
            oc.width  = el.naturalWidth  || el.width;
            oc.height = el.naturalHeight || el.height;
            oc.getContext('2d').drawImage(el, 0, 0);
            const dataUrl = oc.toDataURL('image/png');
            restores.push({ fab, oldSrc: fab.getSrc ? fab.getSrc() : null });
            // Reemplazar temporalmente con data URL
            await new Promise(res => fab.setSrc(dataUrl, res, { crossOrigin: null }));
        } catch(e) { /* imagen cross-origin sin datos: se omite */ }
    }
    // Generar SVG con imágenes embebidas
    const svg = canvas.toSVG();
    // Restaurar fuentes originales
    for (const { fab, oldSrc } of restores) {
        if (oldSrc) fab.setSrc(oldSrc, () => {}, { crossOrigin: 'anonymous' });
    }
    dlBlob(new Blob([svg], { type:'image/svg+xml' }), 'diagrama.svg');
}
function dlBlob(blob,name){ const u=URL.createObjectURL(blob),a=Object.assign(document.createElement('a'),{href:u,download:name}); a.click(); URL.revokeObjectURL(u); }

/* ═══════════════════════════════════════════════════════
   BOOT
═══════════════════════════════════════════════════════ */
window.addEventListener('load', () => {
    initToolbar();
    initPropsPanel();
    initLibrary();
    initKeyboard();
});
</script>
</body>
</html>
