<x-admin-layout title="Inventario · {{ $client->name }} | Diagramas" :breadcrumbs="[]">

    <div class="space-y-2">

        {{-- ── Título + nav ── --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('admin.hub.index') }}"
                    class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium
                          text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition">
                    <i class="ri-arrow-left-s-line text-base"></i> Regresar a Clientes
                </a>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 truncate">{{ $client->name }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Inventario de switches del cliente</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.topology.index', $client) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-orange-700 bg-orange-50 hover:bg-orange-100 border border-orange-200 rounded-lg transition">
                    <i class="ri-node-tree text-sm"></i> Topología Global v1
                </a>
                <a href="{{ route('admin.areas.global', $client) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-violet-700 bg-violet-50 hover:bg-violet-100 border border-violet-200 rounded-lg transition">
                    <i class="ri-node-tree text-sm"></i> Topología Global v2
                </a>
                <a href="{{ route('admin.areas.client', $client) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition">
                    <i class="ri-map-2-line text-sm"></i> Áreas
                </a>
                <a href="{{ route('admin.clients.manage.show', $client) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition">
                    <i class="ri-settings-3-line text-sm"></i> Gestionar
                </a>
                {{-- Descarga directa CSV --}}
                <a href="{{ route('admin.hub.inventario.export', $client) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold
                          text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg transition">
                    <i class="ri-file-excel-2-line text-sm"></i> Descargar CSV
                </a>
            </div>
        </div>

        {{-- ── Stats ── --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shrink-0">
                    <i class="ri-server-line text-base"></i>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-800">{{ $totalSwitches }}</div>
                    <div class="text-xs text-gray-500">Switches</div>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                <div
                    class="w-9 h-9 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center shrink-0">
                    <i class="ri-map-2-line text-base"></i>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-800">{{ $totalAreas }}</div>
                    <div class="text-xs text-gray-500">Áreas</div>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center shrink-0">
                    <i class="ri-git-branch-line text-base"></i>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-800">{{ $totalConnections }}</div>
                    <div class="text-xs text-gray-500">Conexiones</div>
                </div>
            </div>
        </div>

        {{-- ── Buscador (solo en vista áreas) ── --}}
        <div class="inv-controls flex items-center gap-3 flex-wrap">
            <div class="relative flex-1 min-w-48">
                <i
                    class="ri-search-line absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                <input id="inv-search" type="text" placeholder="Buscar hostname, IP, MAC…"
                    class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300">
            </div>
            <button onclick="toggleAll(true)"
                class="text-xs px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 transition">↕ Expandir
                todo</button>
            <button onclick="toggleAll(false)"
                class="text-xs px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 transition">↕ Colapsar
                todo</button>
        </div>

        {{-- ── Tabs: Vista Áreas / DataTable ── --}}
        <div class="flex gap-1 border-b border-gray-200 mb-1">
            <button id="tab-areas" onclick="switchTab('areas')"
                class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-indigo-600 text-indigo-700 -mb-px transition">
                <i class="ri-layout-grid-line mr-1"></i> Vista por Áreas
            </button>
            <button id="tab-datatable" onclick="switchTab('datatable')"
                class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 -mb-px transition">
                <i class="ri-table-line mr-1"></i> DataTable / Filtros
            </button>
        </div>

        {{-- ── Panel DataTable (Livewire) ── --}}
        <div id="panel-datatable" class="hidden">
            <livewire:inventario-table :client-id="$client->id" />
        </div>

        {{-- ── Tabla agrupada por área ── --}}
        <div class="space-y-3" id="inv-body">
            @forelse($grouped as $batchId => $batchRows)
                @php
                    $batchName = $batchRows->first()['batch_name'];
                    $switchCount = $batchRows->count();
                    $batch = $batches->firstWhere('id', $batchId);
                @endphp

                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden group-batch"
                    data-batch="{{ $batchId }}">

                    {{-- Cabecera de grupo --}}
                    <div class="flex items-center gap-3 px-4 py-3 bg-indigo-100 border-b border-indigo-100
                            cursor-pointer select-none"
                        onclick="toggleGroup({{ $batchId }})">
                        <i id="icon-{{ $batchId }}"
                            class="ri-arrow-down-s-line text-indigo-400 text-base transition-transform"></i>
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                            <span class="font-semibold text-indigo-800 text-sm truncate">{{ $batchName }}</span>
                            <span
                                class="text-xs bg-white text-indigo-600 border border-indigo-200 px-2 py-0.5 rounded-full font-medium shrink-0">
                                {{ $switchCount }} switch{{ $switchCount !== 1 ? 'es' : '' }}
                            </span>
                        </div>
                        @if ($batch)
                            <a href="{{ route('admin.areas.topology', [$client, $batch]) }}"
                                class="shrink-0 inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800
                              bg-white border border-indigo-200 px-2 py-1 rounded-md transition hover:bg-yellow-100"
                                onclick="event.stopPropagation()" title="Ver topología de esta área">
                                <i class="ri-node-tree text-base"></i> ver Topología
                            </a>
                        @endif
                    </div>

                    {{-- Filas de switches --}}
                    <div id="rows-{{ $batchId }}" class="overflow-x-auto">
                        <table class="w-full text-base">
                            <thead>
                                <tr
                                    class="bg-blue-50 border-b border-gray-100 text-gray-500 uppercase tracking-wide text-[12px]">
                                    <th class="px-4 py-2 text-left font-semibold w-6">#</th>
                                    <th class="px-4 py-2 text-left font-semibold">Hostname</th>
                                    <th class="px-4 py-2 text-left font-semibold">IP Gestión</th>
                                    <th class="px-4 py-2 text-left font-semibold">Modelo</th>
                                    <th class="px-4 py-2 text-left font-semibold">Serie</th>
                                    <th class="px-4 py-2 text-left font-semibold">MAC</th>
                                    <th class="px-4 py-2 text-left font-semibold">Default Route</th>
                                    <th class="px-4 py-2 text-center font-semibold">Arreglo</th>
                                    <th class="px-4 py-2 text-center font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($batchRows->values() as $i => $row)
                                    @php
                                        $searchStr = strtolower($row['sys_name'] . ' ' . $row['management_ip'] . ' ' . $row['system_mac']);
                                        if ($row['is_stacked'] && !empty($row['stack_members'])) {
                                            foreach ($row['stack_members'] as $m) {
                                                $searchStr .= ' ' . strtolower(($m['serial_number'] ?? '') . ' ' . ($m['mac'] ?? ''));
                                            }
                                        } else {
                                            $searchStr .= ' ' . strtolower($row['serial_number']);
                                        }
                                    @endphp
                                    <tr class="inv-row hover:bg-orange-100/30 transition"
                                        data-search="{{ $searchStr }}">
                                        <td class="px-4 py-2.5 text-gray-400 tabular-nums">{{ $i + 1 }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-1.5">
                                                @if ($row['is_stacked'])
                                                    <i class="ri-stack-line text-amber-600 shrink-0" title="Stack"></i>
                                                @else
                                                    <i class="ri-server-line text-gray-600 shrink-0"></i>
                                                @endif
                                                <a href="{{ $row['show_url'] }}"
                                                    class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                                    {{ $row['sys_name'] }}
                                                </a>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-gray-600 text-[16px]">
                                            {{ $row['management_ip'] }}
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-600 max-w-[120px] truncate"
                                            title="{{ $row['system_type'] }}">
                                            {{ $row['system_type'] }}
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-gray-500 text-[13px] leading-5">
                                            @if($row['is_stacked'] && !empty($row['stack_members']))
                                                @foreach($row['stack_members'] as $m)
                                                    <div><span class="text-gray-400 text-[11px]">S{{ $m['slot'] ?? '?' }}:</span> {{ $m['serial_number'] ?? '—' }}</div>
                                                @endforeach
                                            @else
                                                {{ $row['serial_number'] }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-gray-500 text-[13px] leading-5">
                                            @if($row['is_stacked'] && !empty($row['stack_members']))
                                                @foreach($row['stack_members'] as $m)
                                                    <div><span class="text-gray-400 text-[11px]">S{{ $m['slot'] ?? '?' }}:</span> {{ $m['mac'] ?? '—' }}</div>
                                                @endforeach
                                            @else
                                                {{ $row['system_mac'] }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-gray-600">{{ $row['default_route'] }}
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            @php
                                                $roleIconMap = [
                                                    'core'     => 'core_switch.png',
                                                    'backbone' => 'backbone_switch.png',
                                                    'dist'     => 'dist_switch.png',
                                                    'access'   => 'access_switch.png',
                                                    'stack'    => 'stack_switch.png',
                                                ];
                                                $roleLabels = [
                                                    'core'     => 'Core',
                                                    'backbone' => 'Backbone',
                                                    'dist'     => 'Dist',
                                                    'access'   => 'Access',
                                                    'stack'    => 'Stack',
                                                ];
                                                $rIcon = $roleIconMap[$row['role']] ?? 'access_switch.png';
                                                $rl = $roleLabels[$row['role']] ?? ucfirst($row['role']);
                                            @endphp
                                            <span class="inline-flex flex-col items-center justify-center gap-1"
                                                title="{{ $rl }}">
                                                <img src="{{ route('admin.topology.icon', $rIcon) }}"
                                                    alt="{{ $rl }}" class="w-9 h-9 object-contain">
                                                <span class="text-xs text-gray-500 text-center">
                                                    {{ $rl }}
                                                </span>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <a href="{{ $row['show_url'] }}"
                                                class="text-gray-400 hover:text-indigo-600 transition"
                                                title="Ver detalle">
                                                <i class="ri-eye-line text-base"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center py-16 text-gray-400">
                    <i class="ri-server-line text-4xl mb-2 block"></i>
                    <p>No hay switches registrados para este cliente.</p>
                </div>
            @endforelse
        </div>

    </div>

    @push('js')
        <script>
            const _openGroups = new Set({{ json_encode($grouped->keys()->toArray()) }});

            function toggleGroup(batchId) {
                const rows = document.getElementById('rows-' + batchId);
                const icon = document.getElementById('icon-' + batchId);
                if (!rows) return;
                const isOpen = !rows.classList.contains('hidden');
                rows.classList.toggle('hidden', isOpen);
                if (icon) icon.style.transform = isOpen ? 'rotate(-90deg)' : '';
            }

            function toggleAll(expand) {
                document.querySelectorAll('.group-batch').forEach(card => {
                    const batchId = card.dataset.batch;
                    const rows = document.getElementById('rows-' + batchId);
                    const icon = document.getElementById('icon-' + batchId);
                    if (!rows) return;
                    rows.classList.toggle('hidden', !expand);
                    if (icon) icon.style.transform = expand ? '' : 'rotate(-90deg)';
                });
            }

            document.getElementById('inv-search')?.addEventListener('input', function() {
                const q = this.value.toLowerCase().trim();
                document.querySelectorAll('.group-batch').forEach(card => {
                    const batchId = card.dataset.batch;
                    const allRows = card.querySelectorAll('.inv-row');
                    let groupVisible = 0;
                    allRows.forEach(row => {
                        const match = !q || row.dataset.search.includes(q);
                        row.classList.toggle('hidden', !match);
                        if (match) groupVisible++;
                    });
                    card.style.display = groupVisible === 0 && q ? 'none' : '';
                    // Auto-expandir si hay resultados
                    if (q && groupVisible > 0) {
                        const rows = document.getElementById('rows-' + batchId);
                        const icon = document.getElementById('icon-' + batchId);
                        if (rows) rows.classList.remove('hidden');
                        if (icon) icon.style.transform = '';
                    }
                });
            });

            // ── Tabs áreas / datatable ────────────────────────────────
            function switchTab(tab) {
                const isAreas = tab === 'areas';

                document.getElementById('panel-datatable').classList.toggle('hidden', isAreas);
                document.getElementById('inv-body').classList.toggle('hidden', !isAreas);

                // Barra de búsqueda y botones expand/colapsar solo en vista áreas
                document.querySelectorAll('.inv-controls').forEach(el =>
                    el.classList.toggle('hidden', !isAreas)
                );

                // Estilo de tabs
                ['areas', 'datatable'].forEach(t => {
                    const btn = document.getElementById('tab-' + t);
                    const active = t === tab;
                    btn.classList.toggle('border-indigo-600', active);
                    btn.classList.toggle('text-indigo-700', active);
                    btn.classList.toggle('border-transparent', !active);
                    btn.classList.toggle('text-gray-500', !active);
                });

                // Guardar preferencia
                sessionStorage.setItem('inv_tab', tab);
            }

            // Restaurar tab al recargar
            const savedTab = sessionStorage.getItem('inv_tab');
            if (savedTab === 'datatable') switchTab('datatable');
        </script>
    @endpush

</x-admin-layout>
