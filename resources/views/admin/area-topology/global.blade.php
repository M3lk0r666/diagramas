<x-admin-layout
    title="Diagrama Global · {{ $client->name }} | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard',   'href' => route('dashboard')],
        ['name' => 'Áreas',       'href' => route('admin.areas.index')],
        ['name' => $client->name, 'href' => route('admin.areas.client', $client)],
        ['name' => 'Diagrama global'],
    ]">

    <div class="flex flex-col -mx-4 sm:-mx-6 lg:-mx-8" style="height: calc(100vh - 130px);">

        {{-- ── Barra superior ── --}}
        <div class="flex items-center justify-between px-5 py-2.5 bg-white border-b border-gray-200 shrink-0 gap-3 flex-wrap">
            <div class="flex items-center gap-2.5">
                <span class="font-semibold text-gray-800 text-sm">{{ $client->name }}</span>
                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                    {{ $batches->count() }} áreas · {{ $nodes->count() }} switches
                </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button id="btn-expand-all"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600
                           bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m16-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                    Expandir todo
                </button>
                <button id="btn-collapse-all"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600
                           bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"/>
                    </svg>
                    Colapsar todo
                </button>
                <button id="btn-fit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600
                           bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                    Ajustar vista
                </button>
                <a href="{{ route('admin.areas.client', $client) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600
                          bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    ← Áreas
                </a>
            </div>
        </div>

        {{-- ── Contenido: diagrama (izq) + panel áreas (der) ── --}}
        <div class="flex flex-1 overflow-hidden">

            {{-- Diagrama global --}}
            <div class="relative bg-gray-50 border-r border-gray-200 flex-1 min-w-0">
                <div id="global-network" class="w-full h-full"></div>

                {{-- Leyenda --}}
                <div class="absolute bottom-3 left-3 z-10 bg-white/90 backdrop-blur-sm rounded-lg border border-gray-200
                            shadow-sm px-3 py-2.5 text-xs text-gray-600 pointer-events-none" style="min-width:170px">
                    <div class="font-semibold text-gray-700 mb-2">Iconos</div>
                    <div class="space-y-1 mb-3">
                        <div class="flex items-center gap-2"><img src="{{ route('admin.topology.icon', 'core_switch.png') }}"     class="w-5 h-5 object-contain" alt=""> Core</div>
                        <div class="flex items-center gap-2"><img src="{{ route('admin.topology.icon', 'backbone_switch.png') }}" class="w-5 h-5 object-contain" alt=""> Backbone</div>
                        <div class="flex items-center gap-2"><img src="{{ route('admin.topology.icon', 'dist_switch.png') }}"     class="w-5 h-5 object-contain" alt=""> Distribución</div>
                        <div class="flex items-center gap-2"><img src="{{ route('admin.topology.icon', 'access_switch.png') }}"   class="w-5 h-5 object-contain" alt=""> Acceso</div>
                        <div class="flex items-center gap-2"><img src="{{ route('admin.topology.icon', 'stack_switch.png') }}"    class="w-5 h-5 object-contain" alt=""> Stack</div>
                    </div>
                    <div class="font-semibold text-gray-700 mb-2 pt-2 border-t border-gray-100">Conexiones</div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-7" style="border-top: 2px solid #94A3B8"></span> Intra-área
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="inline-block w-7" style="border-top: 2.5px dashed #F97316"></span> Inter-área
                    </div>
                </div>

                {{-- Spinner --}}
                <div id="network-loading"
                     class="absolute inset-0 flex items-center justify-center bg-gray-50/90 z-10">
                    <div class="flex flex-col items-center gap-3">
                        <svg class="w-8 h-8 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span class="text-sm text-gray-500">Calculando diagrama global…</span>
                    </div>
                </div>
            </div>

            {{-- Panel lateral: lista de áreas con botones expand/collapse --}}
            <div class="w-72 shrink-0 flex flex-col bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 shrink-0">
                    <h3 class="text-sm font-semibold text-gray-700">Áreas del cliente</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Clic en el área para expandir/colapsar · Hover para resaltar</p>
                </div>

                <div class="overflow-y-auto flex-1 divide-y divide-gray-50" id="areas-list">
                    @foreach($batchesMeta as $bm)
                        {{-- Inicia colapsado (bg-gray-50 + flecha abajo) --}}
                        <div class="area-item px-4 py-3 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors"
                             data-batch="{{ $bm['id'] }}"
                             data-expanded="false">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-3 h-3 rounded-sm shrink-0 border"
                                          style="background: {{ $bm['color']['bg'] }}; border-color: {{ $bm['color']['border'] }}">
                                    </span>
                                    <span class="text-sm font-medium text-gray-800 truncate">{{ $bm['name'] }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="text-xs text-gray-400">{{ $bm['sw_count'] }} sw</span>
                                    <span class="area-icon text-gray-400">
                                        {{-- flecha abajo = colapsado --}}
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <a href="{{ route('admin.areas.topology', [$client->id, $bm['id']]) }}"
                                   onclick="event.stopPropagation()"
                                   class="text-xs text-blue-600 hover:underline">Ver topología →</a>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Resumen de conexiones inter-área --}}
                <div class="shrink-0 px-4 py-3 border-t border-gray-100 bg-orange-50/60">
                    <div class="flex items-center gap-2 text-xs text-orange-700">
                        <span class="w-6 border-t-2 border-dashed border-orange-400 inline-block"></span>
                        <span class="font-medium">Conexiones inter-área marcadas en naranja</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Las líneas discontinuas naranja representan enlaces entre distintas áreas.</p>
                </div>
            </div>

        </div>
    </div>

    @push('js')
    <script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.6/standalone/umd/vis-network.min.js"></script>
    <script>
    (function () {
        /* ─────────────────────────────────────────────────────────────────
         *  DATOS desde PHP (todos los nodos/aristas del cliente)
         * ───────────────────────────────────────────────────────────────── */
        const RAW_NODES    = @json($nodes);       // todos los switches con batch_id, role…
        const RAW_EDGES    = @json($graphEdges);  // todas las aristas con inter_area flag
        const BATCHES_META = @json($batchesMeta); // [{id, name, sw_count, color:{bg,border}}]

        const ROLE_ICON = {
            core:     '{{ route('admin.topology.icon', 'core_switch.png') }}',
            backbone: '{{ route('admin.topology.icon', 'backbone_switch.png') }}',
            dist:     '{{ route('admin.topology.icon', 'dist_switch.png') }}',
            access:   '{{ route('admin.topology.icon', 'access_switch.png') }}',
            stack:    '{{ route('admin.topology.icon', 'stack_switch.png') }}',
            default:  '{{ route('admin.topology.icon', 'access_switch.png') }}',
        };

        /* ─────────────────────────────────────────────────────────────────
         *  MAPAS DE LOOKUP  (construidos una sola vez al inicio)
         * ───────────────────────────────────────────────────────────────── */
        const nodeMap      = {};   // nodeId  → raw node
        const batchNodeMap = {};   // batchId → [raw nodes]
        const batchMeta    = {};   // batchId → batch meta

        RAW_NODES.forEach(n => {
            nodeMap[n.id] = n;
            if (!batchNodeMap[n.batch_id]) batchNodeMap[n.batch_id] = [];
            batchNodeMap[n.batch_id].push(n);
        });
        BATCHES_META.forEach(b => { batchMeta[b.id] = b; });

        // Separar aristas intra-área e inter-área, asignar IDs estables
        const interEdges   = [];   // aristas entre batches distintos
        const batchEdgeMap = {};   // batchId → [aristas intra-área]

        RAW_EDGES.forEach((e, i) => {
            const re = { ...e, _vis_id: 'e' + i };
            if (e.inter_area) {
                re._src_batch = nodeMap[e.from]?.batch_id;
                re._dst_batch = nodeMap[e.to]?.batch_id;
                if (re._src_batch && re._dst_batch) interEdges.push(re);
            } else {
                const bId = e.batch_id ?? nodeMap[e.from]?.batch_id;
                if (bId) {
                    if (!batchEdgeMap[bId]) batchEdgeMap[bId] = [];
                    batchEdgeMap[bId].push(re);
                }
            }
        });

        /* ─────────────────────────────────────────────────────────────────
         *  ESTADO
         * ───────────────────────────────────────────────────────────────── */
        const clusterState = {};  // batchId → true=colapsado
        const proxyPos     = {};  // batchId → {x,y} posición del proxy

        BATCHES_META.forEach(b => { clusterState[b.id] = true; }); // todo colapsado al inicio

        /* ─────────────────────────────────────────────────────────────────
         *  CONSTRUCTORES DE ITEMS VIS-NETWORK
         * ───────────────────────────────────────────────────────────────── */
        function makeProxyNode(b, pos) {
            return {
                id:    'cluster_' + b.id,
                label: b.name + '\n' + b.sw_count + ' sw  ·  doble clic para expandir',
                x: pos.x, y: pos.y,
                shape: 'box',
                color: {
                    background: b.color.bg,
                    border:     b.color.border,
                    highlight:  { background: b.color.bg, border: b.color.border },
                    hover:      { background: b.color.bg, border: b.color.border },
                },
                font:            { size: 11, color: '#1E3A5F', face: 'Inter, sans-serif' },
                borderWidth:     2,
                widthConstraint: { minimum: 150, maximum: 230 },
                margin:          { top: 10, right: 14, bottom: 10, left: 14 },
                shadow:          { enabled: true, color: 'rgba(0,0,0,.10)', x: 0, y: 2, size: 6 },
                mass: Math.max(Math.ceil(b.sw_count / 4), 2),
                _batch_id: b.id,
                _is_proxy: true,
            };
        }

        function makeIndividualNode(n, center) {
            const nodes  = batchNodeMap[n.batch_id] ?? [];
            const idx    = nodes.indexOf(n);
            const total  = nodes.length;
            const angle  = (2 * Math.PI * idx / Math.max(total, 1)) - Math.PI / 2;
            const r      = Math.max(65, total * 9);
            const role   = n.is_stacked ? 'stack' : (n.role || 'access');
            return {
                id:    n.id,
                label: n.label,
                title: n.title,
                shape: 'image',
                image: ROLE_ICON[role] || ROLE_ICON.default,
                size:  18,
                x: center.x + r * Math.cos(angle),
                y: center.y + r * Math.sin(angle),
                font:  { size: 8, color: '#374151', face: 'Inter, sans-serif', vadjust: 4 },
                color: { border: 'rgba(0,0,0,0)', background: 'rgba(0,0,0,0)',
                         highlight: { border: '#3B82F6', background: '#EFF6FF' },
                         hover:     { border: '#93C5FD', background: '#F0F9FF' } },
                borderWidth:         0,
                borderWidthSelected: 2,
                _batch_id:  n.batch_id,
                _switch_id: n.switch_id,
            };
        }

        function makeIntraEdge(e) {
            return {
                id:     e._vis_id,
                from:   e.from,
                to:     e.to,
                label:  e.label || '',
                dashes: e.dashes || false,
                arrows: { to: false },
                color:  e.color || { color: '#94A3B8', highlight: '#3B82F6', hover: '#3B82F6' },
                font:   { size: 7, align: 'middle', color: '#6B7280', strokeWidth: 0 },
                width:  e.width || 1.5,
                smooth: { type: 'dynamic' },
            };
        }

        function resolveInterEdge(e) {
            return {
                id:   e._vis_id,
                from: clusterState[e._src_batch] ? 'cluster_' + e._src_batch : e.from,
                to:   clusterState[e._dst_batch] ? 'cluster_' + e._dst_batch : e.to,
                label:  e.label || '',
                dashes: [8, 4],
                arrows: { to: false },
                color:  { color: '#F97316', highlight: '#EA580C', hover: '#EA580C' },
                font:   { size: 7, align: 'middle', color: '#F97316', strokeWidth: 0 },
                width:  2.5,
                smooth: { type: 'dynamic' },
            };
        }

        /* ─────────────────────────────────────────────────────────────────
         *  INICIALIZACIÓN: solo N nodos proxy en círculo + aristas inter
         * ───────────────────────────────────────────────────────────────── */
        const N = BATCHES_META.length;
        const R = Math.max(300, N * 38);

        const initNodes = BATCHES_META.map((b, i) => {
            const angle = (2 * Math.PI * i / N) - Math.PI / 2;
            const pos   = { x: Math.round(R * Math.cos(angle)), y: Math.round(R * Math.sin(angle)) };
            proxyPos[b.id] = pos;
            return makeProxyNode(b, pos);
        });

        // Una sola arista por par único de clusters (evita cientos de paralelas)
        const clusterPairMap = new Map();
        interEdges.forEach(e => {
            const key = Math.min(e._src_batch, e._dst_batch) + '_' + Math.max(e._src_batch, e._dst_batch);
            if (!clusterPairMap.has(key)) {
                clusterPairMap.set(key, { src: e._src_batch, dst: e._dst_batch, count: 0 });
            }
            clusterPairMap.get(key).count++;
        });

        const initEdges = [...clusterPairMap.entries()].map(([key, p]) => ({
            id:     'pair_' + key,
            from:   'cluster_' + p.src,
            to:     'cluster_' + p.dst,
            label:  p.count > 1 ? p.count + ' enlaces' : '',
            dashes: [8, 4],
            arrows: { to: false },
            color:  { color: '#F97316', highlight: '#EA580C', hover: '#EA580C' },
            font:   { size: 7, align: 'middle', color: '#F97316', strokeWidth: 0 },
            width:  2.5,
            smooth: { type: 'dynamic' },
            _pair_key: key,
        }));

        const nodeDS = new vis.DataSet(initNodes);
        const edgeDS = new vis.DataSet(initEdges);

        const container = document.getElementById('global-network');

        // ── Physics OFF: los proxies ya están posicionados en círculo ─────────
        const network = new vis.Network(container, { nodes: nodeDS, edges: edgeDS }, {
            nodes:   { shadow: { enabled: true, color: 'rgba(0,0,0,.08)', x: 0, y: 2, size: 5 } },
            edges:   { smooth: { type: 'dynamic' } },
            physics: { enabled: false },
            interaction: {
                hover: true,
                tooltipDelay: 300,
                keyboard: { enabled: true, speed: { x: 10, y: 10, zoom: 0.03 } },
            },
            layout: { improvedLayout: false },
        });

        // Mostrar al instante — sin esperar estabilización
        document.getElementById('network-loading').style.display = 'none';
        network.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } });

        /* ─────────────────────────────────────────────────────────────────
         *  EXPAND / COLLAPSE  (gestión directa del DataSet)
         * ───────────────────────────────────────────────────────────────── */
        function expandArea(batchId, opts = {}) {
            if (!clusterState[batchId]) return;
            const b = batchMeta[batchId];
            if (!b) return;

            // 1. Guardar posición actual del proxy
            try { proxyPos[batchId] = network.getPosition('cluster_' + batchId); } catch (_) {}
            const center = proxyPos[batchId] ?? { x: 0, y: 0 };

            // 2. Añadir nodos individuales (posicionados en círculo alrededor del centro)
            nodeDS.add((batchNodeMap[batchId] ?? []).map(n => makeIndividualNode(n, center)));

            // 3. Actualizar aristas inter-área ANTES de eliminar el proxy
            clusterState[batchId] = false;
            refreshInterEdges(batchId);

            // 4. Eliminar proxy
            nodeDS.remove('cluster_' + batchId);

            // 5. Añadir aristas intra-área
            edgeDS.add((batchEdgeMap[batchId] ?? []).map(makeIntraEdge));

            updateAreaItem(batchId, false);

            if (!opts.skipPhysics) {
                runPhysicsBriefly(1400);
                setTimeout(() => network.fit({ animation: { duration: 400 } }), 1500);
            }
        }

        function collapseArea(batchId, opts = {}) {
            if (clusterState[batchId]) return;
            const b = batchMeta[batchId];
            if (!b) return;

            const pos = proxyPos[batchId] ?? { x: 0, y: 0 };

            // 1. Añadir proxy de vuelta
            nodeDS.add(makeProxyNode(b, pos));

            // 2. Actualizar aristas inter-área → usan proxy
            clusterState[batchId] = true;
            refreshInterEdges(batchId);

            // 3. Eliminar nodos individuales
            nodeDS.remove((batchNodeMap[batchId] ?? []).map(n => n.id));

            // 4. Eliminar aristas intra-área
            edgeDS.remove((batchEdgeMap[batchId] ?? []).map(e => e._vis_id));

            updateAreaItem(batchId, true);

            if (!opts.skipPhysics) {
                runPhysicsBriefly(700);
                setTimeout(() => network.fit({ animation: { duration: 400 } }), 800);
            }
        }

        function toggleArea(batchId) {
            if (clusterState[batchId]) expandArea(batchId);
            else collapseArea(batchId);
        }

        /**
         * Cuando se expande o colapsa un batch, recalcula las aristas inter-área.
         * Estrategia:
         *  - Si AMBOS extremos siguen colapsados → arista de par (cluster → cluster)
         *  - Si AL MENOS UNO está expandido → aristas individuales reales (sw → cluster o sw → sw)
         */
        function refreshInterEdges(batchId) {
            // Pares que involucran este batch
            const affectedPairs = new Map();
            clusterPairMap.forEach((p, key) => {
                if (p.src === batchId || p.dst === batchId) affectedPairs.set(key, p);
            });

            affectedPairs.forEach((p, key) => {
                const pairId     = 'pair_' + key;
                const srcCollapsed = clusterState[p.src];
                const dstCollapsed = clusterState[p.dst];

                if (srcCollapsed && dstCollapsed) {
                    // Ambos colapsados → restaurar arista de par
                    if (!edgeDS.get(pairId)) {
                        edgeDS.add({
                            id:     pairId,
                            from:   'cluster_' + p.src,
                            to:     'cluster_' + p.dst,
                            label:  p.count > 1 ? p.count + ' enlaces' : '',
                            dashes: [8, 4],
                            arrows: { to: false },
                            color:  { color: '#F97316', highlight: '#EA580C', hover: '#EA580C' },
                            font:   { size: 7, align: 'middle', color: '#F97316', strokeWidth: 0 },
                            width:  2.5,
                            smooth: { type: 'dynamic' },
                        });
                    }
                    // Eliminar aristas individuales del par si existieran
                    interEdges
                        .filter(e => pairKey(e) === key)
                        .forEach(e => { try { edgeDS.remove(e._vis_id); } catch (_) {} });
                } else {
                    // Al menos uno expandido → usar aristas individuales reales
                    if (edgeDS.get(pairId)) edgeDS.remove(pairId);

                    interEdges
                        .filter(e => pairKey(e) === key)
                        .forEach(e => {
                            const from = clusterState[e._src_batch] ? 'cluster_' + e._src_batch : e.from;
                            const to   = clusterState[e._dst_batch] ? 'cluster_' + e._dst_batch : e.to;
                            if (edgeDS.get(e._vis_id)) {
                                edgeDS.update({ id: e._vis_id, from, to });
                            } else {
                                edgeDS.add(resolveInterEdge(e));
                            }
                        });
                }
            });
        }

        function pairKey(e) {
            return Math.min(e._src_batch, e._dst_batch) + '_' + Math.max(e._src_batch, e._dst_batch);
        }

        function runPhysicsBriefly(ms = 1200) {
            network.setOptions({
                physics: {
                    enabled: true,
                    solver: 'forceAtlas2Based',
                    forceAtlas2Based: {
                        gravitationalConstant: -120,
                        springConstant: 0.06,
                        springLength: 140,
                        damping: 0.95,
                    },
                    stabilization: { enabled: false }, // sin límite por iteraciones, solo por tiempo
                },
            });
            setTimeout(() => network.setOptions({ physics: { enabled: false } }), ms);
        }

        /* ─────────────────────────────────────────────────────────────────
         *  SIDEBAR INTERACTIONS
         * ───────────────────────────────────────────────────────────────── */
        function updateAreaItem(batchId, collapsed) {
            const item = document.querySelector(`.area-item[data-batch="${batchId}"]`);
            if (!item) return;
            item.dataset.expanded = collapsed ? 'false' : 'true';
            const icon = item.querySelector('.area-icon');
            if (collapsed) {
                icon.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>`;
                item.classList.add('bg-gray-50');
                item.classList.remove('bg-white');
            } else {
                icon.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>`;
                item.classList.remove('bg-gray-50');
                item.classList.add('bg-white');
            }
        }

        document.querySelectorAll('.area-item').forEach(item => {
            const batchId = parseInt(item.dataset.batch);

            item.addEventListener('click', () => toggleArea(batchId));

            item.addEventListener('mouseenter', () => {
                if (clusterState[batchId]) {
                    // Resaltar el proxy box
                    if (nodeDS.get('cluster_' + batchId)) {
                        nodeDS.update({ id: 'cluster_' + batchId, borderWidth: 4 });
                    }
                } else {
                    // Atenuar nodos de otras áreas
                    const areaIds = new Set((batchNodeMap[batchId] ?? []).map(n => n.id));
                    nodeDS.getIds().forEach(id => {
                        nodeDS.update({ id, opacity: areaIds.has(id) ? 1.0 : 0.15 });
                    });
                }
            });

            item.addEventListener('mouseleave', () => {
                if (clusterState[batchId]) {
                    if (nodeDS.get('cluster_' + batchId)) {
                        nodeDS.update({ id: 'cluster_' + batchId, borderWidth: 2 });
                    }
                } else {
                    nodeDS.getIds().forEach(id => nodeDS.update({ id, opacity: 1.0 }));
                }
            });
        });

        /* ─────────────────────────────────────────────────────────────────
         *  EVENTOS DE RED
         * ───────────────────────────────────────────────────────────────── */
        // Doble clic en proxy → expandir
        network.on('doubleClick', params => {
            if (params.nodes.length !== 1) return;
            const node = nodeDS.get(params.nodes[0]);
            if (node?._is_proxy) expandArea(node._batch_id);
        });

        // Doble clic en nodo individual → colapsar su área
        network.on('doubleClick', () => {}); // placeholder (se puede ampliar)

        /* ─────────────────────────────────────────────────────────────────
         *  BOTONES GLOBALES
         * ───────────────────────────────────────────────────────────────── */
        document.getElementById('btn-expand-all').addEventListener('click', () => {
            BATCHES_META.forEach(b => expandArea(b.id, { skipPhysics: true }));
            runPhysicsBriefly(2000);
            setTimeout(() => network.fit({ animation: { duration: 500 } }), 2100);
        });

        document.getElementById('btn-collapse-all').addEventListener('click', () => {
            BATCHES_META.forEach(b => collapseArea(b.id, { skipPhysics: true }));
            runPhysicsBriefly(600);
            setTimeout(() => network.fit({ animation: { duration: 500 } }), 700);
        });

        document.getElementById('btn-fit').addEventListener('click', () =>
            network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' } }));

    })();
    </script>
    @endpush

</x-admin-layout>
