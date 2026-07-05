<x-admin-layout title="Seleccionar Cliente | Diagramas" :breadcrumbs="[['name' => 'Dashboard', 'href' => route('dashboard')], ['name' => 'Clientes']]">

    <div class="space-y-6">

        {{-- ── Título ── --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Clientes</h1>
                <p class="text-sm text-gray-500 mt-0.5">Selecciona un cliente para acceder a sus vistas</p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Buscador JS --}}
                <div class="relative">
                    <i
                        class="ri-search-line absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input id="hub-search" type="text" placeholder="Buscar cliente…"
                        class="pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 w-52">
                </div>
                <a href="{{ route('admin.client.upload') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition">
                    <i class="ri-upload-2-line text-sm"></i> Subir archivos
                </a>
            </div>
        </div>

        {{-- ── Grid de cards ── --}}
        @if ($clients->isEmpty())
            <div class="text-center py-20 text-gray-400">
                <i class="ri-building-2-line text-5xl mb-3 block"></i>
                <p class="text-base font-medium">No hay clientes registrados</p>
                <a href="{{ route('admin.clients.index') }}"
                    class="mt-3 inline-block text-sm text-indigo-600 hover:underline">
                    Crear primer cliente
                </a>
            </div>
        @else
            <div id="hub-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @foreach ($clients as $client)
                    @php
                        $initials = collect(explode(' ', $client->name))
                            ->map(fn($w) => strtoupper($w[0] ?? ''))
                            ->take(2)
                            ->join('');
                        $colors = ['indigo', 'blue', 'violet', 'emerald', 'orange', 'pink', 'teal', 'rose'];
                        $color = $colors[$client->id % count($colors)];
                    @endphp
                    <div class="hub-card bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition-shadow flex flex-col"
                        data-name="{{ strtolower($client->name) }}">

                        {{-- Card header --}}
                        <div class="p-5 flex items-start gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-{{ $color }}-100 text-{{ $color }}-600
                                flex items-center justify-center text-base font-bold shrink-0">
                                {{ $initials }}
                            </div>
                            <div class="min-w-0">
                                <h2 class="font-semibold text-gray-800 text-sm leading-tight truncate">
                                    {{ $client->name }}</h2>
                                @if ($client->description)
                                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ $client->description }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Stats --}}
                        <div class="px-5 pb-4 grid grid-cols-3 gap-2 border-b border-gray-100">
                            <div class="text-center">
                                <div class="text-lg font-bold text-gray-800">{{ $client->total_switches }}</div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide">Switches</div>
                            </div>
                            <div class="text-center border-x border-gray-100">
                                <div class="text-lg font-bold text-gray-800">{{ $client->total_areas }}</div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide">Áreas</div>
                            </div>
                            <div class="text-center">
                                @php $allOk = $client->ok_areas === $client->total_areas && $client->total_areas > 0; @endphp
                                <div class="text-lg font-bold {{ $allOk ? 'text-emerald-600' : 'text-amber-500' }}">
                                    {{ $client->ok_areas }}/{{ $client->total_areas }}
                                </div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide">Completas</div>
                            </div>
                        </div>

                        {{-- Botones de acceso --}}
                        <div class="p-4 grid grid-cols-3 gap-2 mt-auto">
                            {{-- Inventario --}}
                            <a href="{{ route('admin.hub.inventario', $client) }}"
                                class="flex flex-col items-center gap-1 py-2.5 rounded-lg
                              bg-blue-50 hover:bg-blue-100 text-blue-700 transition group"
                                title="Inventario de switches">
                                <i class="ri-list-check-3 text-lg group-hover:scale-110 transition-transform"></i>
                                <span class="text-[10px] font-semibold uppercase tracking-wide">Inventario</span>
                            </a>

                            {{-- Topología global --}}
                            <a href="{{ route('admin.areas.global', $client) }}"
                                class="flex flex-col items-center gap-1 py-2.5 rounded-lg
                              bg-violet-50 hover:bg-violet-100 text-violet-700 transition group"
                                title="Topología global del cliente">
                                <i class="ri-node-tree text-lg group-hover:scale-110 transition-transform"></i>
                                <span class="text-[10px] font-semibold uppercase tracking-wide">Topología</span>
                            </a>

                            {{-- Áreas ruta original 'admin.areas.client', $client  --}}
                            <a href="{{ route('admin.clients.manage.show', $client) }}"
                                class="flex flex-col items-center gap-1 py-2.5 rounded-lg
                              bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition group"
                                title="Áreas / diagramas del cliente">
                                <i class="ri-map-2-line text-lg group-hover:scale-110 transition-transform"></i>
                                <span class="text-[10px] font-semibold uppercase tracking-wide">Áreas</span>
                            </a>
                        </div>

                        {{-- Footer: link de gestión --}}
                        {{-- <div class="px-4 pb-3">
                            <a href="{{ route('admin.clients.manage.show', $client) }}"
                                class="w-full flex items-center justify-center gap-1.5 py-1.5 text-xs
                              text-gray-500 hover:text-gray-700 hover:bg-gray-50
                              border border-gray-100 rounded-lg transition">
                                <i class="ri-settings-3-line text-sm"></i>
                                Gestionar
                            </a>
                        </div> --}}
                    </div>
                @endforeach
            </div>

            {{-- sin resultados de búsqueda --}}
            <p id="hub-empty" class="hidden text-center text-sm text-gray-400 py-10">
                No se encontró ningún cliente con ese nombre.
            </p>
        @endif

    </div>

    @push('js')
        <script>
            (function() {
                const input = document.getElementById('hub-search');
                if (!input) return;
                input.addEventListener('input', function() {
                    const q = this.value.toLowerCase().trim();
                    const cards = document.querySelectorAll('.hub-card');
                    let visible = 0;
                    cards.forEach(card => {
                        const match = !q || card.dataset.name.includes(q);
                        card.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    const empty = document.getElementById('hub-empty');
                    if (empty) empty.classList.toggle('hidden', visible > 0);
                });
            })();
        </script>
    @endpush

</x-admin-layout>
