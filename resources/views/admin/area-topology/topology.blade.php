<x-admin-layout
    title="Topología · {{ $batch->name }} | Diagramas"
    :breadcrumbs="[]">

    {{-- Layout full-height: barra superior + dos columnas (diagrama | lista) --}}
    <div class="fixed flex flex-col top-14 left-0 right-0 bottom-0 lg:left-64" style="z-index:10;">

        {{-- ── Barra de acciones ── --}}
        <div class="flex items-center justify-between px-5 py-2.5 bg-white border-b border-gray-200 shrink-0 gap-3 flex-wrap">
            <div class="flex items-center gap-2.5 min-w-0">
                <a href="{{ route('admin.areas.client', $client) }}"
                   class="font-semibold text-gray-700 text-sm truncate hover:text-indigo-600 hover:underline transition">{{ $client->name }}</a>
                <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-sm text-gray-500 truncate">{{ $batch->name }}</span>
                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full shrink-0">
                    {{ count($switchesData) }} switches
                </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                {{-- Badge: conexiones inter-área (se rellena desde JS) --}}
                <span id="badge-interarea" class="hidden items-center gap-1.5 px-2.5 py-1 text-xs font-medium
                       bg-orange-50 text-orange-700 border border-orange-200 rounded-full">
                    <span class="w-2 h-2 rounded-full bg-orange-400 shrink-0"></span>
                    <span id="badge-interarea-text"></span>
                </span>
                <button id="btn-fit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600
                           bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                    Ajustar vista
                </button>
                <button id="btn-stabilize"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600
                           bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reorganizar
                </button>
                <a href="{{ route('admin.areas.global', $client) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600
                          bg-indigo-50 hover:bg-indigo-100 rounded-lg transition border border-indigo-200">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064"/>
                    </svg>
                    Vista global
                </a>
                <a href="{{ route('admin.areas.show', [$client, $batch]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600
                          bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    ← Switches
                </a>
            </div>
        </div>

        {{-- ── Contenido: diagrama (izq) + lista (der) ── --}}
        <div class="flex flex-1 overflow-hidden">

            {{-- Diagrama vis-network --}}
            <div class="relative bg-gray-50 border-r border-gray-200 flex-1 min-w-0">
                <div id="area-network" class="w-full h-full"></div>

                {{-- Leyenda compacta --}}
                <div class="absolute bottom-3 left-3 z-10 bg-white/95 backdrop-blur-sm rounded-lg border border-gray-200
                            shadow-sm px-3 py-2 text-xs text-gray-600 pointer-events-none" style="min-width:200px">
                    <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 mb-2">
                        <div class="col-span-2 font-semibold text-gray-700 mb-1 text-[11px] uppercase tracking-wide">Iconos</div>
                        <div class="flex items-center gap-1.5"><img src="{{ route('admin.topology.icon', 'core_switch.png') }}"     class="w-4 h-4 object-contain" alt=""> Core</div>
                        <div class="flex items-center gap-1.5"><img src="{{ route('admin.topology.icon', 'backbone_switch.png') }}" class="w-4 h-4 object-contain" alt=""> Backbone</div>
                        <div class="flex items-center gap-1.5"><img src="{{ route('admin.topology.icon', 'dist_switch.png') }}"     class="w-4 h-4 object-contain" alt=""> Distribución</div>
                        <div class="flex items-center gap-1.5"><img src="{{ route('admin.topology.icon', 'access_switch.png') }}"   class="w-4 h-4 object-contain" alt=""> Acceso</div>
                        <div class="flex items-center gap-1.5"><img src="{{ route('admin.topology.icon', 'stack_switch.png') }}"    class="w-4 h-4 object-contain" alt=""> Stack</div>
                    </div>
                    <div class="pt-1.5 border-t border-gray-100">
                        <div class="font-semibold text-gray-700 mb-1 text-[11px] uppercase tracking-wide">Conexiones</div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-6 shrink-0" style="border-top:2px solid #94A3B8"></span> Intra-área
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="inline-block w-6 shrink-0" style="border-top:2.5px dashed #F97316"></span>
                            <span class="text-orange-600">Inter-área</span>
                        </div>
                    </div>
                </div>

                {{-- Spinner --}}
                <div id="network-loading"
                     class="absolute inset-0 flex items-center justify-center bg-gray-50/90 z-10">
                    <div class="flex flex-col items-center gap-3">
                        <svg class="w-8 h-8 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span class="text-sm text-gray-500">Calculando topología…</span>
                    </div>
                </div>
            </div>

            {{-- Lista lateral de switches --}}
            <div class="w-96 shrink-0 flex flex-col bg-white overflow-hidden">
                {{-- Cabecera lista --}}
                <div class="px-4 py-3 border-b border-gray-100 shrink-0">
                    <h3 class="text-sm font-semibold text-gray-700">Equipos del área</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Hover → resaltar · Clic → zoom
                    </p>
                </div>

                {{-- Búsqueda rápida --}}
                <div class="px-3 py-2 border-b border-gray-100 shrink-0">
                    <div class="relative">
                        <input id="sw-filter" type="text" placeholder="Buscar equipo…"
                               class="w-full text-xs border-gray-200 rounded-lg pl-7 pr-2 py-1.5
                                      focus:ring-blue-500 focus:border-blue-500">
                        <svg class="absolute left-2 top-2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </div>
                </div>

                {{-- Tabla de switches (scrollable) --}}
                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 text-gray-400 uppercase sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-2 text-left">Hostname</th>
                                <th class="px-3 py-2 text-left">IP</th>
                                <th class="px-3 py-2 text-center">Rol</th>
                            </tr>
                        </thead>
                        <tbody id="sw-list" class="divide-y divide-gray-50">
                            @foreach($switchesData as $sw)
                                <tr data-node="{{ $sw['node_id'] }}"
                                    data-name="{{ strtolower($sw['sys_name']) }}"
                                    class="sw-row cursor-pointer transition-colors hover:bg-blue-50 select-none">
                                    <td class="px-3 py-2.5 min-w-0">
                                        <div class="font-medium text-gray-800 truncate" title="{{ $sw['sys_name'] }}"
                                             style="max-width:185px">
                                            {{ $sw['sys_name'] }}
                                        </div>
                                        @if($sw['is_stacked'])
                                            <span class="text-amber-600 text-xs">Stack</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-gray-500 whitespace-nowrap">{{ $sw['ip'] }}</td>
                                    <td class="px-3 py-2.5 text-center w-10">
                                        @php
                                            $roleIconMap = [
                                                'core'     => 'core_switch.png',
                                                'backbone' => 'backbone_switch.png',
                                                'dist'     => 'dist_switch.png',
                                                'access'   => 'access_switch.png',
                                                'stack'    => 'stack_switch.png',
                                            ];
                                            $roleIcon = $sw['is_stacked']
                                                ? 'stack_switch.png'
                                                : ($roleIconMap[$sw['role']] ?? 'access_switch.png');
                                        @endphp
                                        <img src="{{ route('admin.topology.icon', $roleIcon) }}"
                                             alt="{{ $sw['role'] }}" title="{{ ucfirst($sw['role']) }}"
                                             class="w-5 h-5 object-contain mx-auto">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Footer: link a detalle --}}
                <div class="shrink-0 px-4 py-2.5 border-t border-gray-100 bg-gray-50">
                    <a href="{{ route('admin.areas.show', [$client, $batch]) }}"
                       class="text-xs text-blue-600 hover:underline">Ver detalle completo →</a>
                </div>
            </div>

        </div>{{-- /flex contenido --}}
    </div>{{-- /flex principal --}}

    @push('js')
    <script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.6/standalone/umd/vis-network.min.js"></script>
    <script>
    (function () {
        const RAW_NODES = @json($nodes);
        const RAW_EDGES = @json($graphEdges);

        // Iconos PNG via ruta Laravel existente
        const ROLE_ICON = {
            core:     '{{ route('admin.topology.icon', 'core_switch.png') }}',
            backbone: '{{ route('admin.topology.icon', 'backbone_switch.png') }}',
            dist:     '{{ route('admin.topology.icon', 'dist_switch.png') }}',
            access:   '{{ route('admin.topology.icon', 'access_switch.png') }}',
            stack:    '{{ route('admin.topology.icon', 'stack_switch.png') }}',
            default:  '{{ route('admin.topology.icon', 'access_switch.png') }}',
        };

        const visNodes = RAW_NODES.map(n => {
            // Nodo fantasma: switch externo de otra área
            if (n.is_ghost) {
                return {
                    id:          n.id,
                    label:       (n.label || 'Externo') + '\n↗ ' + (n.external_area || 'Área desconocida'),
                    title:       n.title || 'Switch externo',
                    shape:       'box',
                    color: {
                        background: '#FFF7ED',
                        border:     '#F97316',
                        highlight:  { background: '#FFEDD5', border: '#EA580C' },
                        hover:      { background: '#FFEDD5', border: '#F97316' },
                    },
                    borderWidth:  1.5,
                    borderDashes: [6, 3],
                    font:  { size: 8, color: '#C2410C', face: 'Inter, sans-serif' },
                    margin: 6,
                    shadow: false,
                };
            }
            // Nodo normal
            const role = n.is_stacked ? 'stack' : (n.role || 'access');
            const icon = ROLE_ICON[role] || ROLE_ICON.default;
            return {
                id:              n.id,
                label:           n.label,
                title:           n.title || ((n.model || '') + '\nIP: ' + (n.ip || '')),
                shape:           'image',
                image:           icon,
                size:            22,
                font:            { size: 9, color: '#374151', face: 'Inter, sans-serif', vadjust: 4 },
                color:           { border: 'rgba(0,0,0,0)', background: 'rgba(0,0,0,0)',
                                   highlight: { border: '#3B82F6', background: '#EFF6FF' },
                                   hover:     { border: '#93C5FD', background: '#F0F9FF' } },
                borderWidth:         0,
                borderWidthSelected: 2,
                _switch_id: n.switch_id,
            };
        });

        const visEdges = RAW_EDGES.map((e, i) => ({
            id:     'e' + i,
            from:   e.from,
            to:     e.to,
            label:  e.label || '',
            dashes: e.is_ghost ? [6, 4] : (e.dashes || false),
            arrows: (e.arrows !== undefined) ? e.arrows : { to: false, from: false },
            color:  e.is_ghost
                ? { color: '#F97316', highlight: '#EA580C', hover: '#EA580C' }
                : (e.color || { color: '#94A3B8', highlight: '#3B82F6', hover: '#3B82F6' }),
            font:   e.is_ghost
                ? { size: 8, align: 'middle', color: '#7c2d12', background: 'white', strokeWidth: 2, strokeColor: 'white' }
                : { size: 8, align: 'middle', color: '#374151', background: 'white', strokeWidth: 2, strokeColor: 'white' },
            width:  e.is_ghost ? 2 : 1.5,
            smooth: { type: 'dynamic' },
        }));

        const container = document.getElementById('area-network');
        const nodeDS    = new vis.DataSet(visNodes);
        const edgeDS    = new vis.DataSet(visEdges);

        const network = new vis.Network(container, { nodes: nodeDS, edges: edgeDS }, {
            nodes:  { borderWidth: 2, shadow: { enabled: true, color: 'rgba(0,0,0,.08)', x: 0, y: 2, size: 5 } },
            edges:  { arrows: { to: { enabled: false } }, smooth: { type: 'dynamic' } },
            physics: {
                enabled: true,
                solver: 'forceAtlas2Based',
                forceAtlas2Based: { gravitationalConstant: -60, springConstant: 0.08, springLength: 120, damping: 0.9 },
                stabilization: { iterations: 300, updateInterval: 25 },
            },
            interaction: {
                hover: true,
                tooltipDelay: 300,
                navigationButtons: false,
                keyboard: { enabled: true, speed: { x: 10, y: 10, zoom: 0.03 } },
            },
            layout: { improvedLayout: false, randomSeed: 1 },
        });

        // Badge inter-área
        const ghostCount = RAW_NODES.filter(n => n.is_ghost).length;
        if (ghostCount > 0) {
            const badge = document.getElementById('badge-interarea');
            document.getElementById('badge-interarea-text').textContent =
                ghostCount + ' conexión' + (ghostCount !== 1 ? 'es' : '') + ' inter-área';
            badge.classList.remove('hidden');
            badge.classList.add('inline-flex');
        }

        network.on('stabilized', () => {
            document.getElementById('network-loading').style.display = 'none';
            network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' }, padding: 40 });
        });

        document.getElementById('btn-fit').addEventListener('click', () =>
            network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' } }));

        document.getElementById('btn-stabilize').addEventListener('click', () => {
            document.getElementById('network-loading').style.display = 'flex';
            network.stabilize(200);
        });

        // ── Interacción tabla ↔ diagrama ──────────────────────────────────────────
        const allRows = () => document.querySelectorAll('#sw-list tr.sw-row:not(.hidden)');

        function highlightRow(nodeId) {
            allRows().forEach(r => {
                if (r.dataset.node === nodeId) {
                    r.classList.add('!bg-blue-50', 'ring-1', 'ring-inset', 'ring-blue-200');
                    r.classList.remove('opacity-40');
                } else {
                    r.classList.add('opacity-40');
                    r.classList.remove('!bg-blue-50', 'ring-1', 'ring-inset', 'ring-blue-200');
                }
            });
        }

        function clearHighlight() {
            allRows().forEach(r => {
                r.classList.remove('!bg-blue-50', 'ring-1', 'ring-inset', 'ring-blue-200', 'opacity-40');
            });
        }

        function scrollToRow(nodeId) {
            const row = document.querySelector(`#sw-list tr[data-node="${nodeId}"]`);
            if (row) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        document.querySelectorAll('#sw-list tr.sw-row').forEach(row => {
            const nodeId = row.dataset.node;

            row.addEventListener('mouseenter', () => {
                if (nodeDS.get(nodeId)) {
                    network.selectNodes([nodeId], false);
                    highlightRow(nodeId);
                }
            });
            row.addEventListener('mouseleave', () => {
                network.unselectAll();
                clearHighlight();
            });
            row.addEventListener('click', () => {
                if (nodeDS.get(nodeId)) {
                    network.focus(nodeId, { scale: 1.8, animation: { duration: 600, easingFunction: 'easeInOutQuad' } });
                    network.selectNodes([nodeId], false);
                    highlightRow(nodeId);
                }
            });
        });

        network.on('hoverNode', params => {
            clearHighlight();
            highlightRow(params.node);
            scrollToRow(params.node);
        });
        network.on('blurNode', () => clearHighlight());
        network.on('selectNode', params => {
            if (params.nodes.length === 1) scrollToRow(params.nodes[0]);
        });

        // ── Búsqueda rápida en la lista ───────────────────────────────────────────
        document.getElementById('sw-filter').addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#sw-list tr.sw-row').forEach(r => {
                const match = !q || r.dataset.name.includes(q);
                r.classList.toggle('hidden', !match);
            });
        });
    })();
    </script>
    @endpush

</x-admin-layout>
