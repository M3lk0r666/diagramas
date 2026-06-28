<x-admin-layout
    title="Gestión de Clientes | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'href' => route('dashboard')],
        ['name' => 'Gestión de Clientes'],
    ]">

    <div class="space-y-5">

        {{-- ── Encabezado ── --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Gestión de Clientes</h1>
                <p class="text-sm text-gray-500 mt-0.5">Administra áreas y equipos por cliente</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.areas.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition">
                    <i class="ri-node-tree text-sm"></i> Topología
                </a>
                <a href="{{ route('admin.clients.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition">
                    <i class="ri-building-line text-sm"></i> Clientes
                </a>
            </div>
        </div>

        {{-- ── Flash ── --}}
        @if(session('success'))
            <div class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
                <i class="ri-checkbox-circle-line shrink-0"></i> {{ session('success') }}
            </div>
        @endif

        {{-- ── Lista de clientes ── --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($clients as $client)
                <a href="{{ route('admin.clients.manage.show', $client) }}"
                   class="group bg-white border border-gray-200 hover:border-indigo-300 hover:shadow-md
                          rounded-xl p-5 transition-all flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl bg-indigo-50 group-hover:bg-indigo-100
                                flex items-center justify-center shrink-0 transition">
                        <i class="ri-building-2-line text-xl text-indigo-500"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-gray-800 group-hover:text-indigo-700 truncate transition">
                            {{ $client->name }}
                        </div>
                        @if($client->description ?? false)
                            <div class="text-xs text-gray-500 mt-0.5 truncate">{{ $client->description }}</div>
                        @endif
                        <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1">
                                <i class="ri-node-tree text-indigo-400"></i>
                                {{ $client->batches_count }} área(s)
                            </span>
                        </div>
                    </div>
                    <i class="ri-arrow-right-s-line text-gray-300 group-hover:text-indigo-400 mt-1 shrink-0 transition"></i>
                </a>
            @empty
                <div class="col-span-full text-center py-16 text-gray-400">
                    <i class="ri-building-line text-4xl mb-2 block"></i>
                    <p class="text-sm">No hay clientes registrados.</p>
                    <a href="{{ route('admin.clients.index') }}" class="mt-2 text-xs text-indigo-600 hover:underline">
                        Crear cliente
                    </a>
                </div>
            @endforelse
        </div>

    </div>

</x-admin-layout>
