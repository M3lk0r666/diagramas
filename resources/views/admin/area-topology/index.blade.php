<x-admin-layout
    title="Topología por Áreas | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'href' => route('dashboard')],
        ['name' => 'Áreas'],
    ]">

    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Topología por Áreas</h1>
                <p class="text-sm text-gray-500 mt-0.5">Selecciona un cliente para ver sus áreas y diagramas de topología.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-700 text-xs font-medium rounded-full border border-blue-200">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 01-2-2V7a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2z"/>
                </svg>
                {{ $clients->total() }} clientes
            </span>
        </div>

        @if($clients->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 01-2-2V7a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">No hay clientes registrados.</p>
                <p class="text-sm text-gray-400 mt-1">Sube archivos de configuración para comenzar.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($clients as $client)
                    @php
                        $totalSwitches = $client->batches->sum('switches_count');
                        $okSwitches    = $client->batches->sum('ok_count');
                    @endphp
                    <a href="{{ route('admin.areas.client', $client) }}"
                       class="group bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-300 hover:shadow-md transition-all">

                        <div class="flex items-start justify-between mb-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19 21H5a2 2 0 01-2-2V7a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                                {{ $client->batches_count > 0 ? 'bg-green-50 text-green-700' : 'bg-gray-50 text-gray-500' }}">
                                {{ $client->batches_count }} área{{ $client->batches_count !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        <h3 class="font-semibold text-gray-800 group-hover:text-blue-700 transition-colors truncate">
                            {{ $client->name }}
                        </h3>
                        @if($client->description)
                            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $client->description }}</p>
                        @endif

                        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <span>{{ $okSwitches }} / {{ $totalSwitches }} switches OK</span>
                            <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="flex justify-center">
                {{ $clients->links() }}
            </div>
        @endif

    </div>
</x-admin-layout>
