<x-admin-layout title="IVE · Clientes" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'IVE'],
]">

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">

        {{-- Header --}}
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Infrastructure Visualization Engine
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Selecciona un cliente para abrir su visualización 3D isométrica en una ventana independiente.
            </p>
        </div>

        {{-- Grid de clientes --}}
        @if ($clients->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-16 text-center">
                <i class="ri-box-3-line text-5xl text-gray-300 dark:text-gray-600"></i>
                <p class="mt-4 text-gray-500">No hay clientes con topología procesada.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($clients as $client)
                    @php
                        $areas   = $client->batches->count();
                        $devices = $client->batches->sum(fn ($b) => $b->switches->count());
                    @endphp

                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow overflow-hidden">

                        {{-- Banner previo --}}
                        <div class="h-32 flex items-center justify-center border-b border-gray-100 dark:border-gray-700
                            bg-gradient-to-br from-indigo-50 to-slate-100 dark:from-indigo-950 dark:to-gray-800">
                            <div class="text-center">
                                <i class="ri-box-3-line text-4xl text-indigo-400"></i>
                                <p class="text-xs text-gray-400 mt-2">
                                    {{ $areas }} área{{ $areas !== 1 ? 's' : '' }}
                                    &middot;
                                    {{ $devices }} dispositivo{{ $devices !== 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>

                        {{-- Info --}}
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white truncate">
                                {{ $client->name }}
                            </h3>
                            <p class="text-xs text-gray-400 mt-1">
                                Actualizado {{ $client->updated_at->diffForHumans() }}
                            </p>

                            {{-- Acción --}}
                            <div class="mt-4">
                                <a
                                    href="{{ route('admin.ive.global', $client) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-center gap-2 w-full px-3 py-2
                                           bg-indigo-600 hover:bg-indigo-700 text-white text-sm
                                           font-medium rounded-lg transition-colors">
                                    <i class="ri-box-3-line"></i>
                                    Abrir IVE
                                    <i class="ri-external-link-line text-xs opacity-70"></i>
                                </a>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif

    </div>

</x-admin-layout>
