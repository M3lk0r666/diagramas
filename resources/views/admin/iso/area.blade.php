<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Isométrico · {{ $batch->name }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#f1f5f9}
</style>
</head>
<body>

<div id="iso-wrapper"
     class="relative overflow-hidden"
     style="width:100%;height:100vh;background:#f1f5f9;">

    <canvas id="iso-canvas" class="absolute inset-0 block w-full h-full"></canvas>
    <div id="iso-labels" class="absolute inset-0 pointer-events-none"></div>
    <div id="iso-tooltip" style="display:none"></div>

    {{-- ══ Top toolbar ══ --}}
    <div class="absolute top-3 left-3 right-3 z-30 flex items-center gap-2 flex-wrap">

        {{-- Título --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm px-3 py-2
                    flex items-center gap-2 mr-auto min-w-0">
            <i class="ri-cube-line text-indigo-500 text-base shrink-0"></i>
            <span class="text-xs text-gray-500 shrink-0">{{ $client->name }}</span>
            <i class="ri-arrow-right-s-line text-gray-300 shrink-0 text-sm"></i>
            <span class="font-semibold text-gray-800 text-sm truncate">{{ $batch->name }}</span>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full shrink-0">
                {{ count($data['batches'][0]['switches']) }}&nbsp;switches
            </span>
        </div>

        {{-- Buscador --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm
                    flex items-center gap-1 px-2.5 py-1.5">
            <i class="ri-search-line text-gray-400 text-sm shrink-0"></i>
            <input id="iso-search-input"
                   type="text"
                   placeholder="Switch / IP…"
                   class="text-sm border-0 outline-none w-32 text-gray-700 placeholder-gray-400 bg-transparent"
                   onkeydown="if(event.key==='Enter') isoSearch()">
            <button onclick="isoSearch()"
                    class="text-xs text-white bg-indigo-500 hover:bg-indigo-600 px-2 py-0.5 rounded transition font-medium shrink-0">
                Buscar
            </button>
            <span id="iso-search-status" class="text-xs font-medium text-red-500 shrink-0"></span>
        </div>

        {{-- Controles + leyenda inline --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm
                    flex items-center divide-x divide-gray-200">
            <button id="btn-labels"
                    onclick="isoToggleLabels()"
                    class="text-xs text-gray-600 hover:text-indigo-600 hover:bg-indigo-50
                           px-2.5 py-2 transition font-medium flex items-center gap-1 rounded-l-lg">
                <i class="ri-tag-line text-sm"></i>
                <span>Labels</span>
            </button>
            <button onclick="isoReset()"
                    class="text-xs text-gray-600 hover:text-indigo-600 hover:bg-indigo-50
                           px-2.5 py-2 transition font-medium flex items-center gap-1">
                <i class="ri-focus-3-line text-sm"></i>
                <span>Centrar</span>
            </button>
            <button onclick="isoPanelToggle()"
                    class="text-xs text-gray-600 hover:text-indigo-600 hover:bg-indigo-50
                           px-2.5 py-2 transition font-medium flex items-center gap-1"
                    title="Abrir / cerrar panel de detalles">
                <i id="iso-panel-toggle-icon" class="ri-layout-right-line text-sm"></i>
                <span>Info</span>
            </button>
            {{-- Leyenda de roles compacta --}}
            <div class="px-2.5 py-2 flex items-center gap-1.5" title="Core · Backbone · Distribución · Acceso">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500 shrink-0" title="Core"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-orange-500 shrink-0" title="Backbone"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-violet-500 shrink-0" title="Distribución"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shrink-0" title="Acceso"></span>
            </div>
            <a href="{{ route('admin.iso.global', $client) }}"
               class="text-xs text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50
                      px-2.5 py-2 transition font-medium flex items-center gap-1">
                <i class="ri-global-line text-sm"></i>
                <span>Global</span>
            </a>
            <a href="{{ route('admin.areas.topology', [$client, $batch]) }}"
               class="text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100
                      px-2.5 py-2 transition font-medium flex items-center gap-1">
                <i class="ri-node-tree text-sm"></i>
                <span>Vista&nbsp;2D</span>
            </a>
            <a href="{{ route('admin.areas.show', [$client, $batch]) }}"
               class="text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100
                      px-2.5 py-2 transition font-medium flex items-center gap-1 rounded-r-lg">
                <i class="ri-arrow-left-s-line text-sm"></i>
                <span>Switches</span>
            </a>
        </div>
    </div>

    {{-- ══ Panel lateral colapsable ══ --}}
    <div id="iso-panel"
         class="absolute top-14 right-0 w-72 bg-white border-l border-gray-200
                shadow-xl z-40 transform translate-x-full transition-transform duration-300
                flex flex-col"
         style="height: calc(100% - 56px)">
        <div class="flex items-center justify-between px-4 py-3
                    border-b border-gray-100 shrink-0 bg-gray-50">
            <span class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                <i class="ri-information-line text-indigo-500"></i>
                Detalles del Switch
            </span>
            <button onclick="isoClosePanel()"
                    class="text-gray-400 hover:text-gray-700 p-1 rounded hover:bg-gray-200 transition"
                    title="Cerrar panel">
                <i class="ri-close-line text-lg"></i>
            </button>
        </div>
        <div id="iso-panel-body" class="p-4 overflow-y-auto flex-1 text-sm"></div>
    </div>

    {{-- Hint de uso --}}
    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 z-10 pointer-events-none
                text-xs text-gray-500 bg-white border border-gray-200
                px-3 py-1 rounded-full whitespace-nowrap shadow-sm">
        Arrastra para mover &middot; Scroll para zoom &middot; Clic en nodo para detalles
    </div>
</div>

<script>
    const ISO_DATA  = @json($data);
    const ICON_BASE = '{{ url("/admin/topology/icons") }}/';
</script>
@include('admin.iso._scene')

</body>
</html>
