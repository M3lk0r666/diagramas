<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Topología de red — Pantalla completa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        html, body { height: 100%; margin: 0; }
        #topology-graph { width: 100%; height: 100%; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="h-screen w-screen flex flex-col">

        {{-- ── Barra superior: filtros + leyenda ─────────────────── --}}
        <div class="bg-white border-b border-gray-200 px-4 py-3 flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="font-semibold text-gray-800">Topología de red</h1>

                @if ($batches->count() > 1)
                    <div class="flex flex-wrap items-center gap-2">
                        <button onclick="filterBatch(null)"
                            class="batch-btn px-3 py-1 rounded-full text-xs font-medium bg-blue-600 text-white transition"
                            data-id="all">
                            Todos
                        </button>
                        @foreach ($batches as $batch)
                            <button onclick="filterBatch({{ $batch['id'] }})"
                                class="batch-btn px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-700 transition"
                                data-id="{{ $batch['id'] }}">
                                {{ $batch['name'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                @if ($batches->count() > 0)
                    <div class="flex flex-wrap gap-3 text-xs">
                        @foreach ($batches as $batch)
                            @php $color = $batchColors[$batch['id']] ?? '#EFF6FF'; @endphp
                            <span class="flex items-center gap-1.5">
                                <span class="inline-block w-3 h-3 rounded-sm border border-blue-400"
                                    style="background-color: {{ $color }}"></span>
                                {{ $batch['name'] }}
                            </span>
                        @endforeach
                    </div>
                @endif

                <span class="text-xs text-gray-400 hidden sm:inline">
                    Clic en nodo = zoom · Doble clic en fondo = ver todo
                </span>
                <button onclick="window.close()"
                    class="text-xs text-gray-400 hover:text-gray-600 px-2 py-1" title="Cerrar pestaña">
                    ✕ Cerrar
                </button>
            </div>
        </div>

        {{-- ── Grafo a pantalla completa ──────────────────────────── --}}
        <div class="flex-1 min-h-0">
            <div id="topology-graph"></div>
        </div>
    </div>

    <script>
        // ── Datos ────────────────────────────────────────────────

            // Mapeo rol -> icono (servidos via ruta Laravel)
            const iconMap = {
                core:         '{{ route("admin.topology.icon", "core_switch.png") }}',
                backbone:     '{{ route("admin.topology.icon", "backbone_switch.png") }}',
                distribution: '{{ route("admin.topology.icon", "dist_switch.png") }}',
                access:       '{{ route("admin.topology.icon", "access_switch.png") }}',
            };
            const stackIcon = '{{ route("admin.topology.icon", "stack_switch.png") }}';

            function nodeIcon(n) {
                if (n.is_stacked) return stackIcon;
                return iconMap[n.role] ?? iconMap.access;
            }
            function nodeSize(role) {
                if (role === 'core')         return 40;
                if (role === 'backbone')     return 35;
                if (role === 'distribution') return 30;
                return 25;
            }
            // Datos
            const allNodes = new vis.DataSet(@json($nodes).map(n => ({
                ...n,
                shape: 'image',
                image: nodeIcon(n),
                size:  nodeSize(n.role),
                font:  { size: 11, color: '#1e293b' },
                color: { border: 'transparent', background: 'transparent',
                         highlight: { border: '#3B82F6', background: '#DBEAFE' } },
            })));

            const allEdges = new vis.DataSet(@json($graphEdges).map(e => e.dashes ? ({
                from: e.from,
                to: e.to,
                dashes: true,
                arrows: e.arrows ?? '',
                color: e.color ?? { color: '#CBD5E1' },
                length: e.length,
                smooth: false,
            }) : ({
                from: e.from,
                to: e.to,
                label: e.label,
                arrows: '',
                font: { size: 9, align: 'middle', multi: true },
                color: { color: '#94A3B8' },
                smooth: { type: 'dynamic' },
            })));

        // Red
        const network = new vis.Network(
            document.getElementById('topology-graph'), {
                nodes: allNodes,
                edges: allEdges
            }, {
                    nodes: {
                        shape: 'image',
                        font: { size: 11 },
                        shapeProperties: { useImageSize: false },
                    },
                    edges: {
                        smooth: { type: 'dynamic' },
                        font:   { size: 9, align: 'middle' },
                    },
                    physics: {
                        barnesHut: {
                            gravitationalConstant: -12000,
                            springLength: 200,
                            springConstant: 0.04
                        },
                        stabilization: { iterations: 200 },
                    },
            }
        );

        // ── Clic en nodo → zoom al nodo ──────────────────────────
        // Doble clic → resetear vista completa
        network.on('click', params => {
            if (params.nodes.length === 1) {
                network.focus(params.nodes[0], {
                    scale: 1.8,
                    animation: { duration: 400, easingFunction: 'easeInOutQuad' },
                });
            }
        });
        network.on('doubleClick', params => {
            if (params.nodes.length === 0) {
                network.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
            }
        });

        // ── Filtro por lote ──────────────────────────────────────
        const batchBtns = document.querySelectorAll('.batch-btn');

        function filterBatch(batchId) {
            batchBtns.forEach(b => {
                const isActive = batchId === null ?
                    b.dataset.id === 'all' :
                    b.dataset.id == batchId;
                b.classList.toggle('bg-blue-600', isActive);
                b.classList.toggle('text-white', isActive);
                b.classList.toggle('bg-gray-100', !isActive);
                b.classList.toggle('text-gray-600', !isActive);
            });

            if (batchId === null) {
                allNodes.update(allNodes.get().map(n => ({ ...n, hidden: false })));
                allEdges.update(allEdges.get().map(e => ({ ...e, hidden: false })));
            } else {
                const visibleNodeIds = allNodes.get()
                    .filter(n => n.batch_id == batchId)
                    .map(n => n.id);

                allNodes.update(allNodes.get().map(n => ({
                    ...n, hidden: n.batch_id != batchId
                })));

                allEdges.update(allEdges.get().map(e => ({
                    ...e, hidden: !visibleNodeIds.includes(e.from)
                })));
            }
        }
    </script>
</body>
</html>
