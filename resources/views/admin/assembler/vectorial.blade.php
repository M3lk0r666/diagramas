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
(function(){
    const d=Object.getOwnPropertyDescriptor(CanvasRenderingContext2D.prototype,'textBaseline');
    if(d&&d.set) Object.defineProperty(CanvasRenderingContext2D.prototype,'textBaseline',{
        get:d.get,set:function(v){d.set.call(this,v==='alphabetical'?'alphabetic':v);},configurable:true
    });
})();
</script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{display:flex;flex-direction:column;height:100vh;overflow:hidden;background:#F1F5F9;font-family:'Inter',system-ui,sans-serif;color:#1E293B}

#toolbar{display:flex;align-items:center;gap:6px;height:48px;padding:0 12px;background:#FFFFFF;border-bottom:1px solid #E2E8F0;flex-shrink:0;z-index:10;box-shadow:0 1px 4px rgba(0,0,0,.06)}
#toolbar .sep{width:1px;height:24px;background:#E2E8F0;margin:0 4px}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;border:none;background:transparent;color:#64748B;cursor:pointer;font-size:13px;transition:background .15s,color .15s;white-space:nowrap}
.tb-btn:hover{background:#F1F5F9;color:#1E293B}
.tb-btn.active{background:#3B82F6;color:#fff}
.tb-btn i{font-size:16px}
#project-name{font-size:13px;font-weight:600;color:#475569;margin-right:6px}
#autosave-badge{font-size:11px;color:#16A34A;opacity:0;transition:opacity .5s;margin-left:4px}
.tb-spacer{flex:1}

#export-menu{position:fixed;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;padding:4px;z-index:9999;display:none;min-width:160px;box-shadow:0 8px 24px rgba(0,0,0,.1)}
#export-menu button{display:flex;align-items:center;gap:8px;width:100%;padding:7px 12px;border:none;background:transparent;color:#475569;cursor:pointer;border-radius:5px;font-size:13px;text-align:left}
#export-menu button:hover{background:#F1F5F9;color:#1E293B}

#main-row{display:flex;flex:1;overflow:hidden}

#sidebar{width:280px;flex-shrink:0;background:#FFFFFF;border-right:1px solid #E2E8F0;display:flex;flex-direction:column;overflow:hidden}
#sidebar-header{padding:10px 12px 6px;border-bottom:1px solid #E2E8F0;flex-shrink:0}
#sidebar-header h3{font-size:12px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em}
#sidebar-search{width:100%;margin-top:6px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:5px 8px;color:#1E293B;font-size:12px;outline:none}
#sidebar-search:focus{border-color:#3B82F6}
#sidebar-body{flex:1;overflow-y:auto;padding:6px;min-height:0}
#sidebar-body::-webkit-scrollbar,#icon-palette::-webkit-scrollbar,#props-body::-webkit-scrollbar,#props-legend::-webkit-scrollbar{width:4px}
#sidebar-body::-webkit-scrollbar-thumb,#icon-palette::-webkit-scrollbar-thumb,#props-body::-webkit-scrollbar-thumb,#props-legend::-webkit-scrollbar-thumb{background:#E2E8F0;border-radius:2px}

.batch-group{margin-bottom:4px}
.batch-header{display:flex;align-items:center;gap:6px;padding:6px 8px;border-radius:7px;cursor:pointer;background:#F8FAFC;border:1px solid #E2E8F0;user-select:none}
.batch-header:hover{background:#EFF6FF}
.batch-chevron{font-size:14px;color:#94A3B8;transition:transform .2s;margin-left:auto}
.batch-chevron.open{transform:rotate(90deg)}
.batch-name{font-size:12px;font-weight:600;color:#475569;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.batch-count{font-size:10px;color:#94A3B8;background:#F1F5F9;border-radius:10px;padding:1px 6px;border:1px solid #E2E8F0}
.batch-load-btn{font-size:10px;background:#3B82F6;color:#fff;border:none;border-radius:4px;padding:2px 7px;cursor:pointer;white-space:nowrap;flex-shrink:0}
.batch-load-btn:hover{background:#2563EB}
.batch-load-btn.loaded{background:#16A34A}
.switch-list{display:none;padding:4px 0 2px 10px}
.switch-list.open{display:block}
.switch-item{display:flex;align-items:center;gap:6px;padding:4px 6px;border-radius:5px;cursor:grab;font-size:11px;color:#64748B;border:1px solid transparent;margin-bottom:2px;transition:background .1s}
.switch-item:hover{background:#EFF6FF;border-color:#BFDBFE;color:#1E293B}
.switch-item:active{cursor:grabbing}
.switch-role-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.switch-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.switch-ip{font-size:10px;color:#94A3B8;margin-left:auto;flex-shrink:0}

#icon-palette{padding:8px 10px 6px;border-bottom:1px solid #E2E8F0;flex-shrink:0;max-height:52%;overflow-y:auto}
.pal-header{font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;display:flex;align-items:center}
.pal-header span{margin-left:auto;font-size:10px;color:#B0BEC5;font-weight:400;text-transform:none}
.pal-cat{font-size:9px;font-weight:700;color:#3B82F6;text-transform:uppercase;letter-spacing:.08em;margin:7px 0 3px;padding-bottom:2px;border-bottom:1px solid #EFF6FF}
.pal-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;margin-bottom:2px}
.pal-item{display:flex;flex-direction:column;align-items:center;gap:2px;padding:5px 2px;border-radius:6px;border:1px solid #E2E8F0;background:#F8FAFC;cursor:grab;transition:background .12s,border-color .12s;user-select:none}
.pal-item:hover{background:#EFF6FF;border-color:#93C5FD}
.pal-item:active{cursor:grabbing;background:#DBEAFE}
.pal-item img{width:26px;height:26px;pointer-events:none;object-fit:contain}
.pal-item span{font-size:8.5px;color:#64748B;text-align:center;pointer-events:none;line-height:1.2}

#port-dialog{display:none;position:fixed;background:#FFFFFF;border:1px solid #93C5FD;border-radius:10px;padding:14px;z-index:9999;box-shadow:0 8px 32px rgba(59,130,246,.15);min-width:220px}
#port-dialog h4{font-size:12px;font-weight:700;color:#1E293B;margin-bottom:10px}
#port-dialog label{display:block;font-size:11px;color:#64748B;margin-bottom:2px}
#port-dialog input{width:100%;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:5px;padding:5px 8px;color:#1E293B;font-size:12px;outline:none;margin-bottom:8px}
#port-dialog input:focus{border-color:#3B82F6}
#port-dialog .pd-actions{display:flex;gap:6px;justify-content:flex-end;margin-top:4px}
#port-dialog .pd-ok{background:#3B82F6;color:#fff;border:none;border-radius:5px;padding:5px 14px;font-size:12px;cursor:pointer;font-weight:600}
#port-dialog .pd-cancel{background:transparent;color:#64748B;border:1px solid #E2E8F0;border-radius:5px;padding:5px 10px;font-size:12px;cursor:pointer}

#canvas-area{flex:1;position:relative;overflow:hidden;background:#F8FAFC;background-image:radial-gradient(circle,#CBD5E1 1px,transparent 1px);background-size:28px 28px}
#canvas-wrap{position:absolute;inset:0}
canvas{position:absolute;top:0;left:0}
#canvas-hint{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;color:#94A3B8;pointer-events:none;user-select:none}
#canvas-hint i{font-size:48px;display:block;margin-bottom:8px;color:#CBD5E1}
#canvas-hint p{font-size:13px}

#props-panel{width:300px;flex-shrink:0;background:#FFFFFF;border-left:1px solid #E2E8F0;display:flex;flex-direction:column;overflow:hidden}
#props-header{padding:10px 14px;border-bottom:1px solid #E2E8F0;flex-shrink:0}
#props-header h3{font-size:12px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em}
#props-body{flex:1;overflow-y:auto;padding:12px;min-height:0}
.props-empty{color:#94A3B8;font-size:12px;text-align:center;padding-top:40px}
.prop-section{margin-bottom:14px}
.prop-section-title{font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
.prop-row{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:5px}
.prop-key{font-size:11px;color:#94A3B8;flex-shrink:0;max-width:80px}
.prop-val{font-size:11px;color:#334155;word-break:break-all;text-align:right}
.prop-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:600;color:#fff}
.prop-pos{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:4px}
.prop-pos label{font-size:10px;color:#94A3B8}
.prop-pos input,.prop-input{width:100%;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:4px;padding:4px 8px;color:#1E293B;font-size:12px;outline:none}
.prop-pos input:focus,.prop-input:focus{border-color:#3B82F6}

#props-legend{border-top:1px solid #E2E8F0;flex-shrink:0;max-height:40%;overflow-y:auto;padding:8px 14px 10px}
#props-legend h4{font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;position:sticky;top:0;background:#fff;padding:4px 0 2px}
.legend-section-title{font-size:9px;font-weight:700;color:#3B82F6;text-transform:uppercase;letter-spacing:.06em;margin:6px 0 3px}
.legend-item{display:flex;align-items:center;gap:6px;font-size:11px;color:#64748B;margin-bottom:2px}
.legend-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.legend-icon{width:14px;height:14px;flex-shrink:0;object-fit:contain;opacity:.75}

#stats-bar{display:flex;gap:16px;align-items:center;padding:4px 12px;background:#FFFFFF;border-top:1px solid #E2E8F0;flex-shrink:0;font-size:11px;color:#94A3B8}

#label-editor-box{display:none;position:fixed;z-index:9999;background:#FFFFFF;border:2px solid #3B82F6;border-radius:8px;padding:10px;box-shadow:0 4px 20px rgba(59,130,246,.2);min-width:220px}
#label-editor-box label{display:block;font-size:10px;color:#64748B;margin-bottom:3px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
#label-editor-box label+label{margin-top:7px}
#label-editor-input,#label-editor-ip{width:100%;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:5px;padding:5px 8px;color:#1E293B;font-size:12px;outline:none}
#label-editor-input:focus,#label-editor-ip:focus{border-color:#3B82F6}
#label-editor-actions{display:flex;gap:4px;justify-content:flex-end;margin-top:7px}
#label-editor-ok{background:#3B82F6;color:#fff;border:none;border-radius:4px;padding:4px 14px;font-size:11px;cursor:pointer;font-weight:600}
#label-editor-cancel{background:transparent;color:#64748B;border:1px solid #E2E8F0;border-radius:4px;padding:4px 9px;font-size:11px;cursor:pointer}
</style>
</head>
<body>

<div id="toolbar">
    <a href="{{ route('admin.assembler.index') }}" class="tb-btn"><i class="ri-arrow-left-line"></i></a>
    <span id="project-name">{{ $project->name }}</span>
    <span id="autosave-badge"><i class="ri-check-line"></i> Guardado</span>
    <div class="sep"></div>
    <button class="tb-btn active" id="btn-select"><i class="ri-cursor-line"></i> Seleccionar</button>
    <button class="tb-btn" id="btn-conn"><i class="ri-share-line"></i> Conectar</button>
    <button class="tb-btn" id="btn-text"><i class="ri-text"></i> Texto</button>
    <div class="sep"></div>
    <button class="tb-btn" id="btn-layout"><i class="ri-layout-top-line"></i> Layout</button>
    <button class="tb-btn" id="btn-fit"><i class="ri-fullscreen-line"></i> Ajustar</button>
    <button class="tb-btn" id="btn-undo"><i class="ri-arrow-go-back-line"></i></button>
    <button class="tb-btn" id="btn-redo"><i class="ri-arrow-go-forward-line"></i></button>
    <button class="tb-btn" id="btn-delete" title="Eliminar selección (Supr)" style="color:#EF4444"><i class="ri-delete-bin-line"></i> Eliminar</button>
    <div class="sep"></div>
    <button class="tb-btn" id="btn-save"><i class="ri-save-line"></i> Guardar</button>
    <div class="tb-spacer"></div>
    <button class="tb-btn" id="btn-export"><i class="ri-download-line"></i> Exportar <i class="ri-arrow-down-s-line"></i></button>
    <div id="export-menu">
        <button onclick="exportAs('png-client')"><i class="ri-image-line"></i> PNG</button>
        <button onclick="exportAs('svg')"><i class="ri-file-code-line"></i> SVG</button>
        <button onclick="exportAs('pdf')"><i class="ri-file-pdf-line"></i> PDF</button>
    </div>
    <button class="tb-btn" id="btn-shortcuts"><i class="ri-keyboard-line"></i></button>
</div>

<div id="shortcuts-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9998;align-items:center;justify-content:center">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:24px;min-width:360px;color:#334155;box-shadow:0 16px 48px rgba(0,0,0,.12)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="font-size:14px;font-weight:700;color:#1E293B">Atajos de teclado</h3>
            <button onclick="document.getElementById('shortcuts-overlay').style.display='none'" style="background:none;border:none;color:#94A3B8;cursor:pointer;font-size:20px">×</button>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <tr><td style="padding:4px 0;color:#3B82F6;font-weight:600">V / C / T</td><td style="color:#475569">Seleccionar / Conectar / Texto</td></tr>
            <tr><td style="padding:4px 0;color:#3B82F6;font-weight:600">Espacio + drag</td><td style="color:#475569">Paneo del canvas</td></tr>
            <tr><td style="padding:4px 0;color:#3B82F6;font-weight:600">Rueda</td><td style="color:#475569">Zoom</td></tr>
            <tr><td style="padding:4px 0;color:#3B82F6;font-weight:600">Ctrl+Z / Ctrl+Y</td><td style="color:#475569">Deshacer / Rehacer</td></tr>
            <tr><td style="padding:4px 0;color:#3B82F6;font-weight:600">Ctrl+S</td><td style="color:#475569">Guardar</td></tr>
            <tr><td style="padding:4px 0;color:#3B82F6;font-weight:600">Supr / ⌫</td><td style="color:#475569">Eliminar selección</td></tr>
            <tr><td style="padding:4px 0;color:#3B82F6;font-weight:600">Doble clic</td><td style="color:#475569">Editar nombre del nodo</td></tr>
        </table>
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid #E2E8F0;font-size:11px;color:#94A3B8">
            💡 Arrastra elementos desde el panel izquierdo, o usa <strong>Cargar área</strong>.
        </div>
    </div>
</div>

<div id="main-row">
    <div id="sidebar">
        <div id="sidebar-header">
            <h3><i class="ri-node-tree"></i> Áreas — {{ $project->client->name ?? 'Cliente' }}</h3>
            <input type="text" id="sidebar-search" placeholder="Buscar switch…">
        </div>
        <div id="icon-palette">
            <div class="pal-header"><i class="ri-drag-move-line" style="margin-right:5px"></i> Elementos <span>arrastra al canvas</span></div>
            <div class="pal-cat">Switches</div>
<div class="pal-grid">
<div class="pal-item" draggable="true" data-role="core" data-icon="core_switch" title="Core"><img src="/storage/media/core_switch.svg" alt="Core"><span>Core</span></div>
<div class="pal-item" draggable="true" data-role="backbone" data-icon="backbone_switch" title="Backbone"><img src="/storage/media/backbone_switch.svg" alt="Backbone"><span>Backbone</span></div>
<div class="pal-item" draggable="true" data-role="distribution" data-icon="dist_switch" title="Distrib."><img src="/storage/media/dist_switch.svg" alt="Distrib."><span>Distrib.</span></div>
<div class="pal-item" draggable="true" data-role="access" data-icon="access_switch" title="Acceso"><img src="/storage/media/access_switch.svg" alt="Acceso"><span>Acceso</span></div>
<div class="pal-item" draggable="true" data-role="stack" data-icon="stack_switch" title="Stack"><img src="/storage/media/stack_switch.svg" alt="Stack"><span>Stack</span></div>
</div>
<div class="pal-cat">Red y Seguridad</div>
<div class="pal-grid">
<div class="pal-item" draggable="true" data-role="backbone" data-icon="router" title="Router"><img src="/storage/media/router.svg" alt="Router"><span>Router</span></div>
<div class="pal-item" draggable="true" data-role="backbone" data-icon="firewall" title="Firewall"><img src="/storage/media/firewall.svg" alt="Firewall"><span>Firewall</span></div>
<div class="pal-item" draggable="true" data-role="distribution" data-icon="load_balancer" title="Load Bal."><img src="/storage/media/load_balancer.svg" alt="Load Bal."><span>Load Bal.</span></div>
<div class="pal-item" draggable="true" data-role="backbone" data-icon="vpn_conector" title="VPN"><img src="/storage/media/vpn_conector.svg" alt="VPN"><span>VPN</span></div>
<div class="pal-item" draggable="true" data-role="core" data-icon="internet" title="Internet"><img src="/storage/media/internet.svg" alt="Internet"><span>Internet</span></div>
</div>
<div class="pal-cat">Inalámbrico</div>
<div class="pal-grid">
<div class="pal-item" draggable="true" data-role="access" data-icon="ap" title="Access Point"><img src="/storage/media/ap.svg" alt="Access Point"><span>Access Point</span></div>
<div class="pal-item" draggable="true" data-role="access" data-icon="wireless_controler" title="Ctrl. WiFi"><img src="/storage/media/wireless_controler.svg" alt="Ctrl. WiFi"><span>Ctrl. WiFi</span></div>
</div>
<div class="pal-cat">Servidores</div>
<div class="pal-grid">
<div class="pal-item" draggable="true" data-role="backbone" data-icon="server_rack" title="Rack"><img src="/storage/media/server_rack.svg" alt="Rack"><span>Rack</span></div>
<div class="pal-item" draggable="true" data-role="backbone" data-icon="server_torre" title="Servidor"><img src="/storage/media/server_torre.svg" alt="Servidor"><span>Servidor</span></div>
<div class="pal-item" draggable="true" data-role="core" data-icon="network_cloud" title="Nube Red"><img src="/storage/media/network_cloud.svg" alt="Nube Red"><span>Nube Red</span></div>
<div class="pal-item" draggable="true" data-role="backbone" data-icon="storage" title="Storage"><img src="/storage/media/storage.svg" alt="Storage"><span>Storage</span></div>
<div class="pal-item" draggable="true" data-role="access" data-icon="modem" title="Módem"><img src="/storage/media/modem.svg" alt="Módem"><span>Módem</span></div>
</div>
<div class="pal-cat">Dispositivos</div>
<div class="pal-grid">
<div class="pal-item" draggable="true" data-role="access" data-icon="laptop" title="Laptop"><img src="/storage/media/laptop.svg" alt="Laptop"><span>Laptop</span></div>
<div class="pal-item" draggable="true" data-role="access" data-icon="pc_desktop" title="PC Desktop"><img src="/storage/media/pc_desktop.svg" alt="PC Desktop"><span>PC Desktop</span></div>
<div class="pal-item" draggable="true" data-role="access" data-icon="ip_phone" title="IP Phone"><img src="/storage/media/ip_phone.svg" alt="IP Phone"><span>IP Phone</span></div>
<div class="pal-item" draggable="true" data-role="access" data-icon="printer" title="Impresora"><img src="/storage/media/printer.svg" alt="Impresora"><span>Impresora</span></div>
<div class="pal-item" draggable="true" data-role="access" data-icon="security_camera" title="Cámara"><img src="/storage/media/security_camera.svg" alt="Cámara"><span>Cámara</span></div>
</div>
        </div>
        <div id="sidebar-body">
            <div style="color:#94A3B8;font-size:12px;text-align:center;padding:20px"><i class="ri-loader-4-line" style="font-size:20px"></i><br>Cargando switches…</div>
        </div>
    </div>

    <div id="port-dialog">
        <h4><i class="ri-git-branch-line"></i> Puertos de conexión</h4>
        <label>Puerto origen</label><input type="text" id="pd-src" placeholder="ej. 49" autocomplete="off">
        <label>Puerto destino</label><input type="text" id="pd-dst" placeholder="ej. 1" autocomplete="off">
        <div class="pd-actions">
            <button class="pd-cancel" onclick="cancelPortDialog()">Omitir</button>
            <button class="pd-ok" onclick="confirmPortDialog()">Aceptar</button>
        </div>
    </div>

    <div id="canvas-area">
        <div id="canvas-wrap"><canvas id="c"></canvas></div>
        <div id="canvas-hint"><i class="ri-node-tree"></i><p>Arrastra elementos desde el panel izquierdo<br>o usa <strong>Cargar área</strong>.</p></div>
    </div>

    <div id="props-panel">
        <div id="props-header"><h3><i class="ri-information-line"></i> Propiedades</h3></div>
        <div id="props-body">
            <div id="props-idle">
                <div class="props-empty">Selecciona un objeto<br>para ver sus propiedades.</div>
                <div style="margin-top:20px;border-top:1px solid #E2E8F0;padding-top:14px">
                    <p style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Canvas</p>
                    <div style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:#64748B">
                        <div style="display:flex;justify-content:space-between"><span>Tamaño</span><span style="color:#1E293B;font-weight:600">8,000 × 6,000 px</span></div>
                        <div style="display:flex;justify-content:space-between"><span>Nodos</span><span id="idle-nodes" style="color:#1E293B;font-weight:600">0</span></div>
                        <div style="display:flex;justify-content:space-between"><span>Conexiones</span><span id="idle-edges" style="color:#1E293B;font-weight:600">0</span></div>
                    </div>
                </div>
                <div style="margin-top:18px;border-top:1px solid #E2E8F0;padding-top:14px">
                    <p style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Atajos</p>
                    <div style="display:flex;flex-direction:column;gap:6px;font-size:11px;color:#64748B">
                        @foreach([['Ctrl+S','Guardar'],['Ctrl+Z','Deshacer'],['Ctrl+Y','Rehacer'],['Supr / ⌫','Eliminar'],['Rueda','Zoom'],['Espacio+drag','Pan'],['V','Selección'],['C','Conector'],['Doble clic','Editar nombre']] as [$key,$desc])
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px">
                            <kbd style="background:#F1F5F9;border:1px solid #E2E8F0;border-radius:4px;padding:2px 6px;font-size:10px;color:#334155;white-space:nowrap;font-family:monospace">{{ $key }}</kbd>
                            <span style="text-align:right">{{ $desc }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div id="props-node" style="display:none"></div>
        </div>
        <div id="props-legend">
            <h4><i class="ri-bookmark-line" style="margin-right:4px"></i> Leyenda</h4>
            <div class="legend-section-title">Roles de switch</div>
            <div class="legend-item"><span class="legend-dot" style="background:#1E3A5F"></span> Core</div>
            <div class="legend-item"><span class="legend-dot" style="background:#1D4ED8"></span> Backbone</div>
            <div class="legend-item"><span class="legend-dot" style="background:#0891B2"></span> Distribución</div>
            <div class="legend-item"><span class="legend-dot" style="background:#16A34A"></span> Acceso</div>
            <div class="legend-item"><span class="legend-dot" style="background:#7C3AED"></span> Stack</div>
            <div class="legend-section-title">Conexiones</div>
            <div class="legend-item"><span style="width:22px;height:2px;background:#3B82F6;display:inline-block;border-radius:1px;flex-shrink:0"></span>&nbsp;Misma área</div>
            <div class="legend-item"><span style="width:22px;height:0;border-top:2px dashed #F97316;display:inline-block;flex-shrink:0"></span>&nbsp;Inter-área</div>
            <div class="legend-section-title">Red y Seguridad</div><div class="legend-item"><img class="legend-icon" src="/storage/media/router.svg" alt=""> Router</div><div class="legend-item"><img class="legend-icon" src="/storage/media/firewall.svg" alt=""> Firewall</div><div class="legend-item"><img class="legend-icon" src="/storage/media/load_balancer.svg" alt=""> Load Balancer</div><div class="legend-item"><img class="legend-icon" src="/storage/media/vpn_conector.svg" alt=""> VPN Connector</div><div class="legend-item"><img class="legend-icon" src="/storage/media/internet.svg" alt=""> Internet</div><div class="legend-section-title">Inalámbrico</div><div class="legend-item"><img class="legend-icon" src="/storage/media/ap.svg" alt=""> Access Point</div><div class="legend-item"><img class="legend-icon" src="/storage/media/wireless_controler.svg" alt=""> Ctrl. WiFi</div><div class="legend-section-title">Servidores</div><div class="legend-item"><img class="legend-icon" src="/storage/media/server_rack.svg" alt=""> Rack</div><div class="legend-item"><img class="legend-icon" src="/storage/media/server_torre.svg" alt=""> Servidor Torre</div><div class="legend-item"><img class="legend-icon" src="/storage/media/network_cloud.svg" alt=""> Nube Red</div><div class="legend-item"><img class="legend-icon" src="/storage/media/storage.svg" alt=""> Storage</div><div class="legend-item"><img class="legend-icon" src="/storage/media/modem.svg" alt=""> Módem</div><div class="legend-section-title">Dispositivos</div><div class="legend-item"><img class="legend-icon" src="/storage/media/laptop.svg" alt=""> Laptop</div><div class="legend-item"><img class="legend-icon" src="/storage/media/pc_desktop.svg" alt=""> PC Desktop</div><div class="legend-item"><img class="legend-icon" src="/storage/media/ip_phone.svg" alt=""> IP Phone</div><div class="legend-item"><img class="legend-icon" src="/storage/media/printer.svg" alt=""> Impresora</div><div class="legend-item"><img class="legend-icon" src="/storage/media/security_camera.svg" alt=""> Cámara IP</div>
        </div>
    </div>
</div>

<div id="stats-bar">
    <span id="stat-nodes">Nodos: 0</span>
    <span id="stat-edges">Conexiones: 0</span>
    <span id="stat-zoom">Zoom: 100%</span>
    <span style="margin-left:auto" id="stat-mode">Modo: Seleccionar</span>
</div>

<div id="label-editor-box">
    <label>Nombre del elemento</label>
    <input type="text" id="label-editor-input" autocomplete="off" placeholder="Ingresa un nombre…">
    <label>Dirección IP</label>
    <input type="text" id="label-editor-ip" autocomplete="off" placeholder="Ej. 192.168.1.1">
    <div id="label-editor-actions">
        <button id="label-editor-cancel">Cancelar</button>
        <button id="label-editor-ok">Aceptar</button>
    </div>
</div>

<script>
const CSRF      = '{{ csrf_token() }}';
const GRAPH_URL = '{{ route('admin.assembler.graph', $project) }}';
const SAVE_URL  = '{{ route('admin.assembler.update', $project) }}';
const INITIAL_JSON = @json($project->canvas_json);
const AUTOSAVE_MS  = 4000;

const ICONS = {
    core_switch: '/storage/media/core_switch.svg',
    backbone_switch: '/storage/media/backbone_switch.svg',
    dist_switch: '/storage/media/dist_switch.svg',
    access_switch: '/storage/media/access_switch.svg',
    stack_switch: '/storage/media/stack_switch.svg',
    router: '/storage/media/router.svg',
    firewall: '/storage/media/firewall.svg',
    load_balancer: '/storage/media/load_balancer.svg',
    vpn_conector: '/storage/media/vpn_conector.svg',
    internet: '/storage/media/internet.svg',
    ap: '/storage/media/ap.svg',
    wireless_controler: '/storage/media/wireless_controler.svg',
    server_rack: '/storage/media/server_rack.svg',
    server_torre: '/storage/media/server_torre.svg',
    network_cloud: '/storage/media/network_cloud.svg',
    storage: '/storage/media/storage.svg',
    modem: '/storage/media/modem.svg',
    laptop: '/storage/media/laptop.svg',
    pc_desktop: '/storage/media/pc_desktop.svg',
    ip_phone: '/storage/media/ip_phone.svg',
    printer: '/storage/media/printer.svg',
    security_camera: '/storage/media/security_camera.svg',
};

const ROLE_COLORS={core:'#1E3A5F',backbone:'#1D4ED8',distribution:'#0891B2',access:'#16A34A',stack:'#7C3AED'};
const ROLE_LABELS={core:'Core',backbone:'Backbone',distribution:'Distribución',access:'Acceso',stack:'Stack'};

const wrap=document.getElementById('canvas-wrap');
const canvas=new fabric.Canvas('c',{width:wrap.clientWidth,height:wrap.clientHeight,selection:true,preserveObjectStacking:true,renderOnAddRemove:false,skipOffscreen:true,enableRetinaScaling:false});
window.addEventListener('resize',()=>{canvas.setWidth(wrap.clientWidth);canvas.setHeight(wrap.clientHeight);canvas.requestRenderAll();});

let isPanning=false,lastPt=null,spaceDown=false,rafZoom=null;
window.addEventListener('keydown',ev=>{if(ev.code==='Space'&&!ev.target.matches('input,textarea')){if(!spaceDown){spaceDown=true;canvas.defaultCursor='grab';canvas.forEachObject(o=>{o.__ps=o.selectable;o.selectable=false;});canvas.selection=false;}ev.preventDefault();}},true);
window.addEventListener('keyup',ev=>{if(ev.code==='Space'){spaceDown=false;if(!isPanning){canvas.defaultCursor='default';canvas.forEachObject(o=>{if(o.__ps!==undefined){o.selectable=o.__ps;delete o.__ps;}});canvas.selection=(currentTool==='select');}}});
canvas.on('mouse:wheel',ev=>{const e=ev.e;e.preventDefault();if(rafZoom)return;rafZoom=requestAnimationFrame(()=>{rafZoom=null;let z=canvas.getZoom();z*=e.deltaY>0?.93:1.07;z=Math.max(.05,Math.min(5,z));canvas.zoomToPoint({x:e.offsetX,y:e.offsetY},z);document.getElementById('stat-zoom').textContent='Zoom: '+Math.round(z*100)+'%';canvas.requestRenderAll();});});
canvas.on('mouse:down',ev=>{if(ev.e.button===0&&!spaceDown&&!ev.e.altKey)return;isPanning=true;lastPt={x:ev.e.clientX,y:ev.e.clientY};canvas.defaultCursor='grabbing';canvas.skipTargetFind=true;ev.e.preventDefault();});
canvas.on('mouse:move',ev=>{if(!isPanning||!lastPt)return;const vpt=canvas.viewportTransform.slice();vpt[4]+=ev.e.clientX-lastPt.x;vpt[5]+=ev.e.clientY-lastPt.y;canvas.setViewportTransform(vpt);lastPt={x:ev.e.clientX,y:ev.e.clientY};canvas.requestRenderAll();});
canvas.on('mouse:up',()=>{isPanning=false;lastPt=null;canvas.skipTargetFind=false;canvas.defaultCursor=spaceDown?'grab':'default';});

let allNodes=[],allEdges=[],placedNodes={},connObjects=[];

function loadGraph(){
    fetch(GRAPH_URL,{headers:{Accept:'application/json','X-CSRF-TOKEN':CSRF}})
    .then(r=>r.json()).then(data=>{
        if(!data.ok){showSidebarError(data.error);return;}
        allNodes=data.nodes||[];allEdges=data.edges||[];
        renderSidebar(data.batches||[],allNodes);
        if(INITIAL_JSON&&(INITIAL_JSON.nodes||[]).length>0){restoreCanvas(INITIAL_JSON);document.getElementById('canvas-hint').style.display='none';}
    }).catch(e=>showSidebarError('Error de red: '+e.message));
}

function renderSidebar(batches,nodes){
    const body=document.getElementById('sidebar-body');body.innerHTML='';
    if(!batches.length){body.innerHTML='<div style="color:#94A3B8;font-size:12px;text-align:center;padding:20px">No hay áreas con switches.</div>';return;}
    batches.forEach(batch=>{
        const bn=nodes.filter(n=>n.batch_id===batch.id);
        const g=document.createElement('div');g.className='batch-group';
        g.innerHTML=`<div class="batch-header" onclick="toggleBatch(${batch.id})"><i class="ri-building-2-line" style="color:#94A3B8;font-size:14px"></i><span class="batch-name">${batch.name}</span><span class="batch-count">${batch.switch_count}</span><button class="batch-load-btn" id="load-btn-${batch.id}" onclick="event.stopPropagation();loadBatch(${batch.id})"><i class="ri-add-line"></i> Cargar área</button><i class="ri-arrow-right-s-line batch-chevron" id="chev-${batch.id}"></i></div><div class="switch-list" id="swlist-${batch.id}">${bn.map(n=>swHtml(n)).join('')}</div>`;
        body.appendChild(g);
    });
    setupDragFromSidebar();
}
function swHtml(node){const c=node.is_stacked?'#7C3AED':(ROLE_COLORS[node.role]||'#16A34A');return `<div class="switch-item" draggable="true" data-node-id="${node.id}" ondblclick="addNodeToCanvas('${node.id}')"><span class="switch-role-dot" style="background:${c}"></span><span class="switch-label" title="${node.label}">${node.label}</span><span class="switch-ip">${node.ip?node.ip.split('/')[0]:''}</span></div>`;}
function toggleBatch(id){document.getElementById(`swlist-${id}`)?.classList.toggle('open');document.getElementById(`chev-${id}`)?.classList.toggle('open');}
function showSidebarError(msg){document.getElementById('sidebar-body').innerHTML=`<div style="color:#EF4444;font-size:12px;padding:16px">${msg}</div>`;}
document.getElementById('sidebar-search').addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('.switch-item').forEach(el=>{el.style.display=(el.querySelector('.switch-label').textContent.toLowerCase().includes(q)||el.querySelector('.switch-ip').textContent.toLowerCase().includes(q))?'':'none';});});

function setupDragFromSidebar(){
    document.querySelectorAll('.switch-item[draggable]').forEach(el=>{el.addEventListener('dragstart',ev=>{ev.dataTransfer.setData('vec-node',el.dataset.nodeId);});});
    const area=document.getElementById('canvas-area');
    area.addEventListener('dragover',ev=>ev.preventDefault());
    area.addEventListener('drop',ev=>{ev.preventDefault();const nodeId=ev.dataTransfer.getData('vec-node');if(!nodeId)return;const rect=wrap.getBoundingClientRect(),vpt=canvas.viewportTransform,z=canvas.getZoom();addNodeToCanvas(nodeId,(ev.clientX-rect.left-vpt[4])/z,(ev.clientY-rect.top-vpt[5])/z);});
}

function loadBatch(batchId){
    const btn=document.getElementById(`load-btn-${batchId}`),nodes=allNodes.filter(n=>n.batch_id===batchId);if(!nodes.length)return;
    btn.innerHTML='<i class="ri-loader-4-line"></i> Cargando…';btn.disabled=true;
    const existing=canvas.getObjects().filter(o=>o.__nodeId);let offsetY=80;
    if(existing.length){const maxY=Math.max(...existing.map(o=>(o.top||0)+(o.height||0)*(o.scaleY||1)));offsetY=maxY+120;}
    const lv={core:[],backbone:[],distribution:[],access:[]};nodes.forEach(n=>{(lv[n.role]||lv.access).push(n);});
    const pos={};let yOff=offsetY;
    ['core','backbone','distribution','access'].forEach(role=>{const g=lv[role];if(!g.length)return;const sx=Math.max(80,(8000/2)-(g.length*140)/2);g.forEach((n,i)=>{pos[n.id]={x:sx+i*140,y:yOff};});yOff+=180;});
    Promise.all(nodes.map(n=>{if(placedNodes[n.id])return Promise.resolve();const p=pos[n.id]||{x:80,y:offsetY};return createSwitchNode(n,p.x,p.y);}))
    .then(()=>{drawEdgesForBatch(batchId);canvas.requestRenderAll();btn.innerHTML='<i class="ri-check-line"></i> Cargada';btn.classList.add('loaded');btn.disabled=false;document.getElementById('canvas-hint').style.display='none';updateStats();schedAutoSave();pushHistory();});
}

function addNodeToCanvas(nodeId,cx,cy){
    if(placedNodes[nodeId]){canvas.setActiveObject(placedNodes[nodeId]);canvas.requestRenderAll();return;}
    const node=allNodes.find(n=>n.id===nodeId);if(!node)return;
    const vpt=canvas.viewportTransform,z=canvas.getZoom();
    if(cx===undefined){cx=(canvas.width/2-vpt[4])/z;cy=(canvas.height/2-vpt[5])/z;}
    createSwitchNode(node,cx,cy).then(()=>{drawEdgesForNode(nodeId);canvas.requestRenderAll();document.getElementById('canvas-hint').style.display='none';updateStats();schedAutoSave();pushHistory();});
}

function createSwitchNode(node,x,y){
    return new Promise(resolve=>{
        const iconKey=node.is_stacked?'stack_switch':(node.icon||'access_switch');
        const iconUrl=ICONS[iconKey]||ICONS['access_switch']||'';
        const color=node.is_stacked?'#7C3AED':(ROLE_COLORS[node.role]||'#16A34A');
        const display=node.label.length>20?node.label.slice(0,18)+'…':node.label;
        const ipDisplay=(node.ip||'').split('/')[0];

        const buildGrp=img=>{
            // Escalar icono a tamaño fijo máximo
            const MAX=80;
            const imgObj=img||(new fabric.Rect({width:MAX,height:MAX,fill:'transparent'}));
            let dispW=MAX,dispH=MAX;
            if(img){const s=Math.min(MAX/(img.width||MAX),MAX/(img.height||MAX));img.scale(s);dispW=(img.width||MAX)*s;dispH=(img.height||MAX)*s;}
            imgObj.set({left:0,top:0,originX:'center',originY:'center'});
            // Nombre justo debajo del ícono
            const lbl=new fabric.Text(display,{left:0,top:dispH/2+5,fontSize:10,fontFamily:'Arial',fill:'#1E293B',textAlign:'center',originX:'center',originY:'top',fontWeight:'700',backgroundColor:'rgba(255,255,255,0.85)',padding:1});
            lbl.__isNodeLabel=true;
            // IP debajo del nombre
            const ipLbl=new fabric.Text(ipDisplay,{left:0,top:dispH/2+19,fontSize:9,fontFamily:'Arial',fill:'#475569',textAlign:'center',originX:'center',originY:'top',opacity:ipDisplay?1:0,backgroundColor:'rgba(255,255,255,0.85)',padding:1});
            ipLbl.__isNodeIp=true;
            // Punto de rol (esquina superior derecha del ícono)
            const dot=new fabric.Circle({left:dispW/2,top:-dispH/2,radius:4,fill:color,originX:'center',originY:'center',stroke:'#fff',strokeWidth:1.5});
            const grp=new fabric.Group([imgObj,lbl,ipLbl,dot],{left:x,top:y,originX:'center',originY:'center',hasControls:false,hasBorders:true,borderColor:'#3B82F6',borderScaleFactor:2,cornerColor:'#3B82F6',cornerSize:6,transparentCorners:false,padding:6,__nodeId:node.id,__nodeData:node});
            canvas.add(grp);placedNodes[node.id]=grp;resolve(grp);
        };
        if(iconUrl)fabric.Image.fromURL(iconUrl,img=>buildGrp(img),{crossOrigin:'anonymous'});
        else buildGrp(null);
    });
}

function drawEdgesForBatch(batchId){const ids=new Set(allNodes.filter(n=>n.batch_id===batchId).map(n=>n.id));allEdges.filter(e=>(ids.has(e.from)||ids.has(e.to))&&placedNodes[e.from]&&placedNodes[e.to]).forEach(e=>drawEdge(e));}
function drawEdgesForNode(nodeId){allEdges.filter(e=>(e.from===nodeId||e.to===nodeId)&&placedNodes[e.from]&&placedNodes[e.to]).forEach(e=>drawEdge(e));}
function edgeMid(p1,p2,off){const mx=(p1.x+p2.x)/2,my=(p1.y+p2.y)/2;if(!off)return{x:mx,y:my};const dx=p2.x-p1.x,dy=p2.y-p1.y,l=Math.sqrt(dx*dx+dy*dy)||1;return{x:mx+(-dy/l)*off,y:my+(dx/l)*off};}
function drawEdgeLabel(edge,p1,p2){if(!edge.src_port&&!edge.dst_port)return null;const mid=edgeMid(p1,p2,8);const lbl=new fabric.Text([(edge.src_port||'?'),(edge.dst_port||'?')].join(' ↔ '),{left:mid.x,top:mid.y,originX:'center',originY:'center',fontSize:9,fontFamily:'Arial',fill:edge.inter_area?'#F97316':'#3B82F6',backgroundColor:'rgba(248,250,252,0.9)',padding:2,selectable:false,evented:false,opacity:.9,__edgeLabel:edge.id});canvas.add(lbl);lbl.sendToBack();return lbl;}
function drawEdge(edge){if(connObjects.find(c=>c.__edgeId===edge.id))return;const fo=placedNodes[edge.from],to=placedNodes[edge.to];if(!fo||!to)return;const p1=fo.getCenterPoint(),p2=to.getCenterPoint(),color=edge.inter_area?'#F97316':'#3B82F6';const line=new fabric.Line([p1.x,p1.y,p2.x,p2.y],{stroke:color,strokeWidth:1.5,strokeDashArray:edge.inter_area?[6,4]:null,selectable:true,evented:true,hoverCursor:'pointer',opacity:.75,__edgeId:edge.id,__edgeData:edge});canvas.add(line);line.sendToBack();line.__portLabel=drawEdgeLabel(edge,p1,p2);connObjects.push(line);}
function refreshEdges(){connObjects.forEach(line=>{const e=line.__edgeData;if(!e)return;const fo=placedNodes[e.from],to=placedNodes[e.to];if(!fo||!to)return;const p1=fo.getCenterPoint(),p2=to.getCenterPoint();line.set({x1:p1.x,y1:p1.y,x2:p2.x,y2:p2.y});line.setCoords();if(line.__portLabel){const mid=edgeMid(p1,p2,8);line.__portLabel.set({left:mid.x,top:mid.y});line.__portLabel.setCoords();}});}
canvas.on('object:moving',ev=>{if(ev.target?.__nodeId)refreshEdges();canvas.requestRenderAll();});

let currentTool='select',connFirstNode=null;
function setTool(tool){currentTool=tool;connFirstNode=null;document.querySelectorAll('[id^=btn-]').forEach(b=>b.classList.remove('active'));document.getElementById({select:'btn-select',connect:'btn-conn',text:'btn-text'}[tool]||'btn-select')?.classList.add('active');canvas.defaultCursor=tool==='select'?'default':'crosshair';canvas.selection=tool==='select';document.getElementById('stat-mode').textContent='Modo: '+({select:'Seleccionar',connect:'Conectar',text:'Texto'}[tool]||tool);}
document.getElementById('btn-select').addEventListener('click',()=>setTool('select'));
document.getElementById('btn-conn').addEventListener('click',()=>setTool('connect'));
document.getElementById('btn-text').addEventListener('click',()=>setTool('text'));

let _pendingEdge=null;
function showPortDialog(edge,sx,sy){_pendingEdge=edge;const dlg=document.getElementById('port-dialog');document.getElementById('pd-src').value='';document.getElementById('pd-dst').value='';dlg.style.left=Math.min(sx,window.innerWidth-240)+'px';dlg.style.top=Math.min(sy,window.innerHeight-160)+'px';dlg.style.display='block';document.getElementById('pd-src').focus();}
function confirmPortDialog(){if(_pendingEdge){_pendingEdge.src_port=document.getElementById('pd-src').value.trim();_pendingEdge.dst_port=document.getElementById('pd-dst').value.trim();const line=connObjects.find(c=>c.__edgeId===_pendingEdge.id);if(line){if(line.__portLabel)canvas.remove(line.__portLabel);line.__portLabel=drawEdgeLabel(_pendingEdge,{x:line.x1,y:line.y1},{x:line.x2,y:line.y2});}canvas.requestRenderAll();schedAutoSave();}cancelPortDialog();}
function cancelPortDialog(){document.getElementById('port-dialog').style.display='none';_pendingEdge=null;}
document.getElementById('pd-dst').addEventListener('keydown',e=>{if(e.key==='Enter')confirmPortDialog();});
document.getElementById('pd-src').addEventListener('keydown',e=>{if(e.key==='Tab'){e.preventDefault();document.getElementById('pd-dst').focus();}});

canvas.on('mouse:down',ev=>{
    if(isPanning)return;
    if(currentTool==='connect'&&ev.target?.__nodeId){
        if(!connFirstNode){connFirstNode=ev.target;ev.target._objects?.[0]?.set({stroke:'#F59E0B',strokeWidth:3});canvas.requestRenderAll();}
        else if(ev.target!==connFirstNode){const eid='manual_'+Date.now();const fe={id:eid,from:connFirstNode.__nodeId,to:ev.target.__nodeId,src_port:'',dst_port:'',inter_area:false};allEdges.push(fe);drawEdge(fe);connFirstNode._objects?.[0]?.set({stroke:ROLE_COLORS[connFirstNode.__nodeData?.role]||'#16A34A',strokeWidth:1.5});connFirstNode=null;canvas.requestRenderAll();showPortDialog(fe,ev.e.clientX,ev.e.clientY);}
    } else if(currentTool==='text'&&!ev.target){
        const vpt=canvas.viewportTransform,z=canvas.getZoom();
        const txt=new fabric.IText('Etiqueta',{left:(ev.e.offsetX-vpt[4])/z,top:(ev.e.offsetY-vpt[5])/z,fontSize:13,fill:'#1E293B',fontFamily:'Arial',__isLabel:true});
        canvas.add(txt);canvas.setActiveObject(txt);txt.enterEditing();canvas.requestRenderAll();schedAutoSave();
    }
});

/* ── Label editor ── */
let _editingNode=null;
function showLabelEditor(nodeObj,sx,sy){
    _editingNode=nodeObj;
    const textObj=nodeObj._objects?.find(o=>o.__isNodeLabel);
    const ipObj=nodeObj._objects?.find(o=>o.__isNodeIp);
    const inp=document.getElementById('label-editor-input');
    const ipInp=document.getElementById('label-editor-ip');
    const box=document.getElementById('label-editor-box');
    inp.value=nodeObj.__nodeData?.label||(textObj?.text||'');
    ipInp.value=nodeObj.__nodeData?.ip||(ipObj?.text||'');
    box.style.left=Math.min(sx-10,window.innerWidth-240)+'px';
    box.style.top=Math.max(60,sy-100)+'px';
    box.style.display='block';inp.focus();inp.select();
}
function applyLabelEdit(){
    if(!_editingNode)return;
    const newLabel=document.getElementById('label-editor-input').value.trim()||'Sin nombre';
    const newIp=document.getElementById('label-editor-ip').value.trim();
    const display=newLabel.length>20?newLabel.slice(0,18)+'…':newLabel;
    const textObj=_editingNode._objects?.find(o=>o.__isNodeLabel);
    const ipObj=_editingNode._objects?.find(o=>o.__isNodeIp);
    if(textObj)textObj.set('text',display);
    if(ipObj)ipObj.set({text:newIp,opacity:newIp?1:0});
    _editingNode.addWithUpdate();
    if(_editingNode.__nodeData){_editingNode.__nodeData.label=newLabel;_editingNode.__nodeData.ip=newIp;}
    canvas.requestRenderAll();schedAutoSave();pushHistory();closeLabelEditor();updatePropsPanel();
}
function closeLabelEditor(){document.getElementById('label-editor-box').style.display='none';_editingNode=null;}
document.getElementById('label-editor-ok').addEventListener('click',applyLabelEdit);
document.getElementById('label-editor-cancel').addEventListener('click',closeLabelEditor);
document.getElementById('label-editor-input').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();document.getElementById('label-editor-ip').focus();}if(e.key==='Escape')closeLabelEditor();});
document.getElementById('label-editor-ip').addEventListener('keydown',e=>{if(e.key==='Enter')applyLabelEdit();if(e.key==='Escape')closeLabelEditor();});
canvas.on('mouse:dblclick',ev=>{
    const obj=ev.target;if(!obj||!obj.__nodeId)return;
    const vpt=canvas.viewportTransform,z=canvas.getZoom();
    const rect=document.querySelector('#canvas-wrap canvas').getBoundingClientRect();
    const cp=obj.getCenterPoint();
    showLabelEditor(obj,cp.x*z+vpt[4]+rect.left,cp.y*z+vpt[5]+rect.top);
});
function updateNodeLabelFromProp(val){
    const obj=canvas.getActiveObject();if(!obj||!obj.__nodeId)return;
    const newLabel=val.trim()||'Sin nombre';const display=newLabel.length>20?newLabel.slice(0,18)+'…':newLabel;
    const textObj=obj._objects?.find(o=>o.__isNodeLabel);
    if(textObj){textObj.set('text',display);obj.addWithUpdate();}
    if(obj.__nodeData)obj.__nodeData.label=newLabel;
    canvas.requestRenderAll();schedAutoSave();
}
function updateNodeIpFromProp(val){
    const obj=canvas.getActiveObject();if(!obj||!obj.__nodeId)return;
    const newIp=val.trim();
    const ipObj=obj._objects?.find(o=>o.__isNodeIp);
    if(ipObj){ipObj.set({text:newIp,opacity:newIp?1:0});obj.addWithUpdate();}
    if(obj.__nodeData)obj.__nodeData.ip=newIp;
    canvas.requestRenderAll();schedAutoSave();
}

/* ── Layout ── */
document.getElementById('btn-layout').addEventListener('click',()=>{
    const placed=Object.values(placedNodes);if(!placed.length){alert('No hay nodos en el canvas.');return;}
    const lv={0:[],1:[],2:[],3:[]},lmap={core:0,backbone:1,distribution:2,access:3};
    placed.forEach(obj=>{lv[lmap[obj.__nodeData?.role]??3].push(obj);});
    [0,1,2,3].forEach(lvl=>{const row=lv[lvl];if(!row.length)return;const x0=Math.max(100,4000-(row.length*160)/2);row.forEach((obj,i)=>{obj.set({left:x0+i*160,top:80+lvl*220});obj.setCoords();});});
    refreshEdges();canvas.requestRenderAll();schedAutoSave();pushHistory();
});

/* ── Fit ── */
document.getElementById('btn-fit').addEventListener('click',()=>{
    const objs=canvas.getObjects().filter(o=>o.__nodeId);if(!objs.length)return;
    let mx=Infinity,my=Infinity,ax=-Infinity,ay=-Infinity;
    objs.forEach(o=>{const b=o.getBoundingRect(true);mx=Math.min(mx,b.left);my=Math.min(my,b.top);ax=Math.max(ax,b.left+b.width);ay=Math.max(ay,b.top+b.height);});
    const p=60,z=Math.min(canvas.width/(ax-mx+p*2),canvas.height/(ay-my+p*2),2);
    canvas.setZoom(z);canvas.setViewportTransform([z,0,0,z,-(mx-p)*z,-(my-p)*z]);
    document.getElementById('stat-zoom').textContent='Zoom: '+Math.round(z*100)+'%';canvas.requestRenderAll();
});

/* ── Props ── */
function showIdlePanel(){document.getElementById('props-idle').style.display='';document.getElementById('props-node').style.display='none';document.getElementById('idle-nodes').textContent=Object.keys(placedNodes).length;document.getElementById('idle-edges').textContent=connObjects.length;}
canvas.on('selection:created',updatePropsPanel);canvas.on('selection:updated',updatePropsPanel);canvas.on('selection:cleared',showIdlePanel);
function updatePropsPanel(){
    const obj=canvas.getActiveObject();if(!obj){showIdlePanel();return;}
    document.getElementById('props-idle').style.display='none';
    const panel=document.getElementById('props-node');panel.style.display='';
    if(obj.__nodeData){
        const n=obj.__nodeData,color=n.is_stacked?'#7C3AED':(ROLE_COLORS[n.role]||'#16A34A');
        panel.innerHTML=`<div class="prop-section"><div class="prop-section-title">Elemento</div>
            <div class="prop-row"><span class="prop-key">Nombre</span><input class="prop-input" type="text" value="${(n.label||'').replace(/"/g,'&quot;')}" style="width:160px;text-align:right" oninput="updateNodeLabelFromProp(this.value)" onclick="this.select()"></div>
            <div class="prop-row"><span class="prop-key">IP</span><input class="prop-input" type="text" value="${(n.ip||'').replace(/"/g,'&quot;')}" style="width:160px;text-align:right" placeholder="—" oninput="updateNodeIpFromProp(this.value)" onclick="this.select()"></div>
            ${n.role?`<div class="prop-row"><span class="prop-key">Rol</span><span class="prop-badge" style="background:${color}">${ROLE_LABELS[n.role]||n.role}</span></div>`:''}
            ${n.model?`<div class="prop-row"><span class="prop-key">Modelo</span><span class="prop-val">${n.model}</span></div>`:''}
            ${n.mac?`<div class="prop-row"><span class="prop-key">MAC</span><span class="prop-val" style="font-size:10px">${n.mac}</span></div>`:''}
            ${n.port_count?`<div class="prop-row"><span class="prop-key">Puertos</span><span class="prop-val">${n.port_count}</span></div>`:''}
        </div><div class="prop-section"><div class="prop-section-title">Posición</div>
            <div class="prop-pos"><div><label>X</label><input type="number" value="${Math.round(obj.left)}" onchange="moveObj('left',this.value)"></div><div><label>Y</label><input type="number" value="${Math.round(obj.top)}" onchange="moveObj('top',this.value)"></div></div>
        </div><p style="font-size:10px;color:#94A3B8;text-align:center;margin-top:4px"><i class="ri-pencil-line"></i> Doble clic para editar nombre / IP</p>`;
    } else if(obj.__edgeData){
        const e=obj.__edgeData,fn=allNodes.find(n=>n.id===e.from),tn=allNodes.find(n=>n.id===e.to);
        panel.innerHTML=`<div class="prop-section"><div class="prop-section-title">Conexión</div>
            <div class="prop-row"><span class="prop-key">Origen</span><span class="prop-val">${fn?.label||e.from}</span></div>
            <div class="prop-row"><span class="prop-key">Puerto</span><span class="prop-val">${e.src_port||'—'}</span></div>
            <div class="prop-row"><span class="prop-key">Destino</span><span class="prop-val">${tn?.label||e.to}</span></div>
            <div class="prop-row"><span class="prop-key">Puerto</span><span class="prop-val">${e.dst_port||'—'}</span></div>
            <div class="prop-row"><span class="prop-key">Inter-área</span><span class="prop-val">${e.inter_area?'Sí':'No'}</span></div>
            ${e.num_vlans?`<div class="prop-row"><span class="prop-key">VLANs</span><span class="prop-val">${e.num_vlans}</span></div>`:''}</div>`;
    } else {
        panel.innerHTML=`<div class="prop-section"><div class="prop-section-title">Objeto</div><div class="prop-row"><span class="prop-key">Tipo</span><span class="prop-val">${obj.type||'—'}</span></div></div>`;
    }
}
function moveObj(prop,val){const obj=canvas.getActiveObject();if(obj){obj.set(prop,parseFloat(val));obj.setCoords();refreshEdges();canvas.requestRenderAll();}}
canvas.on('object:modified',()=>{updatePropsPanel();schedAutoSave();});

/* ── History ── */
const histStack=[],futureStack=[];let histLock=false;
function pushHistory(){if(histLock)return;histStack.push(buildSaveJson());if(histStack.length>40)histStack.shift();futureStack.length=0;}
function undo(){if(histStack.length<2)return;futureStack.push(histStack.pop());histLock=true;restoreCanvas(histStack[histStack.length-1]);histLock=false;}
function redo(){if(!futureStack.length)return;const s=futureStack.pop();histStack.push(s);histLock=true;restoreCanvas(s);histLock=false;}
document.getElementById('btn-undo').addEventListener('click',undo);
document.getElementById('btn-redo').addEventListener('click',redo);

/* ── Save / restore ── */
let autosaveT=null;
function schedAutoSave(){clearTimeout(autosaveT);autosaveT=setTimeout(saveNow,AUTOSAVE_MS);}
function buildSaveJson(){
    const nodes=[],edges=[],labels=[];
    canvas.getObjects().forEach(obj=>{
        if(obj.__nodeId)nodes.push({nodeId:obj.__nodeId,x:Math.round(obj.left),y:Math.round(obj.top),label:obj.__nodeData?.label,ip:obj.__nodeData?.ip||'',icon:obj.__nodeData?.icon,role:obj.__nodeData?.role});
        else if(obj.__edgeId)edges.push({edgeId:obj.__edgeId});
        else if(obj.__isLabel)labels.push({text:obj.text,x:Math.round(obj.left),y:Math.round(obj.top),fontSize:obj.fontSize,fill:obj.fill});
    });
    return{version:'2.1',nodes,edges,labels,manualEdges:allEdges.filter(e=>e.id.startsWith('manual_'))};
}
function saveNow(){fetch(SAVE_URL,{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,Accept:'application/json'},body:JSON.stringify({canvas_json:JSON.stringify(buildSaveJson())})}).then(r=>r.json()).then(d=>{if(d.ok)flashSaved();}).catch(console.error);}
function flashSaved(){const b=document.getElementById('autosave-badge');b.style.opacity='1';setTimeout(()=>b.style.opacity='0',2500);}
async function restoreCanvas(json){
    if(!json||!json.nodes)return;
    if(json.manualEdges?.length)json.manualEdges.forEach(e=>{if(!allEdges.find(ae=>ae.id===e.id))allEdges.push(e);});
    await Promise.all(json.nodes.map(saved=>{
        let node=allNodes.find(n=>n.id===saved.nodeId);
        if(!node&&saved.icon){node={id:saved.nodeId,label:saved.label||'Elemento',ip:saved.ip||'',role:saved.role||'access',icon:saved.icon,is_stacked:false,model:'',mac:'',port_count:'',batch_id:null,__isCustom:true};allNodes.push(node);}
        if(!node||placedNodes[saved.nodeId])return Promise.resolve();
        if(saved.label&&node.label!==saved.label)node.label=saved.label;
        if(saved.ip!==undefined&&node.__isCustom)node.ip=saved.ip;
        return createSwitchNode(node,saved.x,saved.y);
    }));
    json.edges?.forEach(s=>{const e=allEdges.find(e=>e.id===s.edgeId);if(e)drawEdge(e);});
    allEdges.forEach(e=>{if(placedNodes[e.from]&&placedNodes[e.to])drawEdge(e);});
    json.labels?.forEach(l=>canvas.add(new fabric.IText(l.text,{left:l.x,top:l.y,fontSize:l.fontSize||13,fill:l.fill||'#1E293B',fontFamily:'Arial',__isLabel:true})));
    canvas.requestRenderAll();updateStats();pushHistory();
}

/* ── Keyboard ── */
window.addEventListener('keydown',e=>{
    if(e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA')return;
    if(e.ctrlKey&&e.key==='s'){e.preventDefault();saveNow();}
    if(e.ctrlKey&&e.key==='z'){e.preventDefault();undo();}
    if(e.ctrlKey&&e.key==='y'){e.preventDefault();redo();}
    if(e.key==='v'||e.key==='V')setTool('select');
    if(e.key==='c'||e.key==='C')setTool('connect');
    if(e.key==='t'||e.key==='T')setTool('text');
    if((e.key==='Delete'||e.key==='Backspace')&&!e.ctrlKey){
        const active=canvas.getActiveObjects();if(!active.length)return;
        active.forEach(obj=>{if(obj.__nodeId)delete placedNodes[obj.__nodeId];if(obj.__edgeId){if(obj.__portLabel)canvas.remove(obj.__portLabel);connObjects=connObjects.filter(c=>c!==obj);}canvas.remove(obj);});
        canvas.discardActiveObject();canvas.requestRenderAll();updateStats();schedAutoSave();pushHistory();
    }
    if(e.key==='Escape'){document.getElementById('shortcuts-overlay').style.display='none';closeLabelEditor();}
});

/* ── Export ── */
const expBtn=document.getElementById('btn-export'),expMenu=document.getElementById('export-menu');
expBtn.addEventListener('click',e=>{const r=expBtn.getBoundingClientRect();expMenu.style.top=(r.bottom+6)+'px';expMenu.style.right=(window.innerWidth-r.right)+'px';expMenu.style.display=expMenu.style.display==='block'?'none':'block';e.stopPropagation();});
document.addEventListener('click',()=>{expMenu.style.display='none';});
function exportAs(fmt){expMenu.style.display='none';if(fmt==='png-client'){const a=document.createElement('a');a.href=canvas.toDataURL({format:'png',multiplier:2});a.download='{{ addslashes($project->name) }}.png';a.click();return;}if(fmt==='svg'){exportSvgEmbedded();return;}if(fmt==='pdf'){const{jsPDF}=window.jspdf;const img=canvas.toDataURL({format:'jpeg',quality:.85,multiplier:1});const pdf=new jsPDF({orientation:'landscape',unit:'px',format:[canvas.width,canvas.height]});pdf.addImage(img,'JPEG',0,0,canvas.width,canvas.height);pdf.save('{{ addslashes($project->name) }}.pdf');}}
async function exportSvgEmbedded(){const fabs=canvas.getObjects().filter(o=>o.type==='image'),restores=[];for(const fab of fabs){const el=fab.getElement();if(!el)continue;try{const oc=document.createElement('canvas');oc.width=el.naturalWidth||el.width;oc.height=el.naturalHeight||el.height;oc.getContext('2d').drawImage(el,0,0);restores.push({fab,oldSrc:fab.getSrc?.()});await new Promise(res=>fab.setSrc(oc.toDataURL('image/png'),res,{crossOrigin:null}));}catch(_){}}const svg=canvas.toSVG();for(const{fab,oldSrc}of restores){if(oldSrc)fab.setSrc(oldSrc,()=>{},{crossOrigin:'anonymous'});}const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml'}));a.download='{{ addslashes($project->name) }}.svg';a.click();URL.revokeObjectURL(a.href);}

/* ── Palette drag ── */
let _dragRole=null,_dragIcon=null;
document.querySelectorAll('.pal-item').forEach(el=>{el.addEventListener('dragstart',ev=>{_dragRole=el.dataset.role;_dragIcon=el.dataset.icon;ev.dataTransfer.effectAllowed='copy';ev.dataTransfer.setData('text/plain','pal:'+_dragRole);});});
const canvasArea=document.getElementById('canvas-area');
canvasArea.addEventListener('dragover',ev=>{ev.preventDefault();ev.dataTransfer.dropEffect='copy';});
canvasArea.addEventListener('drop',ev=>{
    ev.preventDefault();if(!ev.dataTransfer.getData('text/plain').startsWith('pal:'))return;
    const rect=canvasArea.getBoundingClientRect(),vpt=canvas.viewportTransform,z=canvas.getZoom();
    const cx=(ev.clientX-rect.left-vpt[4])/z,cy=(ev.clientY-rect.top-vpt[5])/z;
    const cid='custom_'+Date.now();
    const cn={id:cid,label:'Nuevo elemento',ip:'',role:_dragRole||'access',icon:_dragIcon||'access_switch',is_stacked:false,model:'',mac:'',port_count:'',batch_id:null,__isCustom:true};
    allNodes.push(cn);
    createSwitchNode(cn,cx,cy).then(grp=>{
        grp.__isCustom=true;canvas.setActiveObject(grp);canvas.requestRenderAll();
        document.getElementById('canvas-hint').style.display='none';updateStats();schedAutoSave();pushHistory();
        const cp=grp.getCenterPoint(),rect2=document.querySelector('#canvas-wrap canvas').getBoundingClientRect();
        showLabelEditor(grp,cp.x*z+vpt[4]+rect2.left,cp.y*z+vpt[5]+rect2.top);
    });
});

/* ── Stats ── */
function updateStats(){const n=Object.keys(placedNodes).length,e=connObjects.length;document.getElementById('stat-nodes').textContent='Nodos: '+n;document.getElementById('stat-edges').textContent='Conexiones: '+e;const in_=document.getElementById('idle-nodes'),ie=document.getElementById('idle-edges');if(in_)in_.textContent=n;if(ie)ie.textContent=e;}

/* ── Misc ── */
document.getElementById('btn-save').addEventListener('click',saveNow);
document.getElementById('btn-delete').addEventListener('click',()=>{
    const active=canvas.getActiveObjects();if(!active.length)return;
    active.forEach(obj=>{if(obj.__nodeId)delete placedNodes[obj.__nodeId];if(obj.__edgeId){if(obj.__portLabel)canvas.remove(obj.__portLabel);connObjects=connObjects.filter(c=>c!==obj);}canvas.remove(obj);});
    canvas.discardActiveObject();canvas.requestRenderAll();updateStats();schedAutoSave();pushHistory();
});
document.getElementById('btn-shortcuts').addEventListener('click',()=>{const o=document.getElementById('shortcuts-overlay');o.style.display=o.style.display==='flex'?'none':'flex';});
document.getElementById('shortcuts-overlay').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});

window.addEventListener('load',()=>{loadGraph();pushHistory();});
</script>
</body>
</html>