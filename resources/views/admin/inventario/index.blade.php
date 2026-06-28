<x-admin-layout
    title="Inventario de Red | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'href' => route('dashboard')],
        ['name' => 'Inventario'],
    ]">

    @php
        // Agrupar filas por batch (área)
        $grouped = collect($rows)->groupBy('batch_id');
    @endphp

    <div class="space-y-5">

        {{-- ── Título + accesos rápidos ── --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Inventario de Red</h1>
                <p class="text-sm text-gray-500 mt-0.5">Todos los switches registrados en el sistema</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.areas.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition">
                    <i class="ri-node-tree text-sm"></i> Topología por áreas
                </a>
                <button id="btn-gen-images"
                        onclick="generateAllImages()"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                               text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition">
                    <i class="ri-image-add-line text-sm"></i> Generar imágenes de topología
                </button>
                <a href="{{ route('admin.assembler.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-violet-700 bg-violet-50 hover:bg-violet-100 border border-violet-200 rounded-lg transition">
                    <i class="ri-layout-grid-line text-sm"></i> Ensamblador
                </a>
                <a href="{{ route('admin.home') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition">
                    <i class="ri-upload-2-line text-sm"></i> Subir archivos
                </a>
            </div>
        </div>

        {{-- ── Stats cards ── --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @php
                $stats = [
                    ['label' => 'Switches',      'value' => number_format($totalSwitches),    'icon' => 'ri-server-line',        'color' => 'blue'],
                    ['label' => 'Clientes',       'value' => number_format($totalClients),     'icon' => 'ri-building-line',      'color' => 'violet'],
                    ['label' => 'Áreas',          'value' => number_format($totalAreas),       'icon' => 'ri-node-tree',          'color' => 'emerald'],
                    ['label' => 'Conexiones',     'value' => number_format($totalConnections), 'icon' => 'ri-link',               'color' => 'amber'],
                    ['label' => 'Sin resolver',   'value' => number_format($unresolvedConns),  'icon' => 'ri-error-warning-line', 'color' => 'rose'],
                ];
                $colorMap = [
                    'blue'    => ['bg' => 'bg-blue-50',    'icon' => 'text-blue-500',    'val' => 'text-blue-700'],
                    'violet'  => ['bg' => 'bg-violet-50',  'icon' => 'text-violet-500',  'val' => 'text-violet-700'],
                    'emerald' => ['bg' => 'bg-emerald-50', 'icon' => 'text-emerald-500', 'val' => 'text-emerald-700'],
                    'amber'   => ['bg' => 'bg-amber-50',   'icon' => 'text-amber-500',   'val' => 'text-amber-700'],
                    'rose'    => ['bg' => 'bg-rose-50',    'icon' => 'text-rose-500',    'val' => 'text-rose-700'],
                ];
            @endphp
            @foreach($stats as $s)
                @php $c = $colorMap[$s['color']]; @endphp
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg {{ $c['bg'] }} flex items-center justify-center shrink-0">
                        <i class="{{ $s['icon'] }} text-lg {{ $c['icon'] }}"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xl font-bold {{ $c['val'] }}">{{ $s['value'] }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ $s['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ── Barra de filtros ── --}}
        <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-48">
                <i class="ri-search-line absolute left-2.5 top-2.5 text-gray-400 text-sm"></i>
                <input id="inv-search" type="text" placeholder="Buscar hostname, IP, MAC, serie…"
                       class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg
                              focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>

            <select id="inv-client" class="text-sm border border-gray-200 rounded-lg px-3 py-2
                                           focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <option value="">Todos los clientes</option>
                @foreach($clients as $cl)
                    <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                @endforeach
            </select>

            <select id="inv-area" class="text-sm border border-gray-200 rounded-lg px-3 py-2
                                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <option value="">Todas las áreas</option>
                @foreach($batches as $b)
                    <option value="{{ $b->id }}" data-client="{{ $b->client_id }}">
                        {{ $b->name }}{{ $b->client ? ' — '.$b->client->name : '' }}
                    </option>
                @endforeach
            </select>

            <select id="inv-role" class="text-sm border border-gray-200 rounded-lg px-3 py-2
                                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <option value="">Todos los roles</option>
                <option value="core">Core</option>
                <option value="backbone">Backbone</option>
                <option value="dist">Distribución</option>
                <option value="access">Acceso</option>
            </select>

            <span id="inv-count" class="text-xs text-gray-400 ml-auto shrink-0">
                {{ count($rows) }} equipos
            </span>

            <button onclick="toggleAll(true)"
                    class="inline-flex items-center gap-1 px-3 py-2 text-xs font-medium text-gray-600
                           bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition">
                <i class="ri-expand-diagonal-line text-sm"></i> Expandir todo
            </button>
            <button onclick="toggleAll(false)"
                    class="inline-flex items-center gap-1 px-3 py-2 text-xs font-medium text-gray-600
                           bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition">
                <i class="ri-collapse-diagonal-line text-sm"></i> Colapsar todo
            </button>
        </div>

        {{-- ── Tabla agrupada por área ── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="inv-table">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-center w-10">#</th>
                            <th class="px-4 py-3 text-left">Hostname</th>
                            <th class="px-4 py-3 text-left">IP Gestión</th>
                            <th class="px-4 py-3 text-left hidden md:table-cell">Modelo</th>
                            <th class="px-4 py-3 text-left hidden lg:table-cell">Serie</th>
                            <th class="px-4 py-3 text-left hidden lg:table-cell">MAC</th>
                            <th class="px-4 py-3 text-left hidden xl:table-cell">Default Route</th>
                            <th class="px-4 py-3 text-center w-24">Rol</th>
                            <th class="px-4 py-3 text-center w-16"></th>
                        </tr>
                    </thead>
                    <tbody id="inv-tbody">

                        @foreach($grouped as $batchId => $batchRows)
                            @php
                                $firstRow   = $batchRows->first();
                                $batchName  = $firstRow['batch_name']  ?? '—';
                                $clientName = $firstRow['client_name'] ?? '—';
                                $clientId   = $firstRow['client_id']   ?? null;
                                $topoUrl    = ($clientId && $batchId)
                                    ? route('admin.areas.topology', [$clientId, $batchId])
                                    : null;
                            @endphp

                            {{-- ── Cabecera de grupo (área) ── --}}
                            <tr class="inv-group-header border-t-2 border-indigo-100 bg-indigo-50/70
                                       cursor-pointer select-none hover:bg-indigo-100/60 transition-colors"
                                data-group="{{ $batchId }}"
                                data-client="{{ $clientId ?? '' }}"
                                data-expanded="1"
                                onclick="toggleGroup({{ $batchId }})">

                                <td class="px-4 py-2.5 text-center w-10">
                                    <i id="icon-group-{{ $batchId }}"
                                       class="ri-arrow-down-s-line text-indigo-400 text-base"
                                       style="display:inline-block;transition:transform .2s"></i>
                                </td>

                                <td colspan="7" class="px-2 py-2.5">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <i class="ri-node-tree text-indigo-500 shrink-0"></i>
                                        <span class="font-semibold text-indigo-700 text-sm">{{ $batchName }}</span>
                                        <span class="text-gray-300">·</span>
                                        <span class="text-xs text-gray-500">{{ $clientName }}</span>
                                        <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full font-medium ml-1">
                                            {{ $batchRows->count() }} switches
                                        </span>
                                    </div>
                                </td>

                                {{-- Link único al diagrama de este área --}}
                                <td class="px-4 py-2.5 text-center w-16">
                                    @if($topoUrl)
                                        <a href="{{ $topoUrl }}"
                                           onclick="event.stopPropagation()"
                                           title="Ver topología"
                                           class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                                                  text-indigo-500 hover:bg-indigo-200 transition">
                                            <i class="ri-node-tree text-sm"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>

                            {{-- ── Filas de switches del grupo ── --}}
                            @foreach($batchRows as $i => $sw)
                                @php
                                    $roleIcon = [
                                        'core'     => 'core_switch.png',
                                        'backbone' => 'backbone_switch.png',
                                        'dist'     => 'dist_switch.png',
                                        'access'   => 'access_switch.png',
                                    ][$sw['role']] ?? 'access_switch.png';
                                    $roleLabel = [
                                        'core'     => 'Core',
                                        'backbone' => 'Backbone',
                                        'dist'     => 'Dist',
                                        'access'   => 'Access',
                                    ][$sw['role']] ?? ucfirst($sw['role']);
                                @endphp
                                <tr class="inv-row inv-body-{{ $batchId }} hover:bg-blue-50/40 transition-colors"
                                    data-group="{{ $batchId }}"
                                    data-search="{{ strtolower($sw['sys_name'].' '.$sw['management_ip'].' '.$sw['system_mac'].' '.$sw['serial_number'].' '.$sw['batch_name']) }}"
                                    data-client="{{ $sw['client_id'] }}"
                                    data-area="{{ $sw['batch_id'] }}"
                                    data-role="{{ $sw['role'] }}">

                                    <td class="px-4 py-2.5 text-center text-gray-400 text-xs font-mono">
                                        {{ $i + 1 }}
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            @if($sw['is_stacked'])
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" title="Stack"></span>
                                            @else
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 shrink-0"></span>
                                            @endif
                                            <a href="{{ $sw['show_url'] }}"
                                               class="font-medium text-gray-800 hover:text-blue-600 hover:underline truncate max-w-[160px]"
                                               title="{{ $sw['sys_name'] }}">
                                                {{ $sw['sys_name'] }}
                                            </a>
                                        </div>
                                        @if($sw['is_stacked'])
                                            <span class="text-xs text-amber-600 ml-3.5">Stack</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-600">
                                        {{ $sw['management_ip'] }}
                                    </td>

                                    <td class="px-4 py-2.5 text-xs text-gray-600 hidden md:table-cell truncate max-w-[120px]"
                                        title="{{ $sw['system_type'] }}">
                                        {{ $sw['system_type'] }}
                                    </td>

                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-500 hidden lg:table-cell">
                                        {{ $sw['serial_number'] }}
                                    </td>

                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-500 hidden lg:table-cell">
                                        {{ $sw['system_mac'] }}
                                    </td>

                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-500 hidden xl:table-cell">
                                        {{ $sw['default_route'] }}
                                    </td>

                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center justify-center gap-1.5" title="{{ $roleLabel }}">
                                            <img src="{{ route('admin.topology.icon', $roleIcon) }}"
                                                 alt="{{ $roleLabel }}" class="w-5 h-5 object-contain">
                                            <span class="text-xs text-gray-500 hidden xl:inline">{{ $roleLabel }}</span>
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 text-center">
                                        <a href="{{ $sw['show_url'] }}"
                                           class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                                                  text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition"
                                           title="Ver detalle">
                                            <i class="ri-eye-line text-sm"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach

                    </tbody>
                </table>
            </div>

            <div id="inv-empty" class="hidden text-center py-16 text-gray-400">
                <i class="ri-search-line text-3xl mb-2"></i>
                <p class="text-sm">No se encontraron equipos con esos filtros.</p>
            </div>
        </div>

    </div>

    @push('js')
    <script>
    (function () {
        const search    = document.getElementById('inv-search');
        const selClient = document.getElementById('inv-client');
        const selArea   = document.getElementById('inv-area');
        const selRole   = document.getElementById('inv-role');
        const countEl   = document.getElementById('inv-count');
        const empty     = document.getElementById('inv-empty');

        // Filtro cliente → actualizar opciones de área
        selClient.addEventListener('change', () => {
            const cid = selClient.value;
            Array.from(selArea.options).forEach(opt => {
                if (!opt.value) return;
                opt.hidden = cid ? (opt.dataset.client !== cid) : false;
            });
            if (cid && selArea.value && selArea.selectedOptions[0]?.dataset.client !== cid) {
                selArea.value = '';
            }
            applyFilters();
        });

        selArea.addEventListener('change', () => {
            // Al filtrar un área específica, asegurarse que esté expandida
            const aid = selArea.value;
            if (aid) setGroupExpanded(aid, true);
            applyFilters();
        });
        selRole.addEventListener('change', applyFilters);
        search.addEventListener('input', applyFilters);

        function applyFilters() {
            const q    = search.value.trim().toLowerCase();
            const cid  = selClient.value;
            const aid  = selArea.value;
            const role = selRole.value;
            const hasFilter = q || cid || aid || role;

            let totalVisible = 0;
            // Visibles por grupo
            const groupVis = {};

            document.querySelectorAll('#inv-tbody tr.inv-row').forEach(row => {
                const matchQ  = !q    || row.dataset.search.includes(q);
                const matchC  = !cid  || row.dataset.client === cid;
                const matchA  = !aid  || row.dataset.area   === aid;
                const matchR  = !role || row.dataset.role   === role;
                const show    = matchQ && matchC && matchA && matchR;
                row.classList.toggle('hidden', !show);
                if (show) {
                    totalVisible++;
                    const g = row.dataset.group;
                    groupVis[g] = (groupVis[g] || 0) + 1;
                }
            });

            // Cabeceras: mostrar/ocultar y auto-expandir si hay matches en búsqueda
            document.querySelectorAll('#inv-tbody tr.inv-group-header').forEach(hdr => {
                const g      = hdr.dataset.group;
                const hCid   = hdr.dataset.client;
                const matchC = !cid || hCid === cid;
                const matchA = !aid || g    === aid;
                const hasRows = (groupVis[g] || 0) > 0;
                const show   = matchC && matchA && (!hasFilter || hasRows);
                hdr.classList.toggle('hidden', !show);
                // Si hay búsqueda de texto o rol, auto-expandir grupos con resultados
                if ((q || role) && hasRows) {
                    setGroupExpanded(g, true);
                }
            });

            countEl.textContent = totalVisible + ' equipo' + (totalVisible !== 1 ? 's' : '');
            empty.classList.toggle('hidden', totalVisible > 0 || !hasFilter);
        }

        // ── Colapsar / expandir ────────────────────────────────────────────────
        function setGroupExpanded(groupId, expand) {
            const hdr  = document.querySelector('tr.inv-group-header[data-group="' + groupId + '"]');
            const icon = document.getElementById('icon-group-' + groupId);
            const rows = document.querySelectorAll('tr.inv-body-' + groupId);

            rows.forEach(r => {
                // Solo modificar display si la fila no está oculta por filtro
                if (!r.classList.contains('hidden')) {
                    r.style.display = expand ? '' : 'none';
                }
            });

            if (icon) icon.style.transform = expand ? 'rotate(0deg)' : 'rotate(-90deg)';
            if (hdr)  hdr.dataset.expanded = expand ? '1' : '0';
        }

        window.toggleGroup = function(groupId) {
            const hdr = document.querySelector('tr.inv-group-header[data-group="' + groupId + '"]');
            const isExpanded = (hdr?.dataset.expanded ?? '1') !== '0';
            setGroupExpanded(groupId, !isExpanded);
        };

        window.toggleAll = function(expand) {
            document.querySelectorAll('tr.inv-group-header').forEach(hdr => {
                setGroupExpanded(hdr.dataset.group, expand);
            });
        };

    })();

    // ── Generar imágenes de topología ──────────────────────────────────────────
    // Recorre todos los batches visibles y llama a /regenerate en cada uno.
    const BATCH_REGENERATE_URLS = {
        @foreach($batches as $b)
            {{ $b->id }}: "{{ route('admin.batches.diagram.regenerate', $b) }}",
        @endforeach
    };

    async function generateAllImages() {
        const btn = document.getElementById('btn-gen-images');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || '{{ csrf_token() }}';

        // Determinar qué batches están visibles según filtro de cliente/área activo
        const selClient = document.getElementById('inv-client').value;
        const selArea   = document.getElementById('inv-area').value;

        let batchIds = Object.keys(BATCH_REGENERATE_URLS).map(Number);
        if (selArea) {
            batchIds = batchIds.filter(id => id == selArea);
        } else if (selClient) {
            // Filtrar por las opciones de área que pertenecen al cliente
            const areaOpts = Array.from(document.getElementById('inv-area').options);
            const clientBatchIds = areaOpts
                .filter(o => o.value && o.dataset.client == selClient)
                .map(o => Number(o.value));
            batchIds = batchIds.filter(id => clientBatchIds.includes(id));
        }

        if (!batchIds.length) {
            alert('No hay áreas para regenerar. Aplica un filtro o verifica los datos.');
            return;
        }

        const total = batchIds.length;
        btn.disabled = true;
        btn.innerHTML = `<i class="ri-loader-4-line animate-spin text-sm"></i> Generando 0/${total}…`;

        let done = 0, errors = 0;
        for (const batchId of batchIds) {
            const url = BATCH_REGENERATE_URLS[batchId];
            if (!url) continue;
            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                if (!resp.ok) errors++;
            } catch (e) {
                errors++;
            }
            done++;
            btn.innerHTML = `<i class="ri-loader-4-line animate-spin text-sm"></i> Generando ${done}/${total}…`;
        }

        btn.disabled = false;
        btn.innerHTML = `<i class="ri-check-line text-sm"></i> ¡Listo! (${total - errors} generadas${errors ? ', ' + errors + ' con error' : ''})`;
        setTimeout(() => {
            btn.innerHTML = `<i class="ri-image-add-line text-sm"></i> Generar imágenes de topología`;
        }, 4000);

        if (errors === 0) {
            // Ofrecer ir al ensamblador
            if (confirm(`Se encolaron ${total} generaciones. Las imágenes estarán disponibles en el Ensamblador en breves segundos.\n\n¿Abrir el Ensamblador ahora?`)) {
                window.location.href = "{{ route('admin.assembler.index') }}";
            }
        }
    }
    </script>
    @endpush

</x-admin-layout>
