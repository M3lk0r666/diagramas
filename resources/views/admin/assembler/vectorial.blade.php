<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagrama Vectorial — {{ $project->name }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script>
// Fabric.js 5.3.1 bug: internally sets ctx.textBaseline = 'alphabetical'
// The valid value is 'alphabetic'. Patch the setter to normalize it silently.
(function () {
    const desc = Object.getOwnPropertyDescriptor(CanvasRenderingContext2D.prototype, 'textBaseline');
    if (desc && desc.set) {
        Object.defineProperty(CanvasRenderingContext2D.prototype, 'textBaseline', {
            get: desc.get,
            set: function (val) {
                desc.set.call(this, val === 'alphabetical' ? 'alphabetic' : val);
            },
            configurable: true,
        });
    }
})();
</script>
<style>
/* ── Layout ──────────────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{display:flex;flex-direction:column;height:100vh;overflow:hidden;
     background:#0F172A;font-family:'Inter',system-ui,sans-serif;color:#F1F5F9}

/* ── Top toolbar ─────────────────────────────────────────────────── */
#toolbar{display:flex;align-items:center;gap:6px;height:48px;padding:0 12px;
         background:#1E293B;border-bottom:1px solid #334155;flex-shrink:0;z-index:10}
#toolbar .sep{width:1px;height:24px;background:#334155;margin:0 4px}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;
        border:none;background:transparent;color:#94A3B8;cursor:pointer;font-size:13px;
        transition:background .15s,color .15s;white-space:nowrap}
.tb-btn:hover{background:#334155;color:#F1F5F9}
.tb-btn.active{background:#3B82F6;color:#fff}
.tb-btn i{font-size:16px}
#project-name{font-size:13px;font-weight:600;color:#CBD5E1;margin-right:6px}
#autosave-badge{font-size:11px;color:#22C55E;opacity:0;transition:opacity .5s;margin-left:4px}
.tb-spacer{flex:1}

/* ── Export dropdown ─────────────────────────────────────────────── */
#export-menu{position:fixed;background:#1E293B;border:1px solid #334155;border-radius:8px;
             padding:4px;z-index:9999;display:none;min-width:160px;
             box-shadow:0 8px 24px rgba(0,0,0,.5)}
#export-menu button{display:flex;align-items:center;gap:8px;width:100%;padding:7px 12px;
                    border:none;background:transparent;color:#CBD5E1;cursor:pointer;
                    border-radius:5px;font-size:13px;text-align:left}
#export-menu button:hover{background:#334155;color:#F1F5F9}

/* ── Main row ────────────────────────────────────────────────────── */
#main-row{display:flex;flex:1;overflow:hidden}

/* ── Sidebar ─────────────────────────────────────────────────────── */
#sidebar{width:280px;flex-shrink:0;background:#1E293B;border-right:1px solid #334155;
         display:flex;flex-direction:column;overflow:hidden}
#sidebar-header{padding:10px 12px 6px;border-bottom:1px solid #2D3748;flex-shrink:0}
#sidebar-header h3{font-size:12px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.06em}
#sidebar-search{width:100%;margin-top:6px;background:#0F172A;border:1px solid #334155;
                border-radius:6px;padding:5px 8px;color:#F1F5F9;font-size:12px;outline:none}
#sidebar-search:focus{border-color:#3B82F6}
#sidebar-body{flex:1;overflow-y:auto;padding:6px}
#sidebar-body::-webkit-scrollbar{width:4px}
#sidebar-body::-webkit-scrollbar-thumb{background:#334155;border-radius:2px}

/* ── Batch groups ────────────────────────────────────────────────── */
.batch-group{margin-bottom:4px}
.batch-header{display:flex;align-items:center;gap:6px;padding:6px 8px;border-radius:7px;
              cursor:pointer;background:#263044;border:1px solid #334155;user-select:none}
.batch-header:hover{background:#2D3A50}
.batch-chevron{font-size:14px;color:#64748B;transition:transform .2s;margin-left:auto}
.batch-chevron.open{transform:rotate(90deg)}
.batch-name{font-size:12px;font-weight:600;color:#CBD5E1;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.batch-count{font-size:10px;color:#64748B;background:#1E293B;border-radius:10px;padding:1px 6px}
.batch-load-btn{font-size:10px;background:#3B82F6;color:#fff;border:none;border-radius:4px;
                padding:2px 7px;cursor:pointer;white-space:nowrap;flex-shrink:0}
.batch-load-btn:hover{background:#2563EB}
.batch-load-btn.loaded{background:#16A34A}
.switch-list{display:none;padding:4px 0 2px 10px}
.switch-list.open{display:block}
.switch-item{display:flex;align-items:center;gap:6px;padding:4px 6px;border-radius:5px;
             cursor:grab;font-size:11px;color:#94A3B8;border:1px solid transparent;
             margin-bottom:2px;transition:background .1s}
.switch-item:hover{background:#263044;border-color:#334155;color:#F1F5F9}
.switch-item:active{cursor:grabbing}
.switch-role-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.switch-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.switch-ip{font-size:10px;color:#475569;margin-left:auto;flex-shrink:0}
/* ── Icon palette ────────────────────────────────────────────────── */
#icon-palette{padding:10px 12px;border-bottom:1px solid #2D3748;flex-shrink:0}
#icon-palette .pal-title{font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;
                          letter-spacing:.06em;margin-bottom:8px;display:flex;align-items:center}
#icon-palette .pal-title span{margin-left:auto;font-size:10px;color:#334155;font-weight:400;text-transform:none}
.pal-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:5px}
.pal-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 4px;
          border-radius:7px;border:1px solid #334155;background:#0F172A;cursor:grab;
          transition:background .15s,border-color .15s;user-select:none}
.pal-item:hover{background:#1E3A5F;border-color:#3B82F6}
.pal-item:active{cursor:grabbing}
.pal-item img{width:28px;height:28px;pointer-events:none}
.pal-item span{font-size:9px;color:#94A3B8;text-align:center;pointer-events:none}

/* ── Legend ──────────────────────────────────────────────────────── */
#legend{padding:8px 12px;border-top:1px solid #2D3748;flex-shrink:0}
#legend h4{font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
.legend-item{display:flex;align-items:center;gap:6px;font-size:11px;color:#94A3B8;margin-bottom:2px}
.legend-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}

/* ── Port dialog ─────────────────────────────────────────────────── */
#port-dialog{display:none;position:fixed;background:#1E293B;border:1px solid #3B82F6;
             border-radius:10px;padding:14px;z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,.5);
             min-width:220px}
#port-dialog h4{font-size:12px;font-weight:700;color:#CBD5E1;margin-bottom:10px}
#port-dialog label{display:block;font-size:11px;color:#64748B;margin-bottom:2px}
#port-dialog input{width:100%;background:#0F172A;border:1px solid #334155;border-radius:5px;
                   padding:5px 8px;color:#F1F5F9;font-size:12px;outline:none;margin-bottom:8px}
#port-dialog input:focus{border-color:#3B82F6}
#port-dialog .pd-actions{display:flex;gap:6px;justify-content:flex-end;margin-top:4px}
#port-dialog .pd-ok{background:#3B82F6;color:#fff;border:none;border-radius:5px;
                    padding:5px 14px;font-size:12px;cursor:pointer;font-weight:600}
#port-dialog .pd-cancel{background:transparent;color:#64748B;border:1px solid #334155;
                        border-radius:5px;padding:5px 10px;font-size:12px;cursor:pointer}

/* ── Canvas wrapper ──────────────────────────────────────────────── */
#canvas-area{flex:1;position:relative;overflow:hidden;background:#F8FAFC;
             background-image:radial-gradient(circle,#CBD5E1 1px,transparent 1px);
             background-size:28px 28px}
#canvas-wrap{position:absolute;inset:0}
canvas{position:absolute;top:0;left:0}
#canvas-hint{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
             text-align:center;color:#334155;pointer-events:none;user-select:none}
#canvas-hint i{font-size:48px;display:block;margin-bottom:8px}
#canvas-hint p{font-size:13px}

/* ── Properties panel ────────────────────────────────────────────── */
#props-panel{width:300px;flex-shrink:0;background:#1E293B;border-left:1px solid #334155;
             display:flex;flex-direction:column;overflow:hidden}
#props-header{padding:10px 14px;border-bottom:1px solid #2D3748;flex-shrink:0}
#props-header h3{font-size:12px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.06em}
#props-body{flex:1;overflow-y:auto;padding:12px}
#props-body::-webkit-scrollbar{width:4px}
#props-body::-webkit-scrollbar-thumb{background:#334155;border-radius:2px}
.props-empty{color:#475569;font-size:12px;text-align:center;padding-top:40px}
.prop-section{margin-bottom:14px}
.prop-section-title{font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;
                     letter-spacing:.08em;margin-bottom:6px}
.prop-row{display:flex;justify-content:space-between;align-items:flex-start;
          gap:8px;margin-bottom:5px}
.prop-key{font-size:11px;color:#64748B;flex-shrink:0;max-width:100px}
.prop-val{font-size:11px;color:#CBD5E1;word-break:break-all;text-align:right}
.prop-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:12px;
            font-size:10px;font-weight:600;color:#fff}
.prop-pos{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:4px}
.prop-pos label{font-size:10px;color:#64748B}
.prop-pos input{width:100%;background:#0F172A;border:1px solid #334155;border-radius:4px;
                padding:3px 6px;color:#F1F5F9;font-size:12px;outline:none}
.prop-pos input:focus{border-color:#3B82F6}

/* ── Stats bar ───────────────────────────────────────────────────── */
#stats-bar{display:flex;gap:16px;align-items:center;padding:4px 12px;
           background:#0F172A;border-top:1px solid #1E293B;flex-shrink:0;
           font-size:11px;color:#475569}
</style>
</head>
<body>

{{-- ── Top toolbar ─────────────────────────────────────────────────── --}}
<div id="toolbar">
    <a href="{{ route('admin.assembler.index') }}" class="tb-btn" title="Volver">
        <i class="ri-arrow-left-line"></i>
    </a>
    <span id="project-name">{{ $project->name }}</span>
    <span id="autosave-badge"><i class="ri-check-line"></i> Guardado</span>
    <div class="sep"></div>

    {{-- Herramientas --}}
    <button class="tb-btn active" id="btn-select" title="Seleccionar (V)"><i class="ri-cursor-line"></i> Seleccionar</button>
    <button class="tb-btn" id="btn-conn" title="Conectar (C)"><i class="ri-share-line"></i> Conectar</button>
    <button class="tb-btn" id="btn-text" title="Texto (T)"><i class="ri-text"></i> Texto</button>
    <div class="sep"></div>

    {{-- Acciones --}}
    <button class="tb-btn" id="btn-layout" title="Auto-layout jerárquico"><i class="ri-layout-top-line"></i> Layout</button>
    <button class="tb-btn" id="btn-fit" title="Ajustar vista"><i class="ri-fullscreen-line"></i> Ajustar</button>
    <button class="tb-btn" id="btn-undo" title="Deshacer Ctrl+Z"><i class="ri-arrow-go-back-line"></i></button>
    <button class="tb-btn" id="btn-redo" title="Rehacer Ctrl+Y"><i class="ri-arrow-go-forward-line"></i></button>
    <div class="sep"></div>

    <button class="tb-btn" id="btn-save" title="Guardar Ctrl+S"><i class="ri-save-line"></i> Guardar</button>
    <div class="tb-spacer"></div>

    {{-- Export --}}
    <button class="tb-btn" id="btn-export" title="Exportar"><i class="ri-download-line"></i> Exportar <i class="ri-arrow-down-s-line"></i></button>
    <div id="export-menu">
        <button onclick="exportAs('png-client')"><i class="ri-image-line"></i> PNG (alta resolución)</button>
        <button onclick="exportAs('svg')"><i class="ri-file-code-line"></i> SVG vectorial</button>
        <button onclick="exportAs('pdf')"><i class="ri-file-pdf-line"></i> PDF</button>
    </div>
    <button class="tb-btn" id="btn-shortcuts" title="Atajos de teclado"><i class="ri-keyboard-line"></i></button>
</div>

{{-- ── Shortcuts modal ─────────────────────────────────────────────── --}}
<div id="shortcuts-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9998;align-items:center;justify-content:center">
    <div style="background:#1E293B;border:1px solid #334155;border-radius:12px;padding:24px;min-width:380px;max-width:480px;color:#CBD5E1">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="font-size:14px;font-weight:700;color:#F1F5F9">Atajos de teclado</h3>
            <button onclick="document.getElementById('shortcuts-overlay').style.display='none'"
                style="background:none;border:none;color:#64748B;cursor:pointer;font-size:18px">×</button>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <tr><td colspan="2" style="color:#64748B;font-size:10px;font-weight:700;text-transform:uppercase;padding:8px 0 4px">Herramientas</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">V</td><td>Seleccionar</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">C</td><td>Modo conectar</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">T</td><td>Agregar texto</td></tr>
            <tr><td colspan="2" style="color:#64748B;font-size:10px;font-weight:700;text-transform:uppercase;padding:8px 0 4px">Navegación</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">Espacio + Arrastrar</td><td>Paneo del canvas</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">Alt + Arrastrar</td><td>Paneo alternativo</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">Rueda del ratón</td><td>Zoom in / out</td></tr>
            <tr><td colspan="2" style="color:#64748B;font-size:10px;font-weight:700;text-transform:uppercase;padding:8px 0 4px">Edición</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">Ctrl + Z</td><td>Deshacer</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">Ctrl + Y</td><td>Rehacer</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">Ctrl + S</td><td>Guardar</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">Supr / Backspace</td><td>Eliminar selección</td></tr>
            <tr><td colspan="2" style="color:#64748B;font-size:10px;font-weight:700;text-transform:uppercase;padding:8px 0 4px">Canvas</td></tr>
            <tr><td style="padding:4px 0;color:#94A3B8">Doble clic (sidebar)</td><td>Agregar switch al canvas</td></tr>
        </table>
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid #334155;font-size:11px;color:#475569">
            💡 Arrastra switches desde el sidebar al canvas, o usa <strong>Cargar área</strong> para agregar un área completa con sus conexiones.
        </div>
    </div>
</div>

{{-- ── Main row ─────────────────────────────────────────────────────── --}}
<div id="main-row">

    {{-- ── Sidebar ──────────────────────────────────────────────────── --}}
    <div id="sidebar">
        <div id="sidebar-header">
            <h3><i class="ri-node-tree"></i> Áreas — {{ $project->client->name ?? 'Cliente' }}</h3>
            <input type="text" id="sidebar-search" placeholder="Buscar switch…">
        </div>

        {{-- Icon palette: drag to canvas to create custom nodes --}}
        <div id="icon-palette">
            <div class="pal-title"><i class="ri-drag-move-line" style="margin-right:5px"></i> Elementos <span>arrastra al canvas</span></div>
            <div class="pal-grid">
                @php
                    $palItems = [
                        ['role'=>'core',         'label'=>'Core',      'icon'=>'core_switch'],
                        ['role'=>'backbone',      'label'=>'Backbone',  'icon'=>'backbone_switch'],
                        ['role'=>'distribution',  'label'=>'Distrib.',  'icon'=>'dist_switch'],
                        ['role'=>'access',        'label'=>'Acceso',    'icon'=>'access_switch'],
                        ['role'=>'stack',         'label'=>'Stack',     'icon'=>'stack_switch'],
                        ['role'=>'access',        'label'=>'Custom',    'icon'=>'access_switch', 'custom'=>true],
                    ];
                @endphp
                @foreach($palItems as $item)
                <div class="pal-item" draggable="true"
                     data-role="{{ $item['role'] }}"
                     data-icon="{{ $item['icon'] }}"
                     data-custom="{{ isset($item['custom']) ? '1' : '0' }}"
                     title="Arrastra al canvas para agregar un {{ $item['label'] }}">
                    <img src="{{ 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(storage_path('app/public/media/'.$item['icon'].'.svg'))) }}"
                         alt="{{ $item['label'] }}">
                    <span>{{ $item['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div id="sidebar-body">
            <div style="color:#475569;font-size:12px;text-align:center;padding:20px">
                <i class="ri-loader-4-line" style="font-size:20px"></i><br>Cargando switches…
            </div>
        </div>
        <div id="legend">
            <h4>Leyenda</h4>
            <div class="legend-item"><span class="legend-dot" style="background:#1E3A5F"></span> Core</div>
            <div class="legend-item"><span class="legend-dot" style="background:#1D4ED8"></span> Backbone</div>
            <div class="legend-item"><span class="legend-dot" style="background:#0891B2"></span> Distribución</div>
            <div class="legend-item"><span class="legend-dot" style="background:#16A34A"></span> Acceso</div>
            <div class="legend-item"><span class="legend-dot" style="background:#7C3AED"></span> Stack</div>
        </div>
    </div>

    {{-- ── Port dialog (floating, shown after manual connect) ──────── --}}
    <div id="port-dialog">
        <h4><i class="ri-git-branch-line"></i> Puertos de conexión</h4>
        <label>Puerto origen</label>
        <input type="text" id="pd-src" placeholder="ej. 49" autocomplete="off">
        <label>Puerto destino</label>
        <input type="text" id="pd-dst" placeholder="ej. 1" autocomplete="off">
        <div class="pd-actions">
            <button class="pd-cancel" onclick="cancelPortDialog()">Omitir</button>
            <button class="pd-ok" onclick="confirmPortDialog()">Aceptar</button>
        </div>
    </div>

    {{-- ── Canvas ────────────────────────────────────────────────────── --}}
    <div id="canvas-area">
        <div id="canvas-wrap">
            <canvas id="c"></canvas>
        </div>
        <div id="canvas-hint">
            <i class="ri-node-tree"></i>
            <p>Arrastra switches desde el panel izquierdo<br>o usa <strong>Cargar área</strong> para agregar todos los switches de un área.</p>
        </div>
    </div>

    {{-- ── Properties panel ─────────────────────────────────────────── --}}
    <div id="props-panel">
        <div id="props-header"><h3><i class="ri-information-line"></i> Propiedades</h3></div>
        <div id="props-body">
            {{-- Estado vacío + info canvas + atajos --}}
            <div id="props-idle">
                <div class="props-empty">Selecciona un objeto<br>en el canvas para ver<br>sus propiedades.</div>

                <div style="margin-top:20px;border-top:1px solid #2D3748;padding-top:14px">
                    <p style="font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Canvas</p>
                    <div style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:#94A3B8">
                        <div style="display:flex;justify-content:space-between">
                            <span>Tamaño</span>
                            <span style="color:#E2E8F0;font-weight:600">{{ number_format(8000) }} × {{ number_format(6000) }} px</span>
                        </div>
                        <div style="display:flex;justify-content:space-between">
                            <span>Nodos</span>
                            <span id="idle-nodes" style="color:#E2E8F0;font-weight:600">0</span>
                        </div>
                        <div style="display:flex;justify-content:space-between">
                            <span>Conexiones</span>
                            <span id="idle-edges" style="color:#E2E8F0;font-weight:600">0</span>
                        </div>
                    </div>
                </div>

                <div style="margin-top:18px;border-top:1px solid #2D3748;padding-top:14px">
                    <p style="font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Atajos</p>
                    <div style="display:flex;flex-direction:column;gap:6px;font-size:11px;color:#94A3B8">
                        @foreach([
                            ['Ctrl+S',         'Guardar'],
                            ['Ctrl+Z',         'Deshacer'],
                            ['Ctrl+Y',         'Rehacer'],
                            ['Supr / ⌫',       'Eliminar'],
                            ['Rueda',          'Zoom'],
                            ['Espacio + arrastrar', 'Pan'],
                            ['V',              'Herr. selección'],
                            ['C',              'Herr. conector'],
                        ] as [$key, $desc])
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px">
                            <kbd style="background:#0F172A;border:1px solid #334155;border-radius:4px;
                                        padding:2px 6px;font-size:10px;color:#CBD5E1;white-space:nowrap;
                                        font-family:monospace">{{ $key }}</kbd>
                            <span style="text-align:right">{{ $desc }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Propiedades de nodo seleccionado (se rellena por JS) --}}
            <div id="props-node" style="display:none"></div>
        </div>
    </div>
</div>

{{-- ── Stats bar ────────────────────────────────────────────────────── --}}
<div id="stats-bar">
    <span id="stat-nodes">Nodos: 0</span>
    <span id="stat-edges">Conexiones: 0</span>
    <span id="stat-zoom">Zoom: 100%</span>
    <span style="margin-left:auto" id="stat-mode">Modo: Seleccionar</span>
</div>

{{-- ── JavaScript ──────────────────────────────────────────────────── --}}
<script>
/* ═══════════════════════════════════════════════════════
   CONSTANTES
═══════════════════════════════════════════════════════ */
const CSRF       = '{{ csrf_token() }}';
const GRAPH_URL  = '{{ route('admin.assembler.graph', $project) }}';
const SAVE_URL   = '{{ route('admin.assembler.update', $project) }}';
const INITIAL_JSON = @json($project->canvas_json);
const CANVAS_W   = 8000;
const CANVAS_H   = 6000;
const AUTOSAVE_MS = 4000;

// Icon map: base64 data URIs — embedded at render time, zero HTTP requests
@php
    function switchIconDataUri(string $name): string {
        // SVG desde storage/app/public/media/ (misma ruta que usan las vistas del sistema)
        $svgPath = storage_path("app/public/media/{$name}.svg");
        if (file_exists($svgPath)) {
            return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($svgPath));
        }
        // Fallback: PNG desde public/icons/
        $pngPath = public_path("icons/{$name}.png");
        if (file_exists($pngPath)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($pngPath));
        }
        return '';
    }
@endphp
const ICONS = {
    core_switch:     '{{ switchIconDataUri("core_switch") }}',
    backbone_switch: '{{ switchIconDataUri("backbone_switch") }}',
    dist_switch:     '{{ switchIconDataUri("dist_switch") }}',
    access_switch:   '{{ switchIconDataUri("access_switch") }}',
    stack_switch:    '{{ switchIconDataUri("stack_switch") }}',
};

const ROLE_COLORS = {
    core: '#1E3A5F', backbone: '#1D4ED8',
    distribution: '#0891B2', access: '#16A34A',
};
const ROLE_LABELS = {
    core: 'Core', backbone: 'Backbone', distribution: 'Distribución', access: 'Acceso',
};

/* ═══════════════════════════════════════════════════════
   FABRIC CANVAS SETUP
═══════════════════════════════════════════════════════ */
const wrap = document.getElementById('canvas-wrap');
const canvas = new fabric.Canvas('c', {
    width:              wrap.clientWidth,
    height:             wrap.clientHeight,
    selection:          true,
    preserveObjectStacking: true,
    renderOnAddRemove:  false,
    skipOffscreen:      true,
    enableRetinaScaling:false,
});
window.addEventListener('resize', () => {
    canvas.setWidth(wrap.clientWidth);
    canvas.setHeight(wrap.clientHeight);
    canvas.requestRenderAll();
});

/* ── Viewport pan/zoom ────────────────────────────────── */
let isPanning = false, lastPt = null, spaceDown = false;
let rafZoom = null;

// Spacebar → enter pan mode (like Figma/Illustrator)
window.addEventListener('keydown', ev => {
    if (ev.code === 'Space' && !ev.target.matches('input,textarea')) {
        if (!spaceDown) {
            spaceDown = true;
            canvas.defaultCursor = 'grab';
            canvas.forEachObject(o => { o.__prevSelectable = o.selectable; o.selectable = false; });
            canvas.selection = false;
        }
        ev.preventDefault();
    }
}, true);
window.addEventListener('keyup', ev => {
    if (ev.code === 'Space') {
        spaceDown = false;
        if (!isPanning) {
            canvas.defaultCursor = 'default';
            canvas.forEachObject(o => { if (o.__prevSelectable !== undefined) { o.selectable = o.__prevSelectable; delete o.__prevSelectable; } });
            canvas.selection = (currentTool === 'select');
        }
    }
});

canvas.on('mouse:wheel', ev => {
    const e = ev.e;
    e.preventDefault();
    if (rafZoom) return;
    rafZoom = requestAnimationFrame(() => {
        rafZoom = null;
        let z = canvas.getZoom();
        z *= e.deltaY > 0 ? 0.93 : 1.07;
        z = Math.max(0.05, Math.min(5, z));
        canvas.zoomToPoint({ x: e.offsetX, y: e.offsetY }, z);
        document.getElementById('stat-zoom').textContent = 'Zoom: ' + Math.round(z * 100) + '%';
        canvas.requestRenderAll();
    });
});

canvas.on('mouse:down', ev => {
    // Pan: Spacebar held, OR Alt key, OR middle-click
    if (spaceDown || ev.e.altKey || ev.e.button === 1) {
        isPanning = true;
        lastPt = { x: ev.e.clientX, y: ev.e.clientY };
        canvas.defaultCursor = 'grabbing';
        canvas.skipTargetFind = true;
        ev.e.preventDefault();
    }
});
canvas.on('mouse:move', ev => {
    if (!isPanning || !lastPt) return;
    const vpt = canvas.viewportTransform.slice();
    vpt[4] += ev.e.clientX - lastPt.x;
    vpt[5] += ev.e.clientY - lastPt.y;
    canvas.setViewportTransform(vpt);
    lastPt = { x: ev.e.clientX, y: ev.e.clientY };
    canvas.requestRenderAll();
});
canvas.on('mouse:up', () => {
    isPanning = false; lastPt = null;
    canvas.skipTargetFind = false;
    canvas.defaultCursor = spaceDown ? 'grab' : 'default';
});

/* ═══════════════════════════════════════════════════════
   GRAPH DATA
═══════════════════════════════════════════════════════ */
let allNodes = [];   // from API
let allEdges = [];   // from API
let placedNodes = {};  // nodeId → fabric object
let connObjects = [];  // fabric connector lines

function loadGraph() {
    fetch(GRAPH_URL, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { showSidebarError(data.error); return; }
            allNodes = data.nodes || [];
            allEdges = data.edges || [];
            renderSidebar(data.batches || [], allNodes);
            if (INITIAL_JSON && (INITIAL_JSON.nodes || []).length > 0) {
                restoreCanvas(INITIAL_JSON);
                document.getElementById('canvas-hint').style.display = 'none';
            }
        })
        .catch(e => showSidebarError('Error de red: ' + e.message));
}

/* ═══════════════════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════════════════ */
function renderSidebar(batches, nodes) {
    const body = document.getElementById('sidebar-body');
    body.innerHTML = '';
    if (!batches.length) {
        body.innerHTML = '<div style="color:#475569;font-size:12px;text-align:center;padding:20px">No hay áreas con switches.</div>';
        return;
    }

    batches.forEach(batch => {
        const batchNodes = nodes.filter(n => n.batch_id === batch.id);
        const group = document.createElement('div');
        group.className = 'batch-group';
        group.innerHTML = `
            <div class="batch-header" onclick="toggleBatch(${batch.id})">
                <i class="ri-building-2-line" style="color:#64748B;font-size:14px"></i>
                <span class="batch-name">${batch.name}</span>
                <span class="batch-count">${batch.switch_count}</span>
                <button class="batch-load-btn" id="load-btn-${batch.id}"
                        onclick="event.stopPropagation();loadBatch(${batch.id})">
                    <i class="ri-add-line"></i> Cargar área
                </button>
                <i class="ri-arrow-right-s-line batch-chevron" id="chev-${batch.id}"></i>
            </div>
            <div class="switch-list" id="swlist-${batch.id}">
                ${batchNodes.map(n => switchItemHtml(n)).join('')}
            </div>`;
        body.appendChild(group);
    });

    setupDragFromSidebar();
}

function switchItemHtml(node) {
    const color = node.is_stacked ? '#7C3AED' : (ROLE_COLORS[node.role] || '#16A34A');
    return `<div class="switch-item" draggable="true"
                 data-node-id="${node.id}"
                 ondblclick="addNodeToCanvas('${node.id}')">
        <span class="switch-role-dot" style="background:${color}"></span>
        <span class="switch-label" title="${node.label}">${node.label}</span>
        <span class="switch-ip">${node.ip ? node.ip.split('/')[0] : ''}</span>
    </div>`;
}

function toggleBatch(batchId) {
    const list = document.getElementById(`swlist-${batchId}`);
    const chev = document.getElementById(`chev-${batchId}`);
    list.classList.toggle('open');
    chev.classList.toggle('open');
}

function showSidebarError(msg) {
    document.getElementById('sidebar-body').innerHTML =
        `<div style="color:#EF4444;font-size:12px;padding:16px">${msg}</div>`;
}

/* ── Search filter ──────────────────────────────────────── */
document.getElementById('sidebar-search').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.switch-item').forEach(el => {
        const label = el.querySelector('.switch-label').textContent.toLowerCase();
        const ip    = el.querySelector('.switch-ip').textContent.toLowerCase();
        el.style.display = (label.includes(q) || ip.includes(q)) ? '' : 'none';
    });
});

/* ═══════════════════════════════════════════════════════
   DRAG FROM SIDEBAR → CANVAS
═══════════════════════════════════════════════════════ */
function setupDragFromSidebar() {
    document.querySelectorAll('.switch-item[draggable]').forEach(el => {
        el.addEventListener('dragstart', ev => {
            ev.dataTransfer.setData('vec-node', el.dataset.nodeId);
        });
    });

    const area = document.getElementById('canvas-area');
    area.addEventListener('dragover', ev => ev.preventDefault());
    area.addEventListener('drop', ev => {
        ev.preventDefault();
        const nodeId = ev.dataTransfer.getData('vec-node');
        if (!nodeId) return;
        const rect = wrap.getBoundingClientRect();
        const vpt  = canvas.viewportTransform;
        const z    = canvas.getZoom();
        const x    = (ev.clientX - rect.left - vpt[4]) / z;
        const y    = (ev.clientY - rect.top  - vpt[5]) / z;
        addNodeToCanvas(nodeId, x, y);
    });
}

/* ═══════════════════════════════════════════════════════
   LOAD FULL BATCH ONTO CANVAS
═══════════════════════════════════════════════════════ */
function loadBatch(batchId) {
    const btn       = document.getElementById(`load-btn-${batchId}`);
    const nodes     = allNodes.filter(n => n.batch_id === batchId);
    if (!nodes.length) return;

    btn.innerHTML   = '<i class="ri-loader-4-line"></i> Cargando…';
    btn.disabled    = true;

    // Find the bounding box of already-placed nodes to offset below
    const existingObjs = canvas.getObjects().filter(o => o.__nodeId);
    let offsetY = 80;
    if (existingObjs.length) {
        const maxY = Math.max(...existingObjs.map(o => (o.top || 0) + (o.height || 0) * (o.scaleY || 1)));
        offsetY = maxY + 120;
    }

    // Hierarchical layout within batch zone
    const levels = { core: [], backbone: [], distribution: [], access: [] };
    nodes.forEach(n => { (levels[n.role] || levels.access).push(n); });

    const levelOrder = ['core', 'backbone', 'distribution', 'access'];
    const nodePositions = {};
    let yOff = offsetY;

    levelOrder.forEach(role => {
        const group = levels[role];
        if (!group.length) return;
        const totalW  = group.length * 140;
        const startX  = Math.max(80, (CANVAS_W / 2) - totalW / 2);
        group.forEach((n, i) => {
            nodePositions[n.id] = { x: startX + i * 140, y: yOff };
        });
        yOff += 180;
    });

    // Draw nodes then connections
    const ps = nodes.map(n => {
        if (placedNodes[n.id]) return Promise.resolve();
        const pos = nodePositions[n.id] || { x: 80, y: offsetY };
        return createSwitchNode(n, pos.x, pos.y);
    });

    Promise.all(ps).then(() => {
        drawEdgesForBatch(batchId);
        canvas.requestRenderAll();
        btn.innerHTML = '<i class="ri-check-line"></i> Cargada';
        btn.classList.add('loaded');
        btn.disabled = false;
        document.getElementById('canvas-hint').style.display = 'none';
        updateStats();
        schedAutoSave();
        pushHistory();
    });
}

/* ═══════════════════════════════════════════════════════
   ADD SINGLE NODE
═══════════════════════════════════════════════════════ */
function addNodeToCanvas(nodeId, cx, cy) {
    if (placedNodes[nodeId]) {
        // Pan to existing node
        const obj = placedNodes[nodeId];
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        return;
    }
    const node = allNodes.find(n => n.id === nodeId);
    if (!node) return;

    const vpt = canvas.viewportTransform;
    const z   = canvas.getZoom();
    if (cx === undefined) {
        cx = (canvas.width / 2 - vpt[4]) / z;
        cy = (canvas.height / 2 - vpt[5]) / z;
    }

    createSwitchNode(node, cx, cy).then(() => {
        drawEdgesForNode(nodeId);
        canvas.requestRenderAll();
        document.getElementById('canvas-hint').style.display = 'none';
        updateStats();
        schedAutoSave();
        pushHistory();
    });
}

/* ═══════════════════════════════════════════════════════
   CREATE SWITCH NODE (Fabric Group)
═══════════════════════════════════════════════════════ */
function createSwitchNode(node, x, y) {
    return new Promise(resolve => {
        const iconKey = node.is_stacked ? 'stack_switch' : node.icon || 'access_switch';
        const iconUrl = ICONS[iconKey] || ICONS.access_switch;
        const color   = node.is_stacked ? '#7C3AED' : (ROLE_COLORS[node.role] || '#16A34A');
        const label   = node.label.length > 18 ? node.label.slice(0, 16) + '…' : node.label;
        const ip      = (node.ip || '').split('/')[0];

        fabric.Image.fromURL(iconUrl, img => {
            // Card layout (all coords relative to group center):
            // bg rect: center (0, -8), spans y: -50 to +34, x: -41 to +41
            // icon zone: y -50 to +0  → icon center at (0, -25)
            // label zone: y  +4 to +20
            // ip zone:    y +20 to +34
            img.scaleToWidth(44);
            img.scaleToHeight(44);
            img.set({ left: 0, top: -25, originX: 'center', originY: 'center' });

            const labelText = new fabric.Text(label, {
                left: 0, top: 5, fontSize: 10, fontFamily: 'Arial',
                fill: '#E2E8F0', textAlign: 'center', originX: 'center', originY: 'top',
                fontWeight: '600',
            });
            const ipText = new fabric.Text(ip, {
                left: 0, top: 20, fontSize: 9, fontFamily: 'Arial',
                fill: '#64748B', textAlign: 'center', originX: 'center', originY: 'top',
            });
            const rolePill = new fabric.Rect({
                left: 0, top: -55, width: 70, height: 14,
                rx: 7, ry: 7, fill: color + '33', stroke: color, strokeWidth: 1,
                originX: 'center', originY: 'center',
            });
            const roleLabel = new fabric.Text(ROLE_LABELS[node.role] || node.role, {
                left: 0, top: -55, fontSize: 8, fill: color, textAlign: 'center',
                originX: 'center', originY: 'center', fontWeight: '700',
                fontFamily: 'Arial',
            });
            const bg = new fabric.Rect({
                left: 0, top: -8, width: 82, height: 84,
                rx: 8, ry: 8, fill: '#1E293B', stroke: color,
                strokeWidth: 1.5, originX: 'center', originY: 'center',
                shadow: new fabric.Shadow({ color: 'rgba(0,0,0,.4)', blur: 8, offsetX: 0, offsetY: 3 }),
            });

            const group = new fabric.Group([bg, rolePill, roleLabel, img, labelText, ipText], {
                left: x, top: y, originX: 'center', originY: 'center',
                hasControls: true, hasBorders: true,
                cornerColor: '#3B82F6', cornerSize: 6, transparentCorners: false,
                lockScalingX: true, lockScalingY: true,
                __nodeId: node.id,
                __nodeData: node,
            });

            canvas.add(group);
            placedNodes[node.id] = group;
            resolve(group);
        }, { crossOrigin: 'anonymous' });
    });
}

/* ═══════════════════════════════════════════════════════
   DRAW EDGES
═══════════════════════════════════════════════════════ */
function drawEdgesForBatch(batchId) {
    const batchNodeIds = new Set(
        allNodes.filter(n => n.batch_id === batchId).map(n => n.id)
    );
    const edges = allEdges.filter(e =>
        (batchNodeIds.has(e.from) || batchNodeIds.has(e.to)) &&
        placedNodes[e.from] && placedNodes[e.to]
    );
    edges.forEach(e => drawEdge(e));
}

function drawEdgesForNode(nodeId) {
    const edges = allEdges.filter(e =>
        (e.from === nodeId || e.to === nodeId) &&
        placedNodes[e.from] && placedNodes[e.to]
    );
    edges.forEach(e => drawEdge(e));
}

function edgeMidpoint(p1, p2, offset) {
    // offset: perpendicular displacement to avoid overlapping labels on same pair
    const mx = (p1.x + p2.x) / 2;
    const my = (p1.y + p2.y) / 2;
    if (!offset) return { x: mx, y: my };
    const dx = p2.x - p1.x, dy = p2.y - p1.y;
    const len = Math.sqrt(dx*dx + dy*dy) || 1;
    return { x: mx + (-dy / len) * offset, y: my + (dx / len) * offset };
}

function drawEdgeLabel(edge, p1, p2) {
    const hasSrc = edge.src_port && edge.src_port !== '';
    const hasDst = edge.dst_port && edge.dst_port !== '';
    if (!hasSrc && !hasDst) return null;

    const portTxt = [
        hasSrc ? edge.src_port : '?',
        hasDst ? edge.dst_port : '?',
    ].join(' ↔ ');

    const color = edge.inter_area ? '#F97316' : '#3B82F6';
    const mid   = edgeMidpoint(p1, p2, 8);

    const lbl = new fabric.Text(portTxt, {
        left: mid.x, top: mid.y,
        originX: 'center', originY: 'center',
        fontSize: 9, fontFamily: 'Arial', fill: color,
        backgroundColor: 'rgba(248,250,252,0.88)',
        padding: 2,
        selectable: false, evented: false,
        opacity: 0.9,
        __edgeLabel: edge.id,
    });
    canvas.add(lbl);
    lbl.sendToBack();
    return lbl;
}

function drawEdge(edge) {
    // Skip if already drawn
    if (connObjects.find(c => c.__edgeId === edge.id)) return;

    const fromObj = placedNodes[edge.from];
    const toObj   = placedNodes[edge.to];
    if (!fromObj || !toObj) return;

    const p1 = fromObj.getCenterPoint();
    const p2 = toObj.getCenterPoint();

    const color = edge.inter_area ? '#F97316' : '#3B82F6';
    const dash  = edge.inter_area ? [6, 4]    : null;

    const line = new fabric.Line([p1.x, p1.y, p2.x, p2.y], {
        stroke: color, strokeWidth: 1.5, strokeDashArray: dash,
        selectable: true, evented: true, hoverCursor: 'pointer',
        opacity: 0.75,
        __edgeId: edge.id,
        __edgeData: edge,
    });

    canvas.add(line);
    line.sendToBack();

    // Port label
    line.__portLabel = drawEdgeLabel(edge, p1, p2);

    connObjects.push(line);
}

function refreshEdges() {
    connObjects.forEach(line => {
        const edge = line.__edgeData;
        if (!edge) return;
        const fromObj = placedNodes[edge.from];
        const toObj   = placedNodes[edge.to];
        if (!fromObj || !toObj) return;
        const p1 = fromObj.getCenterPoint();
        const p2 = toObj.getCenterPoint();
        line.set({ x1: p1.x, y1: p1.y, x2: p2.x, y2: p2.y });
        line.setCoords();
        // Move port label with the edge
        if (line.__portLabel) {
            const mid = edgeMidpoint(p1, p2, 8);
            line.__portLabel.set({ left: mid.x, top: mid.y });
            line.__portLabel.setCoords();
        }
    });
}

canvas.on('object:moving', ev => {
    if (ev.target?.__nodeId) refreshEdges();
    canvas.requestRenderAll();
});

/* ═══════════════════════════════════════════════════════
   TOOLS
═══════════════════════════════════════════════════════ */
let currentTool = 'select';
let connFirstNode = null;

function setTool(tool) {
    currentTool = tool;
    connFirstNode = null;
    document.querySelectorAll('[id^=btn-]').forEach(b => b.classList.remove('active'));
    const btnMap = { select: 'btn-select', connect: 'btn-conn', text: 'btn-text' };
    document.getElementById(btnMap[tool] || 'btn-select')?.classList.add('active');
    canvas.defaultCursor = tool === 'select' ? 'default' : 'crosshair';
    canvas.selection     = tool === 'select';
    document.getElementById('stat-mode').textContent = 'Modo: ' +
        ({ select: 'Seleccionar', connect: 'Conectar', text: 'Texto' }[tool] || tool);
}

document.getElementById('btn-select').addEventListener('click', () => setTool('select'));
document.getElementById('btn-conn').addEventListener('click',   () => setTool('connect'));
document.getElementById('btn-text').addEventListener('click',   () => setTool('text'));

/* ── Port dialog ─────────────────────────────────────── */
let _pendingEdge = null;

function showPortDialog(edge, screenX, screenY) {
    _pendingEdge = edge;
    const dlg = document.getElementById('port-dialog');
    document.getElementById('pd-src').value = '';
    document.getElementById('pd-dst').value = '';
    dlg.style.left = Math.min(screenX, window.innerWidth  - 240) + 'px';
    dlg.style.top  = Math.min(screenY, window.innerHeight - 160) + 'px';
    dlg.style.display = 'block';
    document.getElementById('pd-src').focus();
}
function confirmPortDialog() {
    if (_pendingEdge) {
        _pendingEdge.src_port = document.getElementById('pd-src').value.trim();
        _pendingEdge.dst_port = document.getElementById('pd-dst').value.trim();
        // Update label if already drawn (it was drawn with empty ports)
        const line = connObjects.find(c => c.__edgeId === _pendingEdge.id);
        if (line) {
            if (line.__portLabel) { canvas.remove(line.__portLabel); }
            const p1 = { x: line.x1, y: line.y1 };
            const p2 = { x: line.x2, y: line.y2 };
            line.__portLabel = drawEdgeLabel(_pendingEdge, p1, p2);
        }
        canvas.requestRenderAll();
        schedAutoSave();
    }
    cancelPortDialog();
}
function cancelPortDialog() {
    document.getElementById('port-dialog').style.display = 'none';
    _pendingEdge = null;
}
// Enter key submits port dialog
document.getElementById('pd-dst').addEventListener('keydown', e => { if (e.key === 'Enter') confirmPortDialog(); });
document.getElementById('pd-src').addEventListener('keydown', e => { if (e.key === 'Tab') { e.preventDefault(); document.getElementById('pd-dst').focus(); } });

canvas.on('mouse:down', ev => {
    if (currentTool === 'connect' && ev.target?.__nodeId) {
        if (!connFirstNode) {
            connFirstNode = ev.target;
            ev.target._objects?.[0]?.set({ stroke: '#F59E0B', strokeWidth: 3 });
            canvas.requestRenderAll();
        } else if (ev.target !== connFirstNode) {
            // Manual edge — create it, then prompt for ports
            const edgeId = 'manual_' + Date.now();
            const fakeEdge = {
                id: edgeId,
                from: connFirstNode.__nodeId,
                to: ev.target.__nodeId,
                src_port: '', dst_port: '', inter_area: false,
            };
            allEdges.push(fakeEdge);
            drawEdge(fakeEdge);
            connFirstNode._objects?.[0]?.set({ stroke: ROLE_COLORS[connFirstNode.__nodeData?.role] || '#16A34A', strokeWidth: 1.5 });
            connFirstNode = null;
            canvas.requestRenderAll();
            // Show port dialog near cursor
            showPortDialog(fakeEdge, ev.e.clientX, ev.e.clientY);
        }
    } else if (currentTool === 'text' && !ev.target) {
        const vpt = canvas.viewportTransform;
        const z   = canvas.getZoom();
        const x   = (ev.e.offsetX - vpt[4]) / z;
        const y   = (ev.e.offsetY - vpt[5]) / z;
        const txt = new fabric.IText('Etiqueta', {
            left: x, top: y, fontSize: 13, fill: '#CBD5E1',
            fontFamily: 'Arial',
            __isLabel: true,
        });
        canvas.add(txt);
        canvas.setActiveObject(txt);
        txt.enterEditing();
        canvas.requestRenderAll();
        schedAutoSave();
    }
});

/* ═══════════════════════════════════════════════════════
   AUTO-LAYOUT JERÁRQUICO GLOBAL
═══════════════════════════════════════════════════════ */
document.getElementById('btn-layout').addEventListener('click', runHierarchicalLayout);

function runHierarchicalLayout() {
    const placed = Object.values(placedNodes);
    if (!placed.length) {
        alert('No hay nodos en el canvas. Carga un área primero.'); return;
    }

    // Group by level
    const levels = { 0: [], 1: [], 2: [], 3: [] };
    const levelMap = { core: 0, backbone: 1, distribution: 2, access: 3 };

    placed.forEach(obj => {
        const n = obj.__nodeData;
        const l = n ? (levelMap[n.role] ?? 3) : 3;
        levels[l].push(obj);
    });

    const colGap = 160, rowGap = 220, startX = 100, startY = 80;
    [0, 1, 2, 3].forEach(lvl => {
        const row = levels[lvl];
        if (!row.length) return;
        const totalW = row.length * colGap;
        const x0     = Math.max(startX, 4000 - totalW / 2);
        row.forEach((obj, i) => {
            obj.set({ left: x0 + i * colGap, top: startY + lvl * rowGap });
            obj.setCoords();
        });
    });

    refreshEdges();
    canvas.requestRenderAll();
    schedAutoSave();
    pushHistory();
}

/* ═══════════════════════════════════════════════════════
   FIT TO SCREEN
═══════════════════════════════════════════════════════ */
document.getElementById('btn-fit').addEventListener('click', fitCanvas);

function fitCanvas() {
    const objs = canvas.getObjects().filter(o => o.__nodeId);
    if (!objs.length) return;

    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    objs.forEach(o => {
        const b = o.getBoundingRect(true);
        minX = Math.min(minX, b.left); minY = Math.min(minY, b.top);
        maxX = Math.max(maxX, b.left + b.width); maxY = Math.max(maxY, b.top + b.height);
    });

    const pad = 60;
    const w = maxX - minX + pad * 2;
    const h = maxY - minY + pad * 2;
    const z = Math.min(canvas.width / w, canvas.height / h, 2);

    canvas.setZoom(z);
    canvas.setViewportTransform([z, 0, 0, z,
        -(minX - pad) * z,
        -(minY - pad) * z,
    ]);
    document.getElementById('stat-zoom').textContent = 'Zoom: ' + Math.round(z * 100) + '%';
    canvas.requestRenderAll();
}

/* ═══════════════════════════════════════════════════════
   PROPERTIES PANEL
═══════════════════════════════════════════════════════ */
function showIdlePanel() {
    document.getElementById('props-idle').style.display = '';
    document.getElementById('props-node').style.display = 'none';
    // Sync counters
    document.getElementById('idle-nodes').textContent = Object.keys(placedNodes).length;
    document.getElementById('idle-edges').textContent = connObjects.length;
}

canvas.on('selection:created', updatePropsPanel);
canvas.on('selection:updated', updatePropsPanel);
canvas.on('selection:cleared', showIdlePanel);

function updatePropsPanel() {
    const obj   = canvas.getActiveObject();
    if (!obj) { showIdlePanel(); return; }

    document.getElementById('props-idle').style.display = 'none';
    const panel = document.getElementById('props-node');
    panel.style.display = '';

    if (obj.__nodeData) {
        const n = obj.__nodeData;
        const color = n.is_stacked ? '#7C3AED' : (ROLE_COLORS[n.role] || '#16A34A');
        panel.innerHTML = `
            <div class="prop-section">
                <div class="prop-section-title">Switch</div>
                <div class="prop-row"><span class="prop-key">Nombre</span>
                    <span class="prop-val" style="font-weight:600">${n.label}</span></div>
                <div class="prop-row"><span class="prop-key">Rol</span>
                    <span class="prop-badge" style="background:${color}">${ROLE_LABELS[n.role] || n.role}</span></div>
                <div class="prop-row"><span class="prop-key">IP</span>
                    <span class="prop-val">${n.ip || '—'}</span></div>
                <div class="prop-row"><span class="prop-key">Modelo</span>
                    <span class="prop-val">${n.model || '—'}</span></div>
                <div class="prop-row"><span class="prop-key">MAC</span>
                    <span class="prop-val" style="font-size:10px">${n.mac || '—'}</span></div>
                <div class="prop-row"><span class="prop-key">Puertos</span>
                    <span class="prop-val">${n.port_count || '—'}</span></div>
                <div class="prop-row"><span class="prop-key">Stack</span>
                    <span class="prop-val">${n.is_stacked ? 'Sí' : 'No'}</span></div>
            </div>
            <div class="prop-section">
                <div class="prop-section-title">Posición</div>
                <div class="prop-pos">
                    <div><label>X</label>
                        <input type="number" id="px" value="${Math.round(obj.left)}" onchange="moveObj('left',this.value)"></div>
                    <div><label>Y</label>
                        <input type="number" id="py" value="${Math.round(obj.top)}" onchange="moveObj('top',this.value)"></div>
                </div>
            </div>`;
    } else if (obj.__edgeData) {
        const e = obj.__edgeData;
        const fromNode = allNodes.find(n => n.id === e.from);
        const toNode   = allNodes.find(n => n.id === e.to);
        panel.innerHTML = `
            <div class="prop-section">
                <div class="prop-section-title">Conexión</div>
                <div class="prop-row"><span class="prop-key">Origen</span>
                    <span class="prop-val">${fromNode?.label || e.from}</span></div>
                <div class="prop-row"><span class="prop-key">Puerto</span>
                    <span class="prop-val">${e.src_port || '—'}</span></div>
                <div class="prop-row"><span class="prop-key">Destino</span>
                    <span class="prop-val">${toNode?.label || e.to}</span></div>
                <div class="prop-row"><span class="prop-key">Puerto</span>
                    <span class="prop-val">${e.dst_port || '—'}</span></div>
                <div class="prop-row"><span class="prop-key">Inter-área</span>
                    <span class="prop-val">${e.inter_area ? 'Sí (naranja)' : 'No (azul)'}</span></div>
                ${e.num_vlans ? `<div class="prop-row"><span class="prop-key">VLANs</span>
                    <span class="prop-val">${e.num_vlans}</span></div>` : ''}
            </div>`;
    } else {
        panel.innerHTML = `<div class="prop-section">
            <div class="prop-section-title">Objeto</div>
            <div class="prop-row"><span class="prop-key">Tipo</span>
                <span class="prop-val">${obj.type || 'desconocido'}</span></div>
        </div>`;
    }
}


function moveObj(prop, val) {
    const obj = canvas.getActiveObject();
    if (obj) { obj.set(prop, parseFloat(val)); obj.setCoords(); refreshEdges(); canvas.requestRenderAll(); }
}

canvas.on('object:modified', () => { updatePropsPanel(); schedAutoSave(); });

/* ═══════════════════════════════════════════════════════
   HISTORY (Undo / Redo)
═══════════════════════════════════════════════════════ */
const histStack = [], futureStack = [];
let histLock = false;

function pushHistory() {
    if (histLock) return;
    histStack.push(buildSaveJson());
    if (histStack.length > 40) histStack.shift();
    futureStack.length = 0;
}
function undo() {
    if (histStack.length < 2) return;
    futureStack.push(histStack.pop());
    histLock = true;
    restoreCanvas(histStack[histStack.length - 1]);
    histLock = false;
}
function redo() {
    if (!futureStack.length) return;
    const state = futureStack.pop();
    histStack.push(state);
    histLock = true;
    restoreCanvas(state);
    histLock = false;
}
document.getElementById('btn-undo').addEventListener('click', undo);
document.getElementById('btn-redo').addEventListener('click', redo);

/* ═══════════════════════════════════════════════════════
   SAVE / RESTORE
═══════════════════════════════════════════════════════ */
let autosaveT = null;
function schedAutoSave() { clearTimeout(autosaveT); autosaveT = setTimeout(saveNow, AUTOSAVE_MS); }

function buildSaveJson() {
    const nodes = [], edges = [], labels = [];

    canvas.getObjects().forEach(obj => {
        if (obj.__nodeId) {
            nodes.push({
                nodeId: obj.__nodeId,
                x: Math.round(obj.left),
                y: Math.round(obj.top),
            });
        } else if (obj.__edgeId) {
            edges.push({ edgeId: obj.__edgeId });
        } else if (obj.__isLabel) {
            labels.push({
                text: obj.text, x: Math.round(obj.left), y: Math.round(obj.top),
                fontSize: obj.fontSize, fill: obj.fill,
            });
        }
    });

    // Also save manual edges (not from BD)
    const manualEdges = allEdges.filter(e => e.id.startsWith('manual_'));

    return { version: '2.0', nodes, edges, labels, manualEdges };
}

function saveNow() {
    const json = buildSaveJson();
    fetch(SAVE_URL, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        body: JSON.stringify({ canvas_json: JSON.stringify(json) }),
    }).then(r => r.json()).then(d => {
        if (d.ok) flashSaved();
    }).catch(console.error);
}

function flashSaved() {
    const b = document.getElementById('autosave-badge');
    b.style.opacity = '1';
    setTimeout(() => b.style.opacity = '0', 2500);
}

async function restoreCanvas(json) {
    if (!json || !json.nodes) return;

    // Add manual edges from saved state
    if (json.manualEdges?.length) {
        json.manualEdges.forEach(e => {
            if (!allEdges.find(ae => ae.id === e.id)) allEdges.push(e);
        });
    }

    // Draw nodes in saved positions
    const ps = json.nodes.map(saved => {
        const node = allNodes.find(n => n.id === saved.nodeId);
        if (!node || placedNodes[saved.nodeId]) return Promise.resolve();
        return createSwitchNode(node, saved.x, saved.y);
    });

    await Promise.all(ps);

    // Draw edges (BD + manual)
    json.edges?.forEach(saved => {
        const edge = allEdges.find(e => e.id === saved.edgeId);
        if (edge) drawEdge(edge);
    });

    // Draw all possible edges among placed nodes (BD edges auto-draw)
    allEdges.forEach(e => {
        if (placedNodes[e.from] && placedNodes[e.to]) drawEdge(e);
    });

    // Restore text labels
    json.labels?.forEach(lbl => {
        const txt = new fabric.IText(lbl.text, {
            left: lbl.x, top: lbl.y, fontSize: lbl.fontSize || 13,
            fill: lbl.fill || '#CBD5E1',
            fontFamily: 'Arial',
            __isLabel: true,
        });
        canvas.add(txt);
    });

    canvas.requestRenderAll();
    updateStats();
    pushHistory();
}

/* ═══════════════════════════════════════════════════════
   KEYBOARD SHORTCUTS
═══════════════════════════════════════════════════════ */
window.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); saveNow(); }
    if (e.ctrlKey && e.key === 'z') { e.preventDefault(); undo(); }
    if (e.ctrlKey && e.key === 'y') { e.preventDefault(); redo(); }
    if (e.key === 'v' || e.key === 'V') setTool('select');
    if (e.key === 'c' || e.key === 'C') setTool('connect');
    if (e.key === 't' || e.key === 'T') setTool('text');
    if ((e.key === 'Delete' || e.key === 'Backspace') && !e.ctrlKey) {
        const active = canvas.getActiveObjects();
        if (!active.length) return;
        active.forEach(obj => {
            if (obj.__nodeId) delete placedNodes[obj.__nodeId];
            if (obj.__edgeId) {
                // Also remove the port label
                if (obj.__portLabel) canvas.remove(obj.__portLabel);
                connObjects = connObjects.filter(c => c !== obj);
            }
            canvas.remove(obj);
        });
        canvas.discardActiveObject();
        canvas.requestRenderAll();
        updateStats();
        schedAutoSave();
        pushHistory();
    }
});

/* ═══════════════════════════════════════════════════════
   EXPORT
═══════════════════════════════════════════════════════ */
const expBtn  = document.getElementById('btn-export');
const expMenu = document.getElementById('export-menu');

expBtn.addEventListener('click', e => {
    const r = expBtn.getBoundingClientRect();
    expMenu.style.top   = (r.bottom + 6) + 'px';
    expMenu.style.right = (window.innerWidth - r.right) + 'px';
    expMenu.style.display = expMenu.style.display === 'block' ? 'none' : 'block';
    e.stopPropagation();
});
document.addEventListener('click', () => { expMenu.style.display = 'none'; });

function exportAs(fmt) {
    expMenu.style.display = 'none';
    if (fmt === 'png-client') {
        // High-res PNG via Fabric multiplier
        const dataUrl = canvas.toDataURL({ format: 'png', multiplier: 2 });
        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = '{{ addslashes($project->name) }}.png';
        a.click();
        return;
    }
    if (fmt === 'svg') {
        exportSvgEmbedded();
        return;
    }
    if (fmt === 'pdf') {
        const { jsPDF } = window.jspdf;
        const img = canvas.toDataURL({ format: 'jpeg', quality: 0.85, multiplier: 1 });
        const pdf = new jsPDF({ orientation: 'landscape', unit: 'px', format: [canvas.width, canvas.height] });
        pdf.addImage(img, 'JPEG', 0, 0, canvas.width, canvas.height);
        pdf.save('{{ addslashes($project->name) }}.pdf');
    }
}

async function exportSvgEmbedded() {
    const fabImages = canvas.getObjects().filter(o => o.type === 'image');
    const restores  = [];
    for (const fab of fabImages) {
        const el = fab.getElement();
        if (!el) continue;
        try {
            const oc = document.createElement('canvas');
            oc.width  = el.naturalWidth  || el.width;
            oc.height = el.naturalHeight || el.height;
            oc.getContext('2d').drawImage(el, 0, 0);
            const dataUrl = oc.toDataURL('image/png');
            restores.push({ fab, oldSrc: fab.getSrc?.() });
            await new Promise(res => fab.setSrc(dataUrl, res, { crossOrigin: null }));
        } catch (_) {}
    }
    const svg = canvas.toSVG();
    for (const { fab, oldSrc } of restores) {
        if (oldSrc) fab.setSrc(oldSrc, () => {}, { crossOrigin: 'anonymous' });
    }
    const blob = new Blob([svg], { type: 'image/svg+xml' });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = '{{ addslashes($project->name) }}.svg';
    a.click();
    URL.revokeObjectURL(a.href);
}

/* ═══════════════════════════════════════════════════════
   STATS
═══════════════════════════════════════════════════════ */
function updateStats() {
    const n = Object.keys(placedNodes).length;
    const e = connObjects.length;
    document.getElementById('stat-nodes').textContent = 'Nodos: ' + n;
    document.getElementById('stat-edges').textContent = 'Conexiones: ' + e;
    // Sync idle panel counters if visible
    const idleN = document.getElementById('idle-nodes');
    const idleE = document.getElementById('idle-edges');
    if (idleN) idleN.textContent = n;
    if (idleE) idleE.textContent = e;
}

/* ═══════════════════════════════════════════════════════
   SAVE BUTTON
═══════════════════════════════════════════════════════ */
document.getElementById('btn-save').addEventListener('click', saveNow);

/* ═══════════════════════════════════════════════════════
   SHORTCUTS MODAL
═══════════════════════════════════════════════════════ */
document.getElementById('btn-shortcuts').addEventListener('click', () => {
    const overlay = document.getElementById('shortcuts-overlay');
    overlay.style.display = overlay.style.display === 'flex' ? 'none' : 'flex';
});
document.getElementById('shortcuts-overlay').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
// Also close with Escape
window.addEventListener('keydown', ev => {
    if (ev.key === 'Escape') document.getElementById('shortcuts-overlay').style.display = 'none';
});

/* ═══════════════════════════════════════════════════════
   PALETTE DRAG → CANVAS (custom switch nodes)
═══════════════════════════════════════════════════════ */
let _dragRole = null, _dragIcon = null;

document.querySelectorAll('.pal-item').forEach(el => {
    el.addEventListener('dragstart', ev => {
        _dragRole = el.dataset.role;
        _dragIcon = el.dataset.icon;
        ev.dataTransfer.effectAllowed = 'copy';
        ev.dataTransfer.setData('text/plain', 'pal:' + _dragRole);
    });
});

const canvasArea = document.getElementById('canvas-area');
canvasArea.addEventListener('dragover', ev => { ev.preventDefault(); ev.dataTransfer.dropEffect = 'copy'; });
canvasArea.addEventListener('drop', ev => {
    ev.preventDefault();
    if (!ev.dataTransfer.getData('text/plain').startsWith('pal:')) return;

    // Convert screen coords → canvas coords
    const rect = canvasArea.getBoundingClientRect();
    const vpt  = canvas.viewportTransform;
    const z    = canvas.getZoom();
    const cx   = (ev.clientX - rect.left  - vpt[4]) / z;
    const cy   = (ev.clientY - rect.top   - vpt[5]) / z;

    const customId = 'custom_' + Date.now();
    const customNode = {
        id:         customId,
        label:      'Nuevo switch',
        ip:         '',
        role:       _dragRole || 'access',
        icon:       _dragIcon || 'access_switch',
        is_stacked: false,
        model:      '',
        mac:        '',
        port_count: '',
        batch_id:   null,
        __isCustom: true,
    };
    allNodes.push(customNode);

    createSwitchNode(customNode, cx, cy).then(grp => {
        // Make label editable on double-click
        grp.__isCustom = true;
        canvas.setActiveObject(grp);
        canvas.requestRenderAll();
        document.getElementById('canvas-hint').style.display = 'none';
        updateStats();
        schedAutoSave();
        pushHistory();
    });
});

/* ═══════════════════════════════════════════════════════
   BOOT
═══════════════════════════════════════════════════════ */
window.addEventListener('load', () => {
    loadGraph();
    pushHistory();
});
</script>
</body>
</html>
