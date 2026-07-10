<x-admin-layout
    title="Mapeo de Puertos | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard',      'href' => route('dashboard')],
        ['name' => 'Mapeo de Puertos', 'href' => route('admin.port-mapping.index')],
        ['name' => $portMapping ? $portMapping->name : 'Nuevo mapeo'],
    ]"
>

{{-- ── Estilos del módulo (idénticos al prototipo validado) ─────────────────── --}}
<style>
/* Contenedor principal */
.pm-app { max-width: 1400px; margin: 0 auto; position: relative; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 14px; color: #333; }
/* Barras de configuración */
.config-bar { background:#fff; border:1px solid #e0e2e6; border-radius:6px; padding:12px 16px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; font-size:13px; margin-bottom:10px; }
.config-bar label { font-weight:600; white-space:nowrap; }
.config-bar select, .config-bar input[type=text] { border:1px solid #c9ccd1; border-radius:4px; padding:5px 8px; font-size:13px; background:#fff; }
.config-bar .sep { color:#ccc; }
/* Panel de metadatos */
.meta-panel { background:#fff; border:1px solid #e0e2e6; border-radius:6px; padding:12px 16px; margin-bottom:10px; display:grid; grid-template-columns:1fr 1fr; gap:8px 28px; font-size:13px; }
.meta-ip { grid-column:1 / -1; display:flex; gap:10px; align-items:center; padding-bottom:10px; border-bottom:1px dashed #e0e2e6; }
.meta-col h4 { font-size:11px; color:#0091ad; letter-spacing:.06em; margin-bottom:8px; }
.meta-col .row { display:flex; gap:8px; align-items:center; margin-bottom:6px; flex-wrap:wrap; }
.meta-panel label { font-weight:600; white-space:nowrap; }
.meta-panel input { border:1px solid #c9ccd1; border-radius:4px; padding:5px 8px; font-size:13px; }
@media (max-width:760px) { .meta-panel { grid-template-columns:1fr; } }
/* Nota de bloque en stacks */
.block-note { display:inline-block; font-size:11px; color:#3d6320; background:#eef7e3; border:1px solid #cde3b3; border-radius:4px; padding:4px 10px; margin-top:8px; }
/* Botones del prototipo */
.pm-btn { border:1px solid #0091ad; color:#0091ad; background:#fff; border-radius:99px; font-size:13px; padding:6px 16px; cursor:pointer; white-space:nowrap; }
.pm-btn:hover { background:#e8f6f9; }
.pm-btn.primary { background:#0091ad; color:#fff; }
.pm-btn.primary:hover { background:#007a92; }
.pm-spacer { flex:1; }
/* Banner de modo mapeo */
.map-banner { display:none; background:#fff7e6; border:1px solid #e0a83c; border-radius:6px; padding:10px 16px; margin-bottom:14px; font-size:13px; color:#7a5410; align-items:center; gap:12px; }
.map-banner.visible { display:flex; }
.map-banner .pm-btn { border-color:#e0a83c; color:#7a5410; }
/* Faceplates */
.stack-wrap { position:relative; }
.origin-row { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:46px; }
.panel { background:#fff; border:1px solid #e0e2e6; border-radius:6px; padding:14px 18px 20px; min-width:0; }
.origin-row .panel { flex:0 1 auto; }
.panel.map-target { outline:2px dashed #e0a83c; outline-offset:3px; }
.panel-title { font-size:13px; font-weight:700; margin-bottom:2px; color:#222; }
.panel-sub { font-size:11px; color:#888; margin-bottom:12px; min-height:13px; }
/* Área de puertos — reglas responsive obligatorias */
.ports-area { display:flex; gap:18px; align-items:flex-start; min-width:0; overflow-x:auto; padding:4px 2px 8px; }
.block { display:flex; gap:8px; flex-shrink:0; }
.col { display:flex; flex-direction:column; align-items:center; gap:4px; flex-shrink:0; }
.num { font-size:11px; color:#444; height:14px; line-height:14px; }
.fiber-sec { display:flex; gap:8px; flex-shrink:0; border-left:1px solid #e0e2e6; padding-left:16px; position:relative; }
.fiber-tag { position:absolute; top:-4px; left:16px; font-size:9px; color:#999; letter-spacing:.05em; }
/* Jacks RJ45 — clip-path del prototipo validado */
.jack { width:34px; height:32px; min-width:34px; min-height:32px; flex-shrink:0; background:#e8e9eb; border:1.5px solid #c4c7cc; border-radius:4px; clip-path:polygon(0 0,100% 0,100% 62%,82% 62%,82% 100%,18% 100%,18% 62%,0 62%); display:flex; align-items:flex-start; justify-content:center; padding-top:5px; font-size:9px; font-weight:700; color:#8a8f98; cursor:pointer; transition:transform .1s ease; user-select:none; }
.jack:hover { transform:scale(1.12); }
.jack.flip { clip-path:polygon(18% 0,82% 0,82% 38%,100% 38%,100% 100%,0 100%,0 38%,18% 38%); align-items:flex-end; padding-top:0; padding-bottom:5px; }
/* SFP: rectángulo plano sin muesca */
.jack.sfp { clip-path:none; height:22px; min-height:22px; margin-top:5px; border-radius:3px; align-items:center; padding:0; }
.jack.sfp.flip { margin-top:10px; }
/* Estados de los jacks */
.jack.active     { background:#b5dd8f; border-color:#7fb84e; color:#3d6320; }
.jack.nolink     { background:#fbfbfb; border-color:#b7bbc2; color:#8a8f98; }
.jack.disabled   { background:#f5b5b5; border-color:#d97070; color:#7c1d1d; }
.jack.reassigned { background:#ffd98e; border-color:#e0a83c; color:#7a5410; }
.jack.selected   { background:#6abf45; border-color:#4e9930; color:#fff; }
.jack.candidate  { box-shadow:0 0 0 2px #e0a83c; }
/* SVG de líneas */
#linesSvg { position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:5; }
#linesSvg path { fill:none; stroke:#e0a83c; stroke-width:2; opacity:.75; }
#linesSvg path.hl   { stroke-width:3.5; opacity:1; stroke:#cf8a13; }
#linesSvg path.band { stroke:#7fb84e; stroke-width:12; opacity:.28; }
/* Leyenda */
.pm-legend { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:#555; margin-top:14px; align-items:center; }
.pm-legend span { display:flex; align-items:center; gap:5px; }
.dot { width:12px; height:12px; border-radius:3px; display:inline-block; border:1px solid transparent; }
/* Popover */
.popover { position:absolute; width:235px; background:#fff; border:1px solid #c9ccd1; border-radius:4px; box-shadow:0 6px 22px rgba(0,0,0,.18); z-index:50; display:none; font-size:13px; }
.popover.visible { display:block; }
.pop-header { background:#f1f2f4; border-bottom:1px solid #dfe1e5; padding:8px 12px; display:flex; justify-content:space-between; align-items:center; }
.pop-header h3 { font-size:13px; color:#0091ad; font-weight:700; }
.close-btn { border:none; background:none; cursor:pointer; font-size:16px; color:#777; line-height:1; }
.close-btn:hover { color:#222; }
.pop-body { padding:8px; display:flex; flex-direction:column; gap:6px; }
.state-btn { border:1px solid #c9ccd1; background:#fff; border-radius:5px; padding:7px 10px; font-size:13px; cursor:pointer; text-align:left; display:flex; align-items:center; gap:8px; }
.state-btn:hover { background:#f4f5f7; }
.pop-info { padding:4px 12px 8px; font-size:12px; color:#777; }
</style>

<div class="pm-app p-4 sm:p-6" id="pm-app">

    {{-- ── Componente: barra de configuración ──────────────────────────────── --}}
    <x-port-mapping.config-bar />

    {{-- ── Nombre del mapeo + Guardar en servidor ─────────────────────────── --}}
    <div class="config-bar" style="gap:10px;">
        <label for="inpMappingName" style="font-weight:600">Nombre:</label>
        <input type="text" id="inpMappingName" placeholder="Ej. Migración IDF-3 Piso 2" style="width:260px">
        <div class="pm-spacer"></div>
        <button class="pm-btn primary" id="btnServerSave">
            <i class="ri-save-line"></i> Guardar en portal
        </button>
        {{-- Punto de extensión: precargar desde análisis de switch existente --}}
        <button class="pm-btn" id="btnPreload" title="Precargar estados desde análisis de Port Summary">
            <i class="ri-database-2-line"></i> Precargar desde análisis
        </button>
    </div>

    {{-- ── Componente: panel de metadatos ─────────────────────────────────── --}}
    <x-port-mapping.meta-panel />

    {{-- Banner de modo re-asignación --}}
    <div class="map-banner" id="mapBanner">
        <span id="mapBannerText"></span>
        <button class="pm-btn" id="btnCancelMap">Cancelar</button>
    </div>

    {{-- ── Faceplates: origen (arriba) + destino (abajo) ───────────────────── --}}
    <div class="stack-wrap" id="stackWrap">
        <div class="origin-row" id="originRow">
            {{-- Generado dinámicamente por JS --}}
        </div>
        <div class="panel" id="panelDest">
            <div class="panel-title" id="titleDest">SWITCH DESTINO</div>
            <div class="panel-sub"   id="subDest"></div>
            <div class="ports-area"  id="areaDest"></div>
        </div>
        <svg id="linesSvg"></svg>
    </div>

    {{-- Leyenda de estados --}}
    <div class="pm-legend">
        <span><span class="dot" style="background:#e8e9eb;border-color:#c4c7cc"></span> Sin definir</span>
        <span><span class="dot" style="background:#b5dd8f;border-color:#7fb84e"></span> Activo</span>
        <span><span class="dot" style="background:#fbfbfb;border-color:#b7bbc2"></span> Sin link</span>
        <span><span class="dot" style="background:#ffd98e;border-color:#e0a83c"></span> Re-asignado</span>
        <span><span class="dot" style="background:#f5b5b5;border-color:#d97070"></span> Deshabilitado</span>
    </div>

    {{-- Popover de estados (JS vanilla, no migrado a Flowbite) --}}
    <div class="popover" id="popover">
        <div class="pop-header">
            <h3 id="popTitle">Puerto</h3>
            <button class="close-btn" id="popClose">&times;</button>
        </div>
        <div class="pop-body" id="popBody"></div>
        <div class="pop-info" id="popInfo"></div>
    </div>

</div>

{{-- ── Modal de confirmación (reemplaza confirm() nativo) ─────────────────── --}}
@push('modals')
<div id="pm-confirm-modal"
     class="hidden fixed inset-0 z-[200] flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4">
        <div class="flex items-start gap-3 mb-4">
            <span class="flex-shrink-0 w-9 h-9 flex items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <i class="ri-error-warning-line text-lg"></i>
            </span>
            <p id="pm-confirm-msg" class="text-sm text-gray-700 pt-1.5 leading-relaxed"></p>
        </div>
        <div class="flex justify-end gap-3">
            <button id="pm-confirm-cancel"
                    class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                Cancelar
            </button>
            <button id="pm-confirm-ok"
                    class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition">
                Continuar
            </button>
        </div>
    </div>
</div>
@endpush

{{-- ── Datos del mapeo para hidratación JS ────────────────────────────────── --}}
@push('js')
@if($portMappingJson)
<script>
window.__PORT_MAPPING__ = {!! $portMappingJson !!};
</script>
@endif
@vite('resources/js/port-mapping.js')

{{-- Botón "Precargar desde análisis" — llama al endpoint stub del controlador --}}
<script>
document.getElementById('btnPreload')?.addEventListener('click', async () => {
    // TODO: en sprint futuro mostrar un selector de switch y llamar a:
    // POST /admin/port-mapping/preload-from-switch { switch_id: X }
    // La respuesta poblará el estado del origen con los puertos reales.
    alert('Función disponible próximamente. Conectará con el parser de Port Summary del portal.');
});
</script>
@endpush

</x-admin-layout>
