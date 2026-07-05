<x-admin-layout title="Áreas · {{ $client->name }} | Diagramas" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Áreas', 'href' => route('admin.areas.index')],
    ['name' => $client->name],
]">

    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ $client->name }}</h1>
                @if ($client->description)
                    <p class="text-sm text-gray-500 mt-0.5">{{ $client->description }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                {{-- Botón: Reanalizar conexiones cross-área --}}
                <button id="btn-rebuild" onclick="rebuildConnections()"
                    class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300
                           hover:border-amber-400 hover:bg-amber-50 text-gray-600 hover:text-amber-700
                           text-sm font-medium rounded-lg transition shadow-sm">
                    <svg id="rebuild-icon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span id="rebuild-label">Reanalizar conexiones</span>
                </button>

                <a href="{{ route('admin.areas.global', $client) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                          text-white text-sm font-semibold rounded-lg transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064" />
                    </svg>
                    Diagrama global
                </a>
            </div>
        </div>

        {{-- Toast de resultado --}}
        <div id="rebuild-toast" class="hidden px-4 py-3 rounded-lg border text-sm font-medium"></div>

        @if ($batches->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
                <p class="text-gray-500 font-medium">Este cliente no tiene áreas registradas.</p>
                <p class="text-sm text-gray-400 mt-1">Sube archivos para crear áreas automáticamente.</p>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700">Áreas disponibles</h2>
                    <span class="text-xs text-gray-400">{{ $batches->count() }}
                        área{{ $batches->count() !== 1 ? 's' : '' }}</span>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 text-left">Área / Batch</th>
                            <th class="px-6 py-3 text-center">Switches</th>
                            <th class="px-6 py-3 text-center">Estado</th>
                            <th class="px-6 py-3 text-left">Fecha</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($batches as $batch)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800">{{ $batch->name }}</div>
                                    <div class="text-xs text-gray-400 mt-0.5">ID #{{ $batch->id }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="text-green-600 font-semibold">{{ $batch->ok_count }}</span>
                                        <span class="text-gray-300">/</span>
                                        <span class="text-gray-500">{{ $batch->switches_count }}</span>
                                    </div>
                                    @if ($batch->fail_count > 0)
                                        <div class="text-xs text-red-500 mt-0.5">{{ $batch->fail_count }} con error
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @php
                                        $pct =
                                            $batch->switches_count > 0
                                                ? round(($batch->ok_count / $batch->switches_count) * 100)
                                                : 0;
                                    @endphp
                                    @if ($pct >= 80)
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                            Listo ({{ $pct }}%)
                                        </span>
                                    @elseif($pct > 0)
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                                            Parcial ({{ $pct }}%)
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                            Sin datos
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-400">
                                    {{ $batch->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- <a href="{{ route('admin.areas.show', [$client, $batch]) }}"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium
                                                  text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                            </svg>
                                            Switches
                                        </a> --}}
                                        @if ($batch->ok_count > 0)
                                            <a href="{{ route('admin.areas.topology', [$client, $batch]) }}"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium
                                                      text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition shadow-sm">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064" />
                                                </svg>
                                                Ver topología
                                            </a>
                                        @else
                                            <button disabled
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium
                                                       text-gray-400 bg-gray-100 rounded-lg cursor-not-allowed"
                                                title="No hay switches procesados">
                                                Ver topología
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>

    @push('js')
        <script>
            function rebuildConnections() {
                const btn = document.getElementById('btn-rebuild');
                const label = document.getElementById('rebuild-label');
                const icon = document.getElementById('rebuild-icon');
                const toast = document.getElementById('rebuild-toast');

                btn.disabled = true;
                label.textContent = 'Analizando…';
                icon.classList.add('animate-spin');
                toast.classList.add('hidden');

                fetch('{{ route('admin.areas.rebuild-connections', $client) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                    })
                    .then(r => r.json())
                    .then(data => {
                        toast.textContent = data.msg ?? 'Análisis completado.';
                        toast.className = 'px-4 py-3 rounded-lg border text-sm font-medium ' +
                            (data.ok ?
                                'bg-green-50 border-green-200 text-green-800' :
                                'bg-red-50 border-red-200 text-red-800');
                        toast.classList.remove('hidden');
                    })
                    .catch(() => {
                        toast.textContent = 'Error al conectar con el servidor.';
                        toast.className =
                            'px-4 py-3 rounded-lg border text-sm font-medium bg-red-50 border-red-200 text-red-800';
                        toast.classList.remove('hidden');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        label.textContent = 'Reanalizar conexiones';
                        icon.classList.remove('animate-spin');
                    });
            }
        </script>
    @endpush

</x-admin-layout>
