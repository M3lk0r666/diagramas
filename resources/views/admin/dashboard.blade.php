<x-admin-layout title="Dashboard | Diagramas" :breadcrumbs="[['name' => 'Dashboard']]">
<div class="p-4 sm:p-6 space-y-6">

    {{-- ENCABEZADO --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Panel de Control</h1>
            <p class="text-sm text-gray-500 mt-0.5">Resumen general del sistema de gestión de red</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('admin.home') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                <i class="ri-upload-cloud-line"></i> Subir archivos
            </a>
            <a href="{{ route('admin.assembler.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition">
                <i class="ri-add-line"></i> Nuevo diagrama
            </a>
        </div>
    </div>

    {{-- KPI CARDS --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-col gap-2 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Clientes</span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><i class="ri-building-2-line text-base"></i></span>
            </div>
            <span class="text-3xl font-bold text-gray-800">{{ $totalClients }}</span>
            <a href="{{ route('admin.clients.index') }}" class="text-xs text-indigo-600 hover:underline">Ver clientes &rarr;</a>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-col gap-2 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Areas</span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-sky-50 text-sky-600"><i class="ri-node-tree text-base"></i></span>
            </div>
            <span class="text-3xl font-bold text-gray-800">{{ $totalBatches }}</span>
            <a href="{{ route('admin.areas.index') }}" class="text-xs text-sky-600 hover:underline">Ver areas &rarr;</a>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-col gap-2 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Switches</span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-violet-50 text-violet-600"><i class="ri-cpu-line text-base"></i></span>
            </div>
            <span class="text-3xl font-bold text-gray-800">{{ $totalSwitches }}</span>
            <a href="{{ route('admin.switches.index') }}" class="text-xs text-violet-600 hover:underline">Ver switches &rarr;</a>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-col gap-2 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Conexiones</span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-50 text-amber-600"><i class="ri-share-line text-base"></i></span>
            </div>
            <span class="text-3xl font-bold text-gray-800">{{ $totalConnections }}</span>
            <a href="{{ route('admin.topology.index') }}" class="text-xs text-amber-600 hover:underline">Ver topologia &rarr;</a>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-col gap-2 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Diagramas</span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i class="ri-layout-grid-line text-base"></i></span>
            </div>
            <span class="text-3xl font-bold text-gray-800">{{ $totalProjects }}</span>
            <a href="{{ route('admin.assembler.index') }}" class="text-xs text-emerald-600 hover:underline">Ver diagramas &rarr;</a>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-col gap-2 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Archivos cfg</span>
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600"><i class="ri-file-text-line text-base"></i></span>
            </div>
            <span class="text-3xl font-bold text-gray-800">{{ $totalFiles }}</span>
            <span class="text-xs text-gray-400">En storage</span>
        </div>
    </div>

    {{-- FILA MEDIA --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Estado areas --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="ri-bar-chart-2-line text-sky-500"></i> Estado de Areas Procesadas
            </h2>
            @php
                $totalB = max(1, $totalBatches);
                $statuses = [
                    ['label'=>'Completadas','val'=>$batchCompleted, 'color'=>'bg-emerald-500','text'=>'text-emerald-700'],
                    ['label'=>'Pendientes', 'val'=>$batchPending,   'color'=>'bg-amber-400',  'text'=>'text-amber-700'],
                    ['label'=>'Procesando', 'val'=>$batchProcessing,'color'=>'bg-sky-400',    'text'=>'text-sky-700'],
                    ['label'=>'Con errores','val'=>$batchFailed,    'color'=>'bg-rose-500',   'text'=>'text-rose-700'],
                ];
            @endphp
            <div class="space-y-3">
                @foreach($statuses as $s)
                <div>
                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                        <span>{{ $s['label'] }}</span>
                        <span class="font-semibold {{ $s['text'] }}">{{ $s['val'] }}</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-gray-100">
                        <div class="h-2 rounded-full {{ $s['color'] }}" style="width:{{ round($s['val']/$totalB*100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Distribucion --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="ri-pie-chart-line text-violet-500"></i> Distribucion
            </h2>
            @php $totalP = max(1, $totalProjects); $totalSw = max(1, $totalSwitches); @endphp
            <div class="space-y-5">
                <div>
                    <p class="text-xs text-gray-500 mb-2 font-medium">Diagramas por tipo</p>
                    <div class="flex gap-2">
                        <div class="flex-1 bg-indigo-50 rounded-lg p-3 text-center">
                            <div class="text-2xl font-bold text-indigo-600">{{ $projectsPng }}</div>
                            <div class="text-xs text-indigo-400 mt-0.5">PNG</div>
                            <div class="text-xs text-gray-400">{{ round($projectsPng/$totalP*100) }}%</div>
                        </div>
                        <div class="flex-1 bg-emerald-50 rounded-lg p-3 text-center">
                            <div class="text-2xl font-bold text-emerald-600">{{ $projectsVect }}</div>
                            <div class="text-xs text-emerald-400 mt-0.5">Vectorial</div>
                            <div class="text-xs text-gray-400">{{ round($projectsVect/$totalP*100) }}%</div>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-2 font-medium">Switches por tipo</p>
                    <div class="flex gap-2">
                        <div class="flex-1 bg-violet-50 rounded-lg p-3 text-center">
                            <div class="text-2xl font-bold text-violet-600">{{ $standaloneSwitches }}</div>
                            <div class="text-xs text-violet-400 mt-0.5">Standalone</div>
                            <div class="text-xs text-gray-400">{{ round($standaloneSwitches/$totalSw*100) }}%</div>
                        </div>
                        <div class="flex-1 bg-amber-50 rounded-lg p-3 text-center">
                            <div class="text-2xl font-bold text-amber-600">{{ $stackedSwitches }}</div>
                            <div class="text-xs text-amber-400 mt-0.5">Stack</div>
                            <div class="text-xs text-gray-400">{{ round($stackedSwitches/$totalSw*100) }}%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Actividad reciente --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="ri-history-line text-amber-500"></i> Actividad Reciente
            </h2>
            <div class="space-y-3">
                @forelse($recentBatches as $batch)
                <div class="flex items-start gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full flex-shrink-0 {{ $batch->status==='completed'?'bg-emerald-400':($batch->status==='failed'?'bg-rose-400':($batch->status==='processing'?'bg-sky-400':'bg-amber-400')) }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-gray-700 truncate">{{ $batch->client->name ?? 'Sin cliente' }} &mdash; {{ $batch->name ?? 'Area #'.$batch->id }}</p>
                        <p class="text-xs text-gray-400">{{ $batch->updated_at->diffForHumans() }}</p>
                    </div>
                    <span class="ml-auto text-xs px-1.5 py-0.5 rounded font-medium flex-shrink-0 {{ $batch->status==='completed'?'bg-emerald-50 text-emerald-600':($batch->status==='failed'?'bg-rose-50 text-rose-600':($batch->status==='processing'?'bg-sky-50 text-sky-600':'bg-amber-50 text-amber-600')) }}">{{ ucfirst($batch->status) }}</span>
                </div>
                @empty
                <p class="text-xs text-gray-400 text-center py-4">Sin actividad reciente</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- TABLA CLIENTES + ULTIMOS DIAGRAMAS --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2"><i class="ri-table-line text-indigo-500"></i> Resumen por Cliente</h2>
                <a href="{{ route('admin.clients.manage.index') }}" class="text-xs text-indigo-600 hover:underline">Ver todos &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-2 text-left font-semibold">Cliente</th>
                            <th class="px-4 py-2 text-center font-semibold">Areas</th>
                            <th class="px-4 py-2 text-center font-semibold">Switches</th>
                            <th class="px-4 py-2 text-center font-semibold">OK</th>
                            <th class="px-4 py-2 text-center font-semibold">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($clientSummary as $c)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-2.5"><a href="{{ route('admin.clients.manage.show', $c) }}" class="font-medium text-gray-800 hover:text-indigo-600">{{ $c->name }}</a></td>
                            <td class="px-4 py-2.5 text-center text-gray-600">{{ $c->batches_count }}</td>
                            <td class="px-4 py-2.5 text-center text-gray-600">{{ $c->switch_total }}</td>
                            <td class="px-4 py-2.5 text-center"><span class="text-emerald-600 font-semibold">{{ $c->completed_batches }}</span></td>
                            <td class="px-4 py-2.5 text-center">@if($c->failed_batches>0)<span class="text-rose-600 font-semibold">{{ $c->failed_batches }}</span>@else<span class="text-gray-300">&mdash;</span>@endif</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No hay clientes con areas registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2"><i class="ri-layout-grid-line text-emerald-500"></i> Ultimos Diagramas</h2>
                <a href="{{ route('admin.assembler.index') }}" class="text-xs text-emerald-600 hover:underline">Ver todos &rarr;</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recentProjects as $proj)
                <div class="px-4 py-3 hover:bg-gray-50 transition flex items-center gap-3">
                    <span class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-lg {{ $proj->type==='vectorial'?'bg-emerald-50 text-emerald-600':'bg-indigo-50 text-indigo-600' }}">
                        <i class="{{ $proj->type==='vectorial'?'ri-pen-nib-line':'ri-image-line' }} text-sm"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-gray-800 truncate">{{ $proj->name }}</p>
                        <p class="text-xs text-gray-400">{{ $proj->client->name ?? '&mdash;' }} &middot; {{ $proj->updated_at->diffForHumans() }}</p>
                    </div>
                    <a href="{{ route('admin.assembler.edit', $proj) }}" class="text-gray-400 hover:text-indigo-600 flex-shrink-0"><i class="ri-pencil-line text-sm"></i></a>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-xs text-gray-400">No hay diagramas creados aun</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ACCESOS RAPIDOS --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2"><i class="ri-apps-line text-gray-500"></i> Accesos Rapidos</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            <a href="{{ route('admin.home') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition text-center">
                <i class="ri-upload-cloud-line text-2xl"></i><span class="text-xs font-medium">Subir Archivos</span>
            </a>
            <a href="{{ route('admin.inventario.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-sky-50 text-sky-600 hover:bg-sky-100 transition text-center">
                <i class="ri-list-check-3 text-2xl"></i><span class="text-xs font-medium">Inventario</span>
            </a>
            <a href="{{ route('admin.clients.manage.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-violet-50 text-violet-600 hover:bg-violet-100 transition text-center">
                <i class="ri-building-2-line text-2xl"></i><span class="text-xs font-medium">Gestion Clientes</span>
            </a>
            <a href="{{ route('admin.switches.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-100 transition text-center">
                <i class="ri-cpu-line text-2xl"></i><span class="text-xs font-medium">Switches</span>
            </a>
            <a href="{{ route('admin.topology.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 transition text-center">
                <i class="ri-flow-chart text-2xl"></i><span class="text-xs font-medium">Topologia</span>
            </a>
            <a href="{{ route('admin.areas.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-teal-50 text-teal-600 hover:bg-teal-100 transition text-center">
                <i class="ri-node-tree text-2xl"></i><span class="text-xs font-medium">Areas</span>
            </a>
            <a href="{{ route('admin.assembler.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition text-center">
                <i class="ri-layout-grid-line text-2xl"></i><span class="text-xs font-medium">Ensamblador</span>
            </a>
            <a href="{{ route('admin.assembler.create') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-green-50 text-green-600 hover:bg-green-100 transition text-center">
                <i class="ri-add-circle-line text-2xl"></i><span class="text-xs font-medium">Nuevo Diagrama</span>
            </a>
            <a href="{{ route('admin.topology.gojs.show') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-cyan-50 text-cyan-600 hover:bg-cyan-100 transition text-center">
                <i class="ri-share-circle-line text-2xl"></i><span class="text-xs font-medium">Topologia GoJS</span>
            </a>
            <a href="{{ route('admin.topology.custom.show') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-orange-50 text-orange-600 hover:bg-orange-100 transition text-center">
                <i class="ri-tools-line text-2xl"></i><span class="text-xs font-medium">Top. Custom</span>
            </a>
            <a href="{{ route('admin.clients.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-100 transition text-center">
                <i class="ri-contacts-line text-2xl"></i><span class="text-xs font-medium">Clientes</span>
            </a>
            <a href="{{ route('admin.topology.full') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-purple-50 text-purple-600 hover:bg-purple-100 transition text-center">
                <i class="ri-global-line text-2xl"></i><span class="text-xs font-medium">Top. Global</span>
            </a>
        </div>
    </div>

</div>
</x-admin-layout>
