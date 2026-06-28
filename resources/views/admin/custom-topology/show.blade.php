<x-admin-layout
    :title="$topologyName . ' | Topología personalizada'"
    :breadcrumbs="[
        ['name' => 'Dashboard',    'href' => route('dashboard')],
        ['name' => 'Topología',    'href' => route('admin.topology.index')],
        ['name' => 'Personalizada','href' => route('admin.topology.custom.create')],
        ['name' => $topologyName],
    ]">

    <div class="space-y-4">

        {{-- ── Barra de título + acciones ──────────────────────────────────── --}}
        <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">{{ $topologyName }}</h2>
                    <p class="text-sm text-gray-400 mt-0.5">{{ $switches->count() }} switches · topología personalizada</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    {{-- Editar selección --}}
                    <a href="{{ route('admin.topology.custom.create') }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300
                               hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar selección
                    </a>

                    {{-- Generar PNG --}}
                    <button id="btn-generate" onclick="generatePng()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                               text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span id="btn-generate-label">Generar diagrama PNG</span>
                    </button>

                    {{-- Ver imagen --}}
                    <a id="btn-view-image"
                        href="{{ route('admin.topology.custom.image', $pngKey) }}"
                        target="_blank"
                        class="{{ $hasImage ? 'inline-flex' : 'hidden' }} items-center gap-1.5 px-4 py-2
                               bg-green-600 hover:bg-green-700 text-white text-sm font-semibold
                               rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ver imagen
                    </a>

                    {{-- Descargar --}}
                    <a id="btn-download"
                        href="{{ route('admin.topology.custom.image', $pngKey) }}"
                        download="{{ Str::slug($topologyName) }}_topologia.png"
                        class="{{ $hasImage ? 'inline-flex' : 'hidden' }} items-center gap-1.5 px-4 py-2
                               bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold
                               rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Descargar
                    </a>
                </div>
            </div>
        </div>

        {{-- ── Grafo interactivo ──────────────────────────────────────────────── --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4">

                {{-- Controles del grafo --}}
                <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-purple-200 border border-purple-500"></span>Core
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-indigo-200 border border-indigo-500"></span>Backbone
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-blue-200 border border-blue-500"></span>Distribución
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-green-200 border border-green-500"></span>Acceso
                        </span>
                    </div>
                    <button onclick="network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' } })"
                        class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 transition">
                        Ajustar vista
                    </button>
                </div>

                <div id="topology-graph"
                    class="w-full bg-gray-50 rounded-xl border border-gray-200 overflow-hidden"
                    style="height: 520px;">
                </div>
            </div>
        </div>

        {{-- ── Switches en la topología ───────────────────────────────────────── --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h3 class="font-semibold text-gray-700">
                        Switches en la topología
                        <span class="text-xs font-normal text-gray-400 ml-1">({{ $switches->count() }})</span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Pasa el mouse para resaltar en el diagrama · Clic para hacer zoom</p>
                </div>
                <div class="relative">
                    <input id="sw-search" type="text" placeholder="Buscar…"
                        class="text-sm border-gray-300 rounded-lg pl-8 pr-3 py-1.5 w-44
                               focus:ring-blue-500 focus:border-blue-500">
                    <svg class="absolute left-2.5 top-2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </div>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Hostname</th>
                        <th class="px-4 py-3 text-left">Modelo</th>
                        <th class="px-4 py-3 text-left">IP gestión</th>
                        <th class="px-4 py-3 text-left">Rol</th>
                        <th class="px-4 py-3 text-center">Tipo</th>
                        <th class="px-4 py-3 text-left">Diagrama</th>
                        <th class="px-4 py-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="sw-tbody" class="divide-y divide-gray-100">
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
                <span id="sw-page-info"></span>
                <div class="flex items-center gap-2">
                    <button id="sw-prev"
                        class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600
                               disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-medium">
                        ← Anterior
                    </button>
                    <div id="sw-page-btns" class="flex gap-1"></div>
                    <button id="sw-next"
                        class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600
                               disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-medium">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- Overlay de carga PNG --}}
    <div id="generate-overlay"
        class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl p-8 flex flex-col items-center gap-4 max-w-sm w-full mx-4">
            <svg class="animate-spin w-10 h-10 text-indigo-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <p class="text-gray-700 font-medium text-center">Generando diagrama PNG…</p>
            <p class="text-gray-400 text-xs text-center">Esto puede tardar unos segundos</p>
        </div>
    </div>

    @push('js')
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <script>
    // ── Iconos ────────────────────────────────────────────────────────────────
    const iconMap = {
        core:         '{{ route('admin.topology.icon', 'core_switch.png') }}',
        backbone:     '{{ route('admin.topology.icon', 'backbone_switch.png') }}',
        distribution: '{{ route('admin.topology.icon', 'dist_switch.png') }}',
        access:       '{{ route('admin.topology.icon', 'access_switch.png') }}',
    };
    const stackIcon = '{{ route('admin.topology.icon', 'stack_switch.png') }}';

    function nodeIcon(n) { return n.is_stacked ? stackIcon : (iconMap[n.role] ?? iconMap.access); }
    function nodeSize(r)  { return r === 'core' ? 40 : r === 'backbone' ? 35 : r === 'distribution' ? 30 : 25; }

    const allNodes = new vis.DataSet(@json($nodes).map(n => ({
        ...n,
        shape: 'image',
        image: nodeIcon(n),
        size:  nodeSize(n.role),
        font:  { size: 11, color: '#1e293b' },
        color: {
            border: 'transparent',
            background: 'transparent',
            highlight: { border: '#16A34A', background: '#DCFCE7' },  // verde al seleccionar
            hover:     { border: '#16A34A', background: '#DCFCE7' },
        },
    })));

    const allEdges = new vis.DataSet(@json($graphEdges).map(e => e.dashes ? ({
        from: e.from, to: e.to, dashes: true,
        arrows: e.arrows ?? '',
        color: e.color ?? { color: '#CBD5E1' },
        length: e.length, smooth: false,
    }) : ({
        from: e.from, to: e.to,
        label: e.label, arrows: '',
        font: { size: 9, align: 'middle', multi: true },
        color: { color: '#94A3B8', highlight: '#16A34A', hover: '#16A34A' },
        smooth: { type: 'dynamic' },
    })));

    // ── Vis-network ───────────────────────────────────────────────────────────
    const network = new vis.Network(
        document.getElementById('topology-graph'),
        { nodes: allNodes, edges: allEdges },
        {
            nodes: {
                shape: 'image',
                font: { size: 11 },
                shapeProperties: { useImageSize: false },
            },
            edges: {
                smooth: { type: 'dynamic' },
                font: { size: 9, align: 'middle' },
            },
            interaction: { hover: true },
            physics: {
                barnesHut: {
                    gravitationalConstant: -12000,
                    springLength: 200,
                    springConstant: 0.04,
                },
                stabilization: { iterations: 200 },
            },
        }
    );

    // Clic en nodo → resaltar fila en tabla
    network.on('click', params => {
        document.querySelectorAll('#sw-tbody tr').forEach(r => r.classList.remove('!bg-green-50'));
        if (params.nodes.length) {
            const nodeId = params.nodes[0];
            const match  = document.querySelector(`#sw-tbody tr[data-node-id="${nodeId}"]`);
            if (match) {
                match.classList.add('!bg-green-50');
                match.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                setTimeout(() => match.classList.remove('!bg-green-50'), 1800);
            } else {
                const nodeData = allNodes.get(nodeId);
                if (nodeData) focusInTable(nodeId);
            }
        }
    });

    // ── Tabla de switches ─────────────────────────────────────────────────────
    const SW_PER_PAGE = 10;
    const ALL_SW = @json($switchesData);

    let swPage    = 1;
    let swSearch  = '';
    let swVisible = [...ALL_SW];

    const roleLabel = { core:'Core', backbone:'Backbone', distribution:'Distribución', access:'Acceso' };
    const roleBadge = {
        core:'bg-purple-100 text-purple-700',
        backbone:'bg-indigo-100 text-indigo-700',
        distribution:'bg-blue-100 text-blue-700',
        access:'bg-gray-100 text-gray-600',
    };

    function renderSwTable(rows, p) {
        swVisible = rows;
        const total      = rows.length;
        const totalPages = Math.max(1, Math.ceil(total / SW_PER_PAGE));
        swPage           = Math.min(p, totalPages);
        const start      = (swPage - 1) * SW_PER_PAGE;
        const pageData   = rows.slice(start, start + SW_PER_PAGE);

        const tbody = document.getElementById('sw-tbody');
        if (pageData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Sin switches para mostrar</td></tr>`;
        } else {
            tbody.innerHTML = pageData.map(s => `
            <tr data-node-id="${s.node_id}" data-sw-id="${s.id}"
                class="cursor-pointer transition-colors select-none sw-row">
                <td class="px-4 py-2.5 font-medium text-gray-800">${s.sys_name}</td>
                <td class="px-4 py-2.5 text-xs text-gray-500">${s.model}</td>
                <td class="px-4 py-2.5 text-xs font-mono text-gray-500">${s.ip}</td>
                <td class="px-4 py-2.5">
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium ${roleBadge[s.role] ?? 'bg-gray-100 text-gray-600'}">
                        ${roleLabel[s.role] ?? s.role}
                    </span>
                </td>
                <td class="px-4 py-2.5 text-center">
                    ${s.is_stacked
                        ? '<span class="inline-block px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full text-xs">Stack</span>'
                        : '<span class="inline-block px-2 py-0.5 bg-gray-50 text-gray-500 rounded-full text-xs">Standalone</span>'
                    }
                </td>
                <td class="px-4 py-2.5 text-xs text-gray-400">${s.batch_name}</td>
                <td class="px-4 py-2.5 text-center">
                    <a href="${s.show_url}" target="_blank"
                        class="text-xs text-blue-600 hover:underline" onclick="event.stopPropagation()">
                        Ver detalle
                    </a>
                </td>
            </tr>`).join('');
        }

        // Hover → nodo verde; clic → zoom
        tbody.querySelectorAll('tr[data-node-id]').forEach(row => {
            const nodeId = row.dataset.nodeId;

            row.addEventListener('mouseenter', () => {
                row.style.backgroundColor = '#f0fdf4';  // green-50
                network.setSelection({ nodes: [nodeId], edges: [] }, { unselectAll: true, highlightEdges: true });
            });
            row.addEventListener('mouseleave', () => {
                row.style.backgroundColor = '';
                network.setSelection({ nodes: [], edges: [] });
            });
            row.addEventListener('click', () => {
                network.focus(nodeId, {
                    scale: 1.6,
                    animation: { duration: 450, easingFunction: 'easeInOutQuad' },
                });
                network.setSelection({ nodes: [nodeId], edges: [] }, { unselectAll: true, highlightEdges: true });
            });
        });

        // Paginación
        document.getElementById('sw-page-info').textContent =
            `Página ${swPage} de ${totalPages} · ${total} switches`;
        document.getElementById('sw-prev').disabled = swPage <= 1;
        document.getElementById('sw-next').disabled = swPage >= totalPages;

        const pageBtns = document.getElementById('sw-page-btns');
        pageBtns.innerHTML = pageRange(swPage, totalPages).map(p =>
            p === '…'
                ? `<span class="px-2 text-gray-400">…</span>`
                : `<button data-p="${p}"
                    class="px-2.5 py-1 rounded text-xs font-medium transition
                           ${p === swPage ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-700'}"
                  >${p}</button>`
        ).join('');
        pageBtns.querySelectorAll('button[data-p]').forEach(b =>
            b.addEventListener('click', () => renderSwTable(swVisible, parseInt(b.dataset.p)))
        );
    }

    function focusInTable(nodeId) {
        const idx = swVisible.findIndex(s => s.node_id === nodeId);
        if (idx === -1) return;
        renderSwTable(swVisible, Math.ceil((idx + 1) / SW_PER_PAGE));
    }

    function buildSwRows() {
        if (!swSearch) return [...ALL_SW];
        const q = swSearch.toLowerCase();
        return ALL_SW.filter(s =>
            s.sys_name.toLowerCase().includes(q) ||
            s.model.toLowerCase().includes(q) ||
            s.ip.toLowerCase().includes(q)
        );
    }

    function pageRange(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = [1];
        if (current > 3) pages.push('…');
        for (let p = Math.max(2, current - 1); p <= Math.min(total - 1, current + 1); p++) pages.push(p);
        if (current < total - 2) pages.push('…');
        pages.push(total);
        return pages;
    }

    document.getElementById('sw-prev').addEventListener('click', () => renderSwTable(swVisible, swPage - 1));
    document.getElementById('sw-next').addEventListener('click', () => renderSwTable(swVisible, swPage + 1));
    document.getElementById('sw-search').addEventListener('input', e => {
        swSearch = e.target.value.trim();
        renderSwTable(buildSwRows(), 1);
    });

    renderSwTable(buildSwRows(), 1);

    // ── Generación de PNG ─────────────────────────────────────────────────────
    const generateUrl = '{{ route('admin.topology.custom.generate') }}';

    async function generatePng() {
        const overlay = document.getElementById('generate-overlay');
        const btn     = document.getElementById('btn-generate');
        const label   = document.getElementById('btn-generate-label');

        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        btn.disabled = true;
        label.textContent = 'Generando…';

        try {
            const res = await fetch(generateUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            });

            const data = await res.json();
            if (!res.ok || data.error) throw new Error(data.error ?? 'Error desconocido');

            ['btn-view-image', 'btn-download'].forEach(id => {
                const el = document.getElementById(id);
                el.classList.remove('hidden');
                el.classList.add('inline-flex');
            });

            label.textContent = 'Regenerar PNG';

        } catch (err) {
            alert('Error al generar el diagrama: ' + err.message);
            label.textContent = 'Generar diagrama PNG';
        } finally {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
            btn.disabled = false;
        }
    }
    </script>
    @endpush

</x-admin-layout>
