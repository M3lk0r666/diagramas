<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Isométrica · {{ $client->name }}</title>
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
            <span class="font-semibold text-gray-800 text-sm truncate">{{ $client->name }}</span>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full shrink-0">
                {{ collect($data['batches'])->count() }}&nbsp;áreas
                &middot;
                {{ collect($data['batches'])->sum(fn($b) => count($b['switches'])) }}&nbsp;switches
            </span>
            @if(count($data['inter_area_connections']))
                <span class="text-xs bg-orange-50 text-orange-600 border border-orange-200
                             px-2 py-0.5 rounded-full shrink-0 font-medium">
                    {{ count($data['inter_area_connections']) }}&nbsp;inter-área
                </span>
            @endif
        </div>

        {{-- Buscador --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm
                    flex items-center gap-1 px-2.5 py-1.5">
            <i class="ri-search-line text-gray-400 text-sm shrink-0"></i>
            <input id="iso-search-input"
                   type="text"
                   placeholder="Switch / IP…"
                   class="text-sm border-0 outline-none w-36 text-gray-700 placeholder-gray-400 bg-transparent"
                   onkeydown="if(event.key==='Enter') isoSearch()">
            <button onclick="isoSearch()"
                    class="text-xs text-white bg-indigo-500 hover:bg-indigo-600 px-2 py-0.5 rounded transition font-medium shrink-0">
                Buscar
            </button>
            <span id="iso-search-status" class="text-xs font-medium text-red-500 shrink-0"></span>
        </div>

        {{-- Controles + leyenda de roles inline --}}
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
                @if(count($data['inter_area_connections']))
                <span class="w-4 border-t-2 border-dashed border-orange-400 shrink-0 ml-0.5" title="Inter-área"></span>
                @endif
            </div>
            <a href="{{ route('admin.areas.global', $client) }}"
               class="text-xs text-gray-600 hover:text-indigo-600 hover:bg-indigo-50
                      px-2.5 py-2 transition font-medium flex items-center gap-1">
                <i class="ri-node-tree text-sm"></i>
                <span>Vista&nbsp;2D</span>
            </a>
            <a href="{{ route('admin.areas.client', $client) }}"
               class="text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100
                      px-2.5 py-2 transition font-medium flex items-center gap-1">
                <i class="ri-arrow-left-s-line text-sm"></i>
                <span>Áreas</span>
            </a>
            <a href="{{ route('admin.iso.index') }}"
               class="text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100
                      px-2.5 py-2 transition font-medium flex items-center gap-1 rounded-r-lg"
               title="Volver al índice de isométricas">
                <i class="ri-home-4-line text-sm"></i>
                <span>Inicio</span>
            </a>
        </div>
    </div>

    {{-- ══ Leyenda de áreas (izquierda, debajo del toolbar) ══ --}}
    @if(count($data['batches']) > 1)
    <div class="absolute left-3 z-20 bg-white border border-gray-200
                rounded-lg shadow-sm px-3 py-2.5 w-52"
         style="top:58px; max-height:calc(100% - 80px); display:flex; flex-direction:column;">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 shrink-0">Áreas</div>
        <div class="overflow-y-auto space-y-0.5 flex-1 pr-0.5">
            @foreach($data['batches'] as $b)
            <a href="{{ route('admin.iso.area', [$client, $b['id']]) }}"
               class="flex items-center gap-2 text-xs text-gray-700 hover:text-indigo-600
                      hover:bg-indigo-50 rounded px-1 py-0.5 -mx-1 transition group">
                <span class="w-2.5 h-2.5 rounded-sm shrink-0"
                      style="background:{{ $b['color'] }}"></span>
                <span class="truncate">{{ $b['name'] }}</span>
                <span class="text-gray-400 shrink-0 ml-auto tabular-nums">{{ count($b['switches']) }}</span>
                <i class="ri-arrow-right-s-line text-indigo-400 opacity-0 group-hover:opacity-100 shrink-0"></i>
            </a>
            @endforeach
        </div>
    </div>
    @endif

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
