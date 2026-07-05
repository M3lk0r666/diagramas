<x-admin-layout title="Dashboard" :breadcrumbs="[['name' => 'Dashboard']]">

    {{-- ════════════════════════════════════════════════════
     DASHBOARD — Sistema de Gestión de Red
════════════════════════════════════════════════════ --}}
    <div class="space-y-6 pb-8">

        {{-- ── Header ─────────────────────────────────────────────────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Panel de control</h1>
                <p class="text-sm text-gray-500 mt-0.5">Resumen general del sistema de gestión de red ·
                    {{ now()->isoFormat('D [de] MMMM, YYYY') }}</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('admin.client.upload') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="ri-upload-cloud-line"></i> Subir archivos
                </a>
                <a href="{{ route('admin.assembler.create') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="ri-layout-grid-line"></i> Nuevo diagramass
                </a>
            </div>
        </div>

        {{-- ── KPI cards ───────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @php
                $kpis = [
                    [
                        'label' => 'Clientes',
                        'value' => $totalClients,
                        'icon' => 'ri-building-2-line',
                        'color' => 'blue',
                        'href' => route('admin.clients.manage.index'),
                    ],
                    [
                        'label' => 'Áreas',
                        'value' => $totalBatches,
                        'icon' => 'ri-folder-zip-line',
                        'color' => 'violet',
                        'href' => route('admin.areas.index'),
                    ],
                    [
                        'label' => 'Switches',
                        'value' => $totalSwitches,
                        'icon' => 'ri-cpu-line',
                        'color' => 'cyan',
                        'href' => route('admin.switches.index'),
                    ],
                    [
                        'label' => 'Conexiones',
                        'value' => number_format($totalConnections),
                        'icon' => 'ri-git-branch-line',
                        'color' => 'emerald',
                        'href' => route('admin.topology.index'),
                    ],
                    [
                        'label' => 'Diagramas',
                        'value' => $totalProjects,
                        'icon' => 'ri-layout-grid-line',
                        'color' => 'orange',
                        'href' => route('admin.assembler.index'),
                    ],
                    [
                        'label' => 'Archivos cfg',
                        'value' => $totalFiles,
                        'icon' => 'ri-file-code-line',
                        'color' => 'slate',
                        'href' => route('admin.inventario.index'),
                    ],
                ];
                $colorMap = [
                    'blue' => [
                        'bg' => 'bg-blue-50',
                        'ibg' => 'text-blue-600',
                        'val' => 'text-blue-700',
                        'border' => 'border-blue-100',
                        'ring' => 'hover:ring-blue-200',
                    ],
                    'violet' => [
                        'bg' => 'bg-violet-50',
                        'ibg' => 'text-violet-600',
                        'val' => 'text-violet-700',
                        'border' => 'border-violet-100',
                        'ring' => 'hover:ring-violet-200',
                    ],
                    'cyan' => [
                        'bg' => 'bg-cyan-50',
                        'ibg' => 'text-cyan-600',
                        'val' => 'text-cyan-700',
                        'border' => 'border-cyan-100',
                        'ring' => 'hover:ring-cyan-200',
                    ],
                    'emerald' => [
                        'bg' => 'bg-emerald-50',
                        'ibg' => 'text-emerald-600',
                        'val' => 'text-emerald-700',
                        'border' => 'border-emerald-100',
                        'ring' => 'hover:ring-emerald-200',
                    ],
                    'orange' => [
                        'bg' => 'bg-orange-50',
                        'ibg' => 'text-orange-600',
                        'val' => 'text-orange-700',
                        'border' => 'border-orange-100',
                        'ring' => 'hover:ring-orange-200',
                    ],
                    'slate' => [
                        'bg' => 'bg-slate-50',
                        'ibg' => 'text-slate-500',
                        'val' => 'text-slate-700',
                        'border' => 'border-slate-100',
                        'ring' => 'hover:ring-slate-200',
                    ],
                ];
            @endphp

            @foreach ($kpis as $k)
                @php $c = $colorMap[$k['color']]; @endphp
                <a href="{{ $k['href'] }}"
                    class="relative group bg-white border {{ $c['border'] }} rounded-xl p-4 hover:shadow-md hover:ring-2 {{ $c['ring'] }} transition-all duration-200">
                    <div class="flex items-start justify-between mb-3">
                        <div class="p-2 {{ $c['bg'] }} rounded-lg">
                            <i class="{{ $k['icon'] }} text-xl {{ $c['ibg'] }}"></i>
                        </div>
                        <i
                            class="ri-arrow-right-up-line text-gray-300 group-hover:text-gray-500 text-sm transition-colors mt-1"></i>
                    </div>
                    <div class="text-2xl font-bold {{ $c['val'] }}">{{ $k['value'] }}</div>
                    <div class="text-xs text-gray-500 mt-0.5 font-medium">{{ $k['label'] }}</div>
                </a>
            @endforeach
        </div>

        {{-- ── Fila 2: Estado áreas / Breakdown / Actividad reciente ──────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Estado de áreas --}}
            <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 mb-1 flex items-center gap-2">
                    <i class="ri-folder-zip-line text-violet-500"></i> Áreas por estado
                </h3>
                <p class="text-xs text-gray-400 mb-4">Resultado del procesamiento de archivos de configuración</p>
                @php
                    $statusItems = [
                        [
                            'label' => 'Completadas',
                            'val' => $batchCompleted,
                            'bar' => 'bg-emerald-500',
                            'badge' => 'bg-emerald-100 text-emerald-700',
                        ],
                        [
                            'label' => 'Fallidas',
                            'val' => $batchFailed,
                            'bar' => 'bg-red-500',
                            'badge' => 'bg-red-100 text-red-700',
                        ],
                        [
                            'label' => 'Procesando',
                            'val' => $batchProcessing,
                            'bar' => 'bg-blue-500',
                            'badge' => 'bg-blue-100 text-blue-700',
                        ],
                        [
                            'label' => 'Pendientes',
                            'val' => $batchPending,
                            'bar' => 'bg-amber-400',
                            'badge' => 'bg-amber-100 text-amber-700',
                        ],
                    ];
                @endphp
                <div class="space-y-3">
                    @foreach ($statusItems as $s)
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-medium text-gray-600">{{ $s['label'] }}</span>
                                <span
                                    class="text-xs font-bold px-2 py-0.5 rounded-full {{ $s['badge'] }}">{{ $s['val'] }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5">
                                @if ($totalBatches > 0)
                                    <div class="{{ $s['bar'] }} h-1.5 rounded-full"
                                        style="width:{{ round(($s['val'] / $totalBatches) * 100) }}%"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 flex justify-between text-xs">
                    <span class="text-gray-400">Total áreas</span>
                    <span class="font-bold text-gray-600">{{ $totalBatches }}</span>
                </div>
            </div>

            {{-- Diagramas & Switches --}}
            <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 mb-1 flex items-center gap-2">
                    <i class="ri-pie-chart-line text-orange-500"></i> Distribución
                </h3>
                <p class="text-xs text-gray-400 mb-4">Tipos de diagramas y configuración de switches</p>

                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Diagramas</p>
                <div class="flex gap-3 mb-5">
                    <div class="flex-1 rounded-xl border border-blue-100 bg-blue-50 p-3 text-center">
                        <div class="text-2xl font-bold text-blue-700">{{ $projectsPng }}</div>
                        <div class="flex items-center justify-center gap-1 text-xs text-blue-500 mt-0.5">
                            <i class="ri-image-line"></i> PNG
                        </div>
                    </div>
                    <div class="flex-1 rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-center">
                        <div class="text-2xl font-bold text-indigo-700">{{ $projectsVect }}</div>
                        <div class="flex items-center justify-center gap-1 text-xs text-indigo-500 mt-0.5">
                            <i class="ri-node-tree"></i> Vectorial
                        </div>
                    </div>
                </div>

                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Switches</p>
                <div class="flex gap-3">
                    <div class="flex-1 rounded-xl border border-cyan-100 bg-cyan-50 p-3 text-center">
                        <div class="text-2xl font-bold text-cyan-700">{{ $standaloneSwitches }}</div>
                        <div class="text-xs text-cyan-500 mt-0.5">Standalone</div>
                    </div>
                    <div class="flex-1 rounded-xl border border-violet-100 bg-violet-50 p-3 text-center">
                        <div class="text-2xl font-bold text-violet-700">{{ $stackedSwitches }}</div>
                        <div class="text-xs text-violet-500 mt-0.5">Stacking</div>
                    </div>
                </div>

                @if ($totalSwitches > 0)
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span>Switches en stack</span>
                            <span>{{ round(($stackedSwitches / $totalSwitches) * 100) }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="bg-violet-500 h-1.5 rounded-full"
                                style="width:{{ round(($stackedSwitches / $totalSwitches) * 100) }}%"></div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Últimas áreas --}}
            <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="ri-history-line text-slate-400"></i> Actividad reciente
                    </h3>
                    <a href="{{ route('admin.areas.index') }}" class="text-xs text-blue-600 hover:underline">Ver todo
                        →</a>
                </div>
                <p class="text-xs text-gray-400 mb-4">Últimas áreas procesadas en el sistema</p>
                @php
                    $stBadge = [
                        'completed' => 'bg-emerald-100 text-emerald-700',
                        'failed' => 'bg-red-100 text-red-700',
                        'processing' => 'bg-blue-100 text-blue-700',
                        'pending' => 'bg-amber-100 text-amber-700',
                    ];
                    $stLabel = [
                        'completed' => 'OK',
                        'failed' => 'Error',
                        'processing' => 'Proc.',
                        'pending' => 'Pend.',
                    ];
                @endphp
                <div class="space-y-2.5">
                    @forelse($recentBatches as $batch)
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
                                <i class="ri-folder-zip-line text-violet-600 text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-800 truncate">{{ $batch->name }}</p>
                                <p class="text-[10px] text-gray-400 truncate">{{ $batch->client->name ?? '—' }} ·
                                    {{ $batch->updated_at->diffForHumans() }}</p>
                            </div>
                            <span
                                class="flex-shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $stBadge[$batch->status] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ $stLabel[$batch->status] ?? $batch->status }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-6">Sin áreas registradas.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Fila 3: Tabla clientes + Últimos diagramas ─────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

            {{-- Tabla resumen por cliente (3/5) --}}
            <div class="lg:col-span-3 bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <i class="ri-building-2-line text-blue-500"></i> Resumen por cliente
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">Áreas, estado y cantidad de switches por cliente</p>
                    </div>
                    <a href="{{ route('admin.clients.manage.index') }}"
                        class="text-xs text-blue-600 hover:underline font-medium flex-shrink-0">Ver todos →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 text-[10px] text-gray-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Cliente</th>
                                <th class="px-4 py-3 text-center font-semibold">Áreas</th>
                                <th class="px-4 py-3 text-center font-semibold">Completadas</th>
                                <th class="px-4 py-3 text-center font-semibold">Fallidas</th>
                                <th class="px-4 py-3 text-center font-semibold">Switches</th>
                                <th class="px-4 py-3 text-center font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 text-xs">
                            @forelse($clientSummary as $c)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                                <span
                                                    class="text-blue-600 font-bold text-xs">{{ strtoupper(substr($c->name, 0, 2)) }}</span>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800 text-xs">{{ $c->name }}</p>
                                                <p class="text-[10px] text-gray-400">ID #{{ $c->id }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-gray-700">{{ $c->batches_count }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="font-semibold text-emerald-600">{{ $c->completed_batches }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="font-semibold {{ $c->failed_batches > 0 ? 'text-red-500' : 'text-gray-300' }}">
                                            {{ $c->failed_batches }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="font-bold text-cyan-700">{{ number_format($c->switch_total) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('admin.clients.manage.show', $c) }}"
                                            class="inline-flex items-center gap-1 text-blue-500 hover:text-blue-700 text-xs font-medium">
                                            <i class="ri-eye-line"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-gray-400 text-xs">
                                        Sin clientes con áreas registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Últimos diagramas (2/5) --}}
            <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="ri-layout-grid-line text-orange-500"></i> Últimos diagramas
                    </h3>
                    <a href="{{ route('admin.assembler.index') }}"
                        class="text-xs text-blue-600 hover:underline font-medium">Ver todos →</a>
                </div>
                <p class="text-xs text-gray-400 mb-4">Proyectos del ensamblador ordenados por actividad</p>

                <div class="space-y-2">
                    @forelse($recentProjects as $proj)
                        @php
                            $isVect = ($proj->type ?? 'png') === 'vectorial';
                            $er = $isVect
                                ? route('admin.assembler.vectorial', $proj)
                                : route('admin.assembler.edit', $proj);
                        @endphp
                        <a href="{{ $er }}"
                            class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition-colors group border border-transparent hover:border-gray-100">
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0
                        {{ $isVect ? 'bg-indigo-100' : 'bg-blue-100' }}">
                                <i
                                    class="{{ $isVect ? 'ri-node-tree text-indigo-600' : 'ri-image-line text-blue-600' }} text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-xs font-semibold text-gray-800 truncate group-hover:text-blue-600 transition-colors">
                                    {{ $proj->name }}
                                </p>
                                <p class="text-[10px] text-gray-400 truncate">
                                    {{ $proj->client->name ?? '—' }} · {{ $proj->updated_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex-shrink-0 flex flex-col items-end gap-1">
                                <span
                                    class="text-[9px] font-bold px-1.5 py-0.5 rounded-full
                            {{ $isVect ? 'bg-indigo-100 text-indigo-600' : 'bg-blue-100 text-blue-600' }}">
                                    {{ $isVect ? 'VEC' : 'PNG' }}
                                </span>
                                <i
                                    class="ri-arrow-right-s-line text-gray-300 group-hover:text-blue-400 text-sm transition-colors"></i>
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-8">
                            <i class="ri-layout-grid-line text-4xl text-gray-200 block mb-2"></i>
                            <p class="text-xs text-gray-400">Sin diagramas aún.</p>
                            <a href="{{ route('admin.assembler.create') }}"
                                class="mt-2 inline-block text-xs text-blue-600 hover:underline font-medium">
                                Crear primer diagrama →
                            </a>
                        </div>
                    @endforelse
                </div>

                @if ($totalProjects > 0)
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <a href="{{ route('admin.assembler.create') }}"
                            class="w-full flex items-center justify-center gap-1.5 py-2 text-xs font-medium text-indigo-600
                          border border-dashed border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
                            <i class="ri-add-line"></i> Nuevo diagrama
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Fila 4: Accesos rápidos ─────────────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Accesos rápidos</h2>
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                @php
                    $modules = [
                        [
                            'label' => 'Subir archivos',
                            'icon' => 'ri-upload-cloud-2-line',
                            'color' => 'blue',
                            'href' => route('admin.client.upload'),
                            'desc' => 'Cargar configs',
                        ],
                        [
                            'label' => 'Inventario',
                            'icon' => 'ri-list-check-3',
                            'color' => 'slate',
                            'href' => route('admin.inventario.index'),
                            'desc' => 'Lista de switches',
                        ],
                        [
                            'label' => 'Clientes',
                            'icon' => 'ri-building-2-line',
                            'color' => 'blue',
                            'href' => route('admin.clients.manage.index'),
                            'desc' => 'Gestión de clientes',
                        ],
                        [
                            'label' => 'Switches',
                            'icon' => 'ri-cpu-line',
                            'color' => 'cyan',
                            'href' => route('admin.switches.index'),
                            'desc' => 'Detalle switches',
                        ],
                        [
                            'label' => 'Topología',
                            'icon' => 'ri-share-line',
                            'color' => 'green',
                            'href' => route('admin.topology.index'),
                            'desc' => 'Red completa',
                        ],
                        [
                            'label' => 'Áreas',
                            'icon' => 'ri-node-tree',
                            'color' => 'violet',
                            'href' => route('admin.areas.index'),
                            'desc' => 'Por área/cliente',
                        ],
                        [
                            'label' => 'Ensamblador',
                            'icon' => 'ri-image-2-line',
                            'color' => 'orange',
                            'href' => route('admin.assembler.index'),
                            'desc' => 'Mis diagramas',
                        ],
                        [
                            'label' => 'Nuevo Diagrama',
                            'icon' => 'ri-add-circle-line',
                            'color' => 'indigo',
                            'href' => route('admin.assembler.create'),
                            'desc' => 'PNG o Vectorial',
                        ],
                        [
                            'label' => 'GoJS',
                            'icon' => 'ri-git-branch-line',
                            'color' => 'teal',
                            'href' => route('admin.topology.gojs.show'),
                            'desc' => 'Topología interactiva',
                        ],
                        [
                            'label' => 'Topo. Custom',
                            'icon' => 'ri-settings-3-line',
                            'color' => 'rose',
                            'href' => route('admin.topology.custom.show'),
                            'desc' => 'Filtros avanzados',
                        ],
                        [
                            'label' => 'Clientes',
                            'icon' => 'ri-table-line',
                            'color' => 'slate',
                            'href' => route('admin.clients.index'),
                            'desc' => 'Tabla de clientes',
                        ],
                        [
                            'label' => 'Archi. Topo.',
                            'icon' => 'ri-folder-image-line',
                            'color' => 'orange',
                            'href' => route('admin.areas.index'),
                            'desc' => 'Topologías por área',
                        ],
                    ];
                    $qc = [
                        'blue' => 'bg-blue-50 text-blue-600 border-blue-100 hover:border-blue-300 hover:bg-blue-100',
                        'slate' =>
                            'bg-slate-50 text-slate-600 border-slate-100 hover:border-slate-300 hover:bg-slate-100',
                        'cyan' => 'bg-cyan-50 text-cyan-600 border-cyan-100 hover:border-cyan-300 hover:bg-cyan-100',
                        'green' =>
                            'bg-green-50 text-green-600 border-green-100 hover:border-green-300 hover:bg-green-100',
                        'violet' =>
                            'bg-violet-50 text-violet-600 border-violet-100 hover:border-violet-300 hover:bg-violet-100',
                        'orange' =>
                            'bg-orange-50 text-orange-600 border-orange-100 hover:border-orange-300 hover:bg-orange-100',
                        'indigo' =>
                            'bg-indigo-50 text-indigo-600 border-indigo-100 hover:border-indigo-300 hover:bg-indigo-100',
                        'teal' => 'bg-teal-50 text-teal-600 border-teal-100 hover:border-teal-300 hover:bg-teal-100',
                        'rose' => 'bg-rose-50 text-rose-600 border-rose-100 hover:border-rose-300 hover:bg-rose-100',
                    ];
                @endphp

                @foreach ($modules as $m)
                    <a href="{{ $m['href'] }}"
                        class="group bg-white border {{ $qc[$m['color']] }} rounded-xl p-3
                      flex flex-col items-center gap-2 text-center transition-all hover:shadow-sm hover:-translate-y-0.5">
                        <div class="w-9 h-9 flex items-center justify-center rounded-xl {{ $qc[$m['color']] }}">
                            <i class="{{ $m['icon'] }} text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-700 leading-tight">{{ $m['label'] }}</p>
                            <p class="text-[9px] text-gray-400 mt-0.5">{{ $m['desc'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

    </div>

</x-admin-layout>
