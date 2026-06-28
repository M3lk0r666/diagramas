<x-admin-layout :title="($switch->sys_name ?? 'Switch') . ' — Puertos | Diagramas'" :breadcrumbs="[
    ['name' => 'Dashboard',  'href' => route('dashboard')],
    ['name' => 'Switches',   'href' => route('admin.switches.index')],
    ['name' => $switch->sys_name ?? 'Switch', 'href' => route('admin.switches.show', $switch)],
    ['name' => 'Diagrama de puertos'],
]">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl">Diagrama de puertos — {{ $switch->sys_name ?? 'Switch' }}</h2>
            <a href="{{ route('admin.switches.ports-diagram', $switch) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-full transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-5v4m0-4h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
                Pantalla completa
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 px-4 space-y-6">

        {{-- ── INFO HEADER ─────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-4 text-sm">
            <div class="flex flex-wrap items-stretch gap-0 divide-x divide-gray-200 dark:divide-gray-700">
                @if($switch->system_type)
                    <div class="pr-6 mr-6 first:pl-0">
                        <p class="text-gray-400 text-xs uppercase tracking-wide font-medium mb-1">Modelo</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $switch->system_type }}</p>
                    </div>
                @endif
                @if($switch->serial_number)
                    <div class="px-6">
                        <p class="text-gray-400 text-xs uppercase tracking-wide font-medium mb-1">Serie</p>
                        <p class="font-mono text-gray-700 dark:text-gray-200">{{ $switch->serial_number }}</p>
                    </div>
                @endif
                @if($switch->system_mac)
                    <div class="px-6">
                        <p class="text-gray-400 text-xs uppercase tracking-wide font-medium mb-1">MAC</p>
                        <p class="font-mono text-gray-700 dark:text-gray-200">{{ $switch->system_mac }}</p>
                    </div>
                @endif
                @if($switch->management_ip)
                    <div class="px-6">
                        <p class="text-gray-400 text-xs uppercase tracking-wide font-medium mb-1">IP Gestión</p>
                        <p class="font-mono font-semibold text-blue-700 dark:text-blue-400">{{ $switch->management_ip }}</p>
                    </div>
                @endif
                @if($switch->firmware_version)
                    <div class="px-6">
                        <p class="text-gray-400 text-xs uppercase tracking-wide font-medium mb-1">Firmware</p>
                        <p class="font-mono text-gray-700 dark:text-gray-200">{{ $switch->firmware_version }}</p>
                    </div>
                @endif
                <div class="pl-6 ml-auto text-right self-center">
                    <p class="text-gray-400 text-xs uppercase tracking-wide font-medium mb-1">Puertos activos</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100 text-lg">{{ count($switch->active_ports ?? []) }}</p>
                </div>
            </div>
        </div>

        {{-- ── GRAFO DE PUERTOS ─────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-4">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200">Conexiones de puertos activos</h3>
                <div class="flex items-center gap-4 text-xs text-gray-500">
                    @if($isStacked)
                        <span class="flex items-center gap-1.5">
                            <img src="{{ route('admin.topology.icon', 'stack_switch.png') }}" class="w-4 h-4 object-contain" alt="">
                            Slot (stack)
                        </span>
                    @endif
                    <span class="flex items-center gap-1.5">
                        <img src="{{ route('admin.topology.icon', 'access_switch.png') }}" class="w-4 h-4 object-contain" style="filter: hue-rotate(110deg) saturate(1.5)" alt="">
                        Vecino documentado
                    </span>
                    <span class="flex items-center gap-1.5">
                        <img src="{{ route('admin.topology.icon', 'port-not-data.png') }}" class="w-4 h-4 object-contain opacity-70" alt="">
                        Activo - No documentado
                    </span>
                </div>
            </div>
            <div id="ports-graph" class="w-full" style="height: 620px;"></div>
        </div>

        {{-- ── TABLA DE PUERTOS ACTIVOS ─────────────────────────── --}}
        @if(count($portsList))
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-700 dark:text-gray-200">
                        Puertos activos
                        <span class="ml-1 text-xs font-normal text-gray-400">({{ count($portsList) }})</span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Pasa el mouse sobre una fila para resaltar el nodo en el diagrama · Clic para centrar</p>
                </div>
                @php
                    $gwRoute = $switch->ip_routes
                        ? collect($switch->ip_routes)->firstWhere('ori', '#d')
                        : null;
                @endphp
                @if($gwRoute)
                    <span class="inline-block bg-green-600 text-white text-xs font-semibold px-3 py-1 rounded-full">
                        Gateway: {{ $gwRoute['gateway'] }}
                    </span>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="ports-table">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-3 text-left w-6"></th>
                            @if($isStacked)
                                <th class="px-4 py-3 text-left">Slot</th>
                            @endif
                            <th class="px-4 py-3 text-left">Puerto</th>
                            <th class="px-4 py-3 text-left">Vecino / Estado</th>
                            <th class="px-4 py-3 text-left">VLAN</th>
                            <th class="px-4 py-3 text-left">Velocidad</th>
                            <th class="px-4 py-3 text-left">Dúplex</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700" id="ports-tbody">
                        @foreach($portsList as $p)
                        <tr class="transition-colors cursor-pointer select-none"
                            data-node-id="{{ $p['nodeId'] }}"
                            data-node-color="{{ $p['color'] }}">
                            {{-- Indicador de color --}}
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0"
                                      style="background:{{ $p['color'] }}"></span>
                            </td>
                            @if($isStacked)
                                <td class="px-4 py-2.5 font-mono text-xs text-gray-500">
                                    Slot {{ $p['slot'] ?? '—' }}
                                </td>
                            @endif
                            <td class="px-4 py-2.5 font-mono text-xs font-semibold text-gray-700 dark:text-gray-200">
                                {{ $p['port'] }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if($p['desc'])
                                    <span class="font-medium text-gray-800 dark:text-gray-100">{{ $p['desc'] }}</span>
                                    @if($p['inDb'])
                                        <span class="ml-1.5 inline-block px-1.5 py-0.5 bg-green-100 text-green-700 text-xs rounded-full font-medium">BD</span>
                                    @else
                                        <span class="ml-1.5 inline-block px-1.5 py-0.5 bg-red-100 text-red-600 text-xs rounded-full font-medium">Sin registro</span>
                                    @endif
                                @else
                                    <span class="text-gray-400 italic text-xs">Activo · No documentado</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">
                                {{ $p['vlan'] ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-500">
                                {{ $p['speed'] ? $p['speed'] . ' Mbps' : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-500">
                                {{ $p['duplex'] ?? '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>

    @push('js')
        <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
        <script>
            const centerNode = @json($centerNode);
            const portNodes  = @json($portNodes);
            const portEdges  = @json($portEdges);

            const allNodes = new vis.DataSet([centerNode, ...portNodes]);
            const allEdges = new vis.DataSet(portEdges.map(e => ({
                from:   e.from,
                to:     e.to,
                label:  e.label  ?? undefined,
                font:   e.font   ?? undefined,
                color:  e.color,
                arrows: '',
                dashes: e.dashes ?? false,
                smooth: false,
            })));

            const network = new vis.Network(
                document.getElementById('ports-graph'),
                { nodes: allNodes, edges: allEdges },
                {
                    layout:  { hierarchical: { enabled: false } },
                    physics: { enabled: false },
                    nodes: {
                        shape:  'box',
                        margin: { top: 6, bottom: 6, left: 10, right: 10 },
                        font:   { size: 12 },
                        shapeProperties: { useImageSize: false },
                    },
                    edges:       { smooth: false },
                    interaction: { hover: true, tooltipDelay: 150, zoomView: true, dragView: true },
                }
            );

            // Ajustar vista al cargar
            network.once('afterDrawing', () => network.fit({ animation: false }));

            // ── Interacción tabla ↔ diagrama ──────────────────────────
            const tbody = document.getElementById('ports-tbody');
            if (tbody) {
                tbody.querySelectorAll('tr[data-node-id]').forEach(row => {
                    const nodeId = parseInt(row.dataset.nodeId, 10);

                    // Hover: seleccionar nodo (resalta con borde azul por defecto de vis)
                    row.addEventListener('mouseenter', () => {
                        row.style.backgroundColor = 'rgba(59,130,246,0.07)';
                        network.setSelection(
                            { nodes: [nodeId], edges: [] },
                            { unselectAll: true, highlightEdges: true }
                        );
                    });

                    row.addEventListener('mouseleave', () => {
                        row.style.backgroundColor = '';
                        network.setSelection({ nodes: [], edges: [] });
                    });

                    // Clic: centrar y hacer zoom al nodo
                    row.addEventListener('click', () => {
                        network.focus(nodeId, {
                            scale: 1.4,
                            animation: { duration: 400, easingFunction: 'easeInOutQuad' },
                        });
                        network.setSelection(
                            { nodes: [nodeId], edges: [] },
                            { unselectAll: true, highlightEdges: true }
                        );
                    });
                });
            }

            // Clic en nodo del diagrama: resalta la fila correspondiente en la tabla
            network.on('click', params => {
                if (!tbody) return;
                tbody.querySelectorAll('tr').forEach(r => r.classList.remove('ring-2', 'ring-blue-400', 'bg-blue-50'));
                if (params.nodes.length) {
                    const id = params.nodes[0];
                    const match = tbody.querySelector(`tr[data-node-id="${id}"]`);
                    if (match) {
                        match.classList.add('bg-blue-50', 'dark:bg-blue-900/30');
                        match.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        setTimeout(() => match.classList.remove('bg-blue-50', 'dark:bg-blue-900/30'), 1500);
                    }
                }
            });
        </script>
    @endpush

</x-admin-layout>
