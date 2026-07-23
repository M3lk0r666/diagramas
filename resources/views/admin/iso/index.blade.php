<x-admin-layout title="Isométrica · Clientes | Diagramas" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Vista Isométrica'],
]">

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">

        {{-- Header --}}
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Vista Isométrica 3D</h1>
            <p class="text-sm text-gray-500 mt-1">
                Selecciona un cliente para abrir su visualización isométrica.
            </p>
        </div>

        @if ($clients->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
                <i class="ri-box-2-line text-5xl text-gray-300"></i>
                <p class="mt-4 text-gray-500">No hay clientes con topología procesada.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($clients as $client)
                    @php
                        $areas   = $client->batches->count();
                        $devices = $client->batches->sum(fn ($b) => $b->switches->count());

                        $colors  = ['indigo', 'blue', 'violet', 'emerald', 'orange', 'pink', 'teal', 'rose'];
                        $color   = $colors[$client->id % count($colors)];
                        $initials = collect(explode(' ', $client->name))
                            ->map(fn ($w) => strtoupper($w[0] ?? ''))
                            ->take(2)
                            ->join('');
                    @endphp

                    <div class="bg-white rounded-xl border border-gray-200 hover:shadow-md transition-shadow overflow-hidden">

                        {{-- Banner --}}
                        <div class="h-28 flex items-center justify-center border-b border-gray-100
                                    bg-gradient-to-br from-{{ $color }}-50 to-slate-100 relative">
                            <div class="text-center">
                                <div class="w-14 h-14 rounded-xl bg-{{ $color }}-100 text-{{ $color }}-600
                                            flex items-center justify-center text-xl font-bold mx-auto mb-1 shadow-sm">
                                    {{ $initials }}
                                </div>
                                <p class="text-xs text-gray-400">
                                    {{ $areas }} área{{ $areas !== 1 ? 's' : '' }}
                                    &middot;
                                    {{ $devices }} dispositivo{{ $devices !== 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>

                        {{-- Info + acción --}}
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 truncate">{{ $client->name }}</h3>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Actualizado {{ $client->updated_at->diffForHumans() }}
                            </p>

                            <div class="mt-4">
                                <a href="{{ route('admin.iso.global', $client) }}"
                                   target="_blank"
                                   class="flex items-center justify-center gap-2 w-full px-3 py-2
                                          bg-indigo-600 hover:bg-indigo-700 text-white text-sm
                                          font-medium rounded-lg transition-colors">
                                    <i class="ri-box-2-line"></i>
                                    Abrir Isométrica
                                </a>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif

    </div>

</x-admin-layout>
