<x-admin-layout title="Topología | Diagramas" :breadcrumbs="[['name' => 'Dashboard', 'href' => route('dashboard')], ['name' => 'Topología de red']]">

    <x-slot name="header">
        <h2 class="font-semibold text-xl">Topología de red</h2>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">

            {{-- ── Filtros (cliente → diagrama) + acciones ─────────── --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    @if ($clients->count() > 0)
                        <form method="GET" action="{{ route('admin.topology.index') }}" class="flex items-center gap-2">
                            <label class="text-sm text-gray-500 font-medium">Cliente:</label>
                            <select name="client" onchange="this.form.submit()"
                                class="text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Todos</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) $activeClient === (string) $client->id)>
                                        {{ $client->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif

                    @if ($batches->count() > 1)
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-gray-500 font-medium">Diagrama:</span>
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

                <a href="{{ route('admin.topology.custom.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    Topología personalizada
                </a>

                <a href="{{ route('admin.topology.full', request()->only('client', 'batch')) }}" target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 8V4m0 0h4M4 4l5 5m11-5v4m0-4h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                    </svg>
                    Ver en pantalla completa
                </a>
            </div>

            {{-- ── Grafo vis-network ────────────────────────────── --}}
            <div id="topology-graph"
                class="w-full bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden"
                style="height: max(200px, calc(40vh - 180px));">
            </div>

            {{-- Leyenda de lotes --}}
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

            {{-- ── Panel de switches (client-side, paginado) ───────── --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <div
                    class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200">
                            Equipos en topología
                            <span id="sw-count-label" class="text-xs font-normal text-gray-400 ml-1"></span>
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">Pasa el mouse para resaltar en el diagrama · Clic para
                            centrar y ver vecinos</p>
                    </div>
                    {{-- Búsqueda client-side --}}
                    <div class="relative">
                        <input id="sw-search" type="text" placeholder="Buscar equipo…"
                            class="text-sm border-gray-300 rounded-lg pl-8 pr-3 py-1.5 focus:ring-blue-500 focus:border-blue-500 w-52">
                        <svg class="absolute left-2.5 top-2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                        </svg>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Hostname</th>
                            <th class="px-4 py-3 text-left">Modelo</th>
                            <th class="px-4 py-3 text-left">Rol</th>
                            <th class="px-4 py-3 text-center">Tipo</th>
                            <th class="px-4 py-3 text-left">Diagrama</th>
                        </tr>
                    </thead>
                    <tbody id="sw-tbody" class="divide-y divide-gray-100 dark:divide-gray-700">
                        {{-- Renderizado por JS --}}
                    </tbody>
                </table>

                {{-- Paginación --}}
                <div id="sw-pagination"
                    class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
                    <span id="sw-page-info"></span>
                    <div class="flex items-center gap-2">
                        <button id="sw-prev"
                            class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-medium">
                            ← Anterior
                        </button>
                        <div id="sw-page-btns" class="flex gap-1"></div>
                        <button id="sw-next"
                            class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-medium">
                            Siguiente →
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Tabla de conexiones (paginada client-side) ───────── --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div
                    class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200">
                            Conexiones EDP
                            <span id="edp-count-label" class="text-xs font-normal text-gray-400 ml-1"></span>
                        </h3>
                    </div>
                    <div class="relative">
                        <input id="edp-search" type="text" placeholder="Buscar switch…"
                            class="text-sm border-gray-300 rounded-lg pl-8 pr-3 py-1.5 focus:ring-blue-500 focus:border-blue-500 w-52">
                        <svg class="absolute left-2.5 top-2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                        </svg>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-4 py-3 text-left">Diagrama</th>
                                <th class="px-4 py-3 text-left">Switch origen</th>
                                <th class="px-4 py-3 text-center">Puerto orig.</th>
                                <th class="px-4 py-3 text-left">Switch destino</th>
                                <th class="px-4 py-3 text-left">MAC destino</th>
                                <th class="px-4 py-3 text-center">Puerto dest.</th>
                                <th class="px-4 py-3 text-center">VLANs</th>
                            </tr>
                        </thead>
                        <tbody id="edp-tbody" class="divide-y divide-gray-100 dark:divide-gray-700">
                            {{-- renderizado por JS --}}
                        </tbody>
                    </table>
                </div>

                <div id="edp-pagination"
                    class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
                    <span id="edp-page-info"></span>
                    <div class="flex items-center gap-2">
                        <button id="edp-prev"
                            class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-medium">
                            ← Anterior
                        </button>
                        <div id="edp-page-btns" class="flex gap-1"></div>
                        <button id="edp-next"
                            class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-medium">
                            Siguiente →
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @push('js')
        <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
        <script>
            // ── Iconos y datos ───────────────────────────────────────────
            const iconMap = {
                core: '{{ route('admin.topology.icon', 'core_switch.png') }}',
                backbone: '{{ route('admin.topology.icon', 'backbone_switch.png') }}',
                distribution: '{{ route('admin.topology.icon', 'dist_switch.png') }}',
                access: '{{ route('admin.topology.icon', 'access_switch.png') }}',
            };
            const stackIcon = '{{ route('admin.topology.icon', 'stack_switch.png') }}';

            function nodeIcon(n) {
                return n.is_stacked ? stackIcon : (iconMap[n.role] ?? iconMap.access);
            }

            function nodeSize(r) {
                return r === 'core' ? 40 : r === 'backbone' ? 35 : r === 'distribution' ? 30 : 25;
            }

            const allNodes = new vis.DataSet(@json($nodes).map(n => ({
                ...n,
                shape: 'image',
                image: nodeIcon(n),
                size: nodeSize(n.role),
                font: {
                    size: 11,
                    color: '#1e293b'
                },
                color: {
                    border: 'transparent',
                    background: 'transparent',
                    highlight: {
                        border: '#3B82F6',
                        background: '#DBEAFE'
                    }
                },
            })));

            const allEdges = new vis.DataSet(@json($graphEdges).map(e => e.dashes ? ({
                from: e.from,
                to: e.to,
                dashes: true,
                arrows: e.arrows ?? '',
                color: e.color ?? {
                    color: '#CBD5E1'
                },
                length: e.length,
                smooth: false,
            }) : ({
                from: e.from,
                to: e.to,
                label: e.label,
                arrows: '',
                font: {
                    size: 9,
                    align: 'middle',
                    multi: true
                },
                color: {
                    color: '#94A3B8'
                },
                smooth: {
                    type: 'dynamic'
                },
            })));

            // Mapa batchId → nombre para la tabla
            const batchNames = {};
            @foreach ($batches as $b)
                batchNames[{{ $b['id'] }}] = @json($b['name']);
            @endforeach

            // ── Vis-network ───────────────────────────────────────────────
            const network = new vis.Network(
                document.getElementById('topology-graph'), {
                    nodes: allNodes,
                    edges: allEdges
                }, {
                    nodes: {
                        shape: 'image',
                        font: {
                            size: 11
                        },
                        shapeProperties: {
                            useImageSize: false
                        }
                    },
                    edges: {
                        smooth: {
                            type: 'dynamic'
                        },
                        font: {
                            size: 9,
                            align: 'middle'
                        }
                    },
                    physics: {
                        barnesHut: {
                            gravitationalConstant: -12000,
                            springLength: 200,
                            springConstant: 0.04
                        },
                        stabilization: {
                            iterations: 200
                        },
                    },
                }
            );

            // Clic en nodo del diagrama → resaltar fila en tabla
            network.on('click', params => {
                const tbody = document.getElementById('sw-tbody');
                tbody.querySelectorAll('tr').forEach(r => r.classList.remove('!bg-blue-50'));
                if (params.nodes.length) {
                    const id = params.nodes[0];
                    const match = tbody.querySelector(`tr[data-node-id="${id}"]`);
                    if (match) {
                        match.classList.add('!bg-blue-50');
                        match.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest'
                        });
                        setTimeout(() => match.classList.remove('!bg-blue-50'), 1800);
                    } else {
                        // El nodo puede estar en otra página → ir a él
                        const nodeData = allNodes.get(id);
                        if (nodeData) focusNodeInTable(id);
                    }
                }
            });

            // ── Panel de switches paginado ────────────────────────────────
            const SW_PER_PAGE = 15;
            let swPage = 1;
            let swVisibleNodes = []; // todos los nodos visibles (tras filtro lote + búsqueda)
            let swActiveBatch = null; // filtro lote activo
            let swSearch = '';

            function roleLabel(r) {
                return {
                    core: 'Core',
                    backbone: 'Backbone',
                    distribution: 'Distribución',
                    access: 'Acceso'
                } [r] ?? r ?? '—';
            }

            function roleBadge(r) {
                const colors = {
                    core: 'bg-purple-100 text-purple-700',
                    backbone: 'bg-indigo-100 text-indigo-700',
                    distribution: 'bg-blue-100 text-blue-700',
                    access: 'bg-gray-100 text-gray-600',
                };
                return colors[r] ?? 'bg-gray-100 text-gray-600';
            }

            function buildSwRows() {
                let nodes = allNodes.get();
                // Filtro por lote
                if (swActiveBatch !== null) nodes = nodes.filter(n => n.batch_id == swActiveBatch);
                // Filtro por búsqueda
                if (swSearch) {
                    const q = swSearch.toLowerCase();
                    nodes = nodes.filter(n => (n.sys_name ?? '').toLowerCase().includes(q) ||
                        (n.model ?? '').toLowerCase().includes(q));
                }
                // Ordenar por nombre
                nodes.sort((a, b) => (a.sys_name ?? '').localeCompare(b.sys_name ?? ''));
                return nodes;
            }

            function renderSwTable(nodes, page) {
                swVisibleNodes = nodes;
                const total = nodes.length;
                const totalPages = Math.max(1, Math.ceil(total / SW_PER_PAGE));
                swPage = Math.min(page, totalPages);

                const start = (swPage - 1) * SW_PER_PAGE;
                const pageData = nodes.slice(start, start + SW_PER_PAGE);

                const tbody = document.getElementById('sw-tbody');
                if (pageData.length === 0) {
                    tbody.innerHTML =
                        `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Sin equipos para mostrar</td></tr>`;
                } else {
                    tbody.innerHTML = pageData.map(n => {
                        const slotLabel = n.slot !== null && n.slot !== undefined ?
                            `<span class="ml-1 text-gray-400 text-xs">Slot ${n.slot}</span>` : '';
                        return `
                    <tr data-node-id="${n.id}" data-batch="${n.batch_id}"
                        class="hover:bg-blue-50 cursor-pointer transition-colors select-none">
                        <td class="px-4 py-2.5 font-medium text-gray-800">
                            ${n.sys_name ?? '—'}${slotLabel}
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-500">${n.model ?? '—'}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium ${roleBadge(n.role)}">
                                ${roleLabel(n.role)}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-center text-xs text-gray-500">
                            ${n.is_stacked
                                ? '<span class="inline-block px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full text-xs">Stack</span>'
                                : '<span class="inline-block px-2 py-0.5 bg-gray-50 text-gray-500 rounded-full text-xs">Standalone</span>'
                            }
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-400">${batchNames[n.batch_id] ?? '—'}</td>
                    </tr>`;
                    }).join('');
                }

                // Hover y click
                tbody.querySelectorAll('tr[data-node-id]').forEach(row => {
                    const nodeId = row.dataset.nodeId;

                    row.addEventListener('mouseenter', () => {
                        network.setSelection({
                            nodes: [nodeId],
                            edges: []
                        }, {
                            unselectAll: true,
                            highlightEdges: true
                        });
                    });
                    row.addEventListener('mouseleave', () => {
                        network.setSelection({
                            nodes: [],
                            edges: []
                        });
                    });
                    row.addEventListener('click', () => {
                        network.focus(nodeId, {
                            scale: 1.6,
                            animation: {
                                duration: 450,
                                easingFunction: 'easeInOutQuad'
                            },
                        });
                        network.setSelection({
                            nodes: [nodeId],
                            edges: []
                        }, {
                            unselectAll: true,
                            highlightEdges: true
                        });
                    });
                });

                // Actualizar controles de paginación
                document.getElementById('sw-count-label').textContent = `(${total})`;
                document.getElementById('sw-page-info').textContent =
                    total > 0 ? `Página ${swPage} de ${totalPages} · ${total} equipos` : '0 equipos';

                const prevBtn = document.getElementById('sw-prev');
                const nextBtn = document.getElementById('sw-next');
                prevBtn.disabled = swPage <= 1;
                nextBtn.disabled = swPage >= totalPages;

                // Botones de página (máx 7 visibles)
                const pageBtns = document.getElementById('sw-page-btns');
                let btnHtml = '';
                const range = pageRange(swPage, totalPages);
                range.forEach(p => {
                    if (p === '…') {
                        btnHtml += `<span class="px-2 text-gray-400">…</span>`;
                    } else {
                        const active = p === swPage;
                        btnHtml += `<button data-p="${p}"
                        class="px-2.5 py-1 rounded text-xs font-medium transition
                               ${active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-700'}"
                    >${p}</button>`;
                    }
                });
                pageBtns.innerHTML = btnHtml;
                pageBtns.querySelectorAll('button[data-p]').forEach(b => {
                    b.addEventListener('click', () => renderSwTable(swVisibleNodes, parseInt(b.dataset.p)));
                });
            }

            // Genera el rango de páginas a mostrar (1..totalPages con elipsis)
            function pageRange(current, total) {
                if (total <= 7) return Array.from({
                    length: total
                }, (_, i) => i + 1);
                const pages = [1];
                if (current > 3) pages.push('…');
                for (let p = Math.max(2, current - 1); p <= Math.min(total - 1, current + 1); p++) pages.push(p);
                if (current < total - 2) pages.push('…');
                pages.push(total);
                return pages;
            }

            function focusNodeInTable(nodeId) {
                const idx = swVisibleNodes.findIndex(n => n.id === nodeId);
                if (idx === -1) return;
                const page = Math.ceil((idx + 1) / SW_PER_PAGE);
                renderSwTable(swVisibleNodes, page);
            }

            // Evento prev / next
            document.getElementById('sw-prev').addEventListener('click', () => {
                if (swPage > 1) renderSwTable(swVisibleNodes, swPage - 1);
            });
            document.getElementById('sw-next').addEventListener('click', () => {
                const totalPages = Math.ceil(swVisibleNodes.length / SW_PER_PAGE);
                if (swPage < totalPages) renderSwTable(swVisibleNodes, swPage + 1);
            });

            // Búsqueda
            document.getElementById('sw-search').addEventListener('input', e => {
                swSearch = e.target.value.trim();
                renderSwTable(buildSwRows(), 1);
            });

            // Render inicial
            renderSwTable(buildSwRows(), 1);

            // ── Tabla conexiones EDP paginada ─────────────────────────────
            const EDP_PER_PAGE = 20;
            const edpAllRows = @json($edges); // array PHP → JS
            let edpPage = 1;
            let edpActiveBatch = null;
            let edpSearch = '';
            let edpVisible = [];

            function buildEdpRows() {
                let rows = edpAllRows;
                if (edpActiveBatch !== null) rows = rows.filter(r => r.batch_id == edpActiveBatch);
                if (edpSearch) {
                    const q = edpSearch.toLowerCase();
                    rows = rows.filter(r =>
                        (r.src_name ?? '').toLowerCase().includes(q) ||
                        (r.dst_name ?? '').toLowerCase().includes(q)
                    );
                }
                return rows;
            }

            function renderEdpTable(rows, page) {
                edpVisible = rows;
                const total = rows.length;
                const totalPages = Math.max(1, Math.ceil(total / EDP_PER_PAGE));
                edpPage = Math.min(page, totalPages);
                const start = (edpPage - 1) * EDP_PER_PAGE;
                const pageData = rows.slice(start, start + EDP_PER_PAGE);

                const tbody = document.getElementById('edp-tbody');
                if (pageData.length === 0) {
                    tbody.innerHTML =
                        `<tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Sin conexiones para mostrar</td></tr>`;
                } else {
                    tbody.innerHTML = pageData.map(e => `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="px-4 py-2">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                ${e.batch_name ?? '—'}
                            </span>
                        </td>
                        <td class="px-4 py-2 font-medium">${e.src_name ?? '—'}</td>
                        <td class="px-4 py-2 text-center font-bold text-blue-700">${e.src_port ?? '—'}</td>
                        <td class="px-4 py-2 font-medium">${e.dst_name ?? '—'}</td>
                        <td class="px-4 py-2 font-mono text-xs text-gray-400">${e.dst_mac ?? '—'}</td>
                        <td class="px-4 py-2 text-center font-bold text-blue-700">${e.dst_port ?? '—'}</td>
                        <td class="px-4 py-2 text-center text-gray-600">${e.num_vlans ?? '—'}</td>
                    </tr>`).join('');
                }

                document.getElementById('edp-count-label').textContent = `(${total})`;
                document.getElementById('edp-page-info').textContent =
                    total > 0 ? `Página ${edpPage} de ${totalPages} · ${total} conexiones` : '0 conexiones';

                document.getElementById('edp-prev').disabled = edpPage <= 1;
                document.getElementById('edp-next').disabled = edpPage >= totalPages;

                const pageBtns = document.getElementById('edp-page-btns');
                pageBtns.innerHTML = pageRange(edpPage, totalPages).map(p =>
                    p === '…' ?
                    `<span class="px-2 text-gray-400">…</span>` :
                    `<button data-p="${p}" class="px-2.5 py-1 rounded text-xs font-medium transition ${p === edpPage ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-700'}">${p}</button>`
                ).join('');
                pageBtns.querySelectorAll('button[data-p]').forEach(b =>
                    b.addEventListener('click', () => renderEdpTable(edpVisible, parseInt(b.dataset.p)))
                );
            }

            document.getElementById('edp-prev').addEventListener('click', () => {
                if (edpPage > 1) renderEdpTable(edpVisible, edpPage - 1);
            });
            document.getElementById('edp-next').addEventListener('click', () => {
                const tp = Math.ceil(edpVisible.length / EDP_PER_PAGE);
                if (edpPage < tp) renderEdpTable(edpVisible, edpPage + 1);
            });
            document.getElementById('edp-search').addEventListener('input', e => {
                edpSearch = e.target.value.trim();
                renderEdpTable(buildEdpRows(), 1);
            });

            // Render inicial
            renderEdpTable(buildEdpRows(), 1);

            // ── Filtro por lote ───────────────────────────────────────────
            const batchBtns = document.querySelectorAll('.batch-btn');
            // (connection-row ya no existe — la tabla EDP es client-side)

            function filterBatch(batchId) {
                swActiveBatch = batchId;
                edpActiveBatch = batchId;

                // Botones activos
                batchBtns.forEach(b => {
                    const active = batchId === null ? b.dataset.id === 'all' : b.dataset.id == batchId;
                    b.classList.toggle('bg-blue-600', active);
                    b.classList.toggle('text-white', active);
                    b.classList.toggle('bg-gray-100', !active);
                    b.classList.toggle('text-gray-600', !active);
                });

                // Filtrar nodos del grafo
                if (batchId === null) {
                    allNodes.update(allNodes.get().map(n => ({
                        ...n,
                        hidden: false
                    })));
                    allEdges.update(allEdges.get().map(e => ({
                        ...e,
                        hidden: false
                    })));
                } else {
                    const visible = new Set(allNodes.get().filter(n => n.batch_id == batchId).map(n => n.id));
                    allNodes.update(allNodes.get().map(n => ({
                        ...n,
                        hidden: !visible.has(n.id)
                    })));
                    allEdges.update(allEdges.get().map(e => ({
                        ...e,
                        hidden: !visible.has(e.from)
                    })));
                }

                // Actualizar tabla switches y tabla EDP
                renderSwTable(buildSwRows(), 1);
                renderEdpTable(buildEdpRows(), 1);
            }
        </script>
    @endpush

</x-admin-layout>
