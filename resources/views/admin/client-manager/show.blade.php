<x-admin-layout title="{{ $client->name }} — Gestión | Diagramas" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Gestión de Clientes', 'href' => route('admin.clients.manage.index')],
    ['name' => $client->name],
]">

    <div class="space-y-5">

        {{-- ── Encabezado ── --}}
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                    <i class="ri-building-2-line text-2xl text-indigo-500"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">{{ $client->name }}</h1>
                    @if ($client->description ?? false)
                        <p class="text-sm text-gray-500 mt-0.5">{{ $client->description }}</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.areas.client', $client) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition">
                    <i class="ri-node-tree text-sm"></i> Ver áreas
                </a>
                <a href="{{ route('admin.areas.global', $client) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition">
                    <i class="ri-global-line text-sm"></i> Diagrama global
                </a>
                <a href="{{ route('admin.client.upload') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition">
                    <i class="ri-upload-2-line text-sm"></i> Subir archivos
                </a>
            </div>
        </div>

        {{-- ── Flash ── --}}
        @if (session('success'))
            <div
                class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
                <i class="ri-checkbox-circle-line shrink-0"></i> {{ session('success') }}
            </div>
        @endif

        {{-- ── Stats ── --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                    <i class="ri-node-tree text-indigo-500"></i>
                </div>
                <div>
                    <div class="text-xl font-bold text-indigo-700">{{ $batches->count() }}</div>
                    <div class="text-xs text-gray-500">Áreas / Diagramas</div>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                    <i class="ri-server-line text-emerald-500"></i>
                </div>
                <div>
                    <div class="text-xl font-bold text-emerald-700">{{ $okSwitches }}</div>
                    <div class="text-xs text-gray-500">Switches OK</div>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
                    <i class="ri-stack-line text-gray-500"></i>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-700">{{ $totalSwitches }}</div>
                    <div class="text-xs text-gray-500">Total switches</div>
                </div>
            </div>
        </div>

        {{-- ── Tabla de áreas / batches ── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-700 text-sm">Áreas / Diagramas</h2>
                <span class="text-xs text-gray-400">{{ $batches->count() }} área(s)</span>
            </div>

            @if ($batches->isEmpty())
                <div class="text-center py-16 text-gray-400">
                    <i class="ri-node-tree text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay áreas registradas para este cliente.</p>
                    <a href="{{ route('admin.home') }}" class="mt-2 text-xs text-indigo-600 hover:underline">
                        Subir archivos de configuración
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-5 py-3 text-left">Área / Nombre</th>
                                <th class="px-5 py-3 text-center w-28">Switches</th>
                                <th class="px-5 py-3 text-center w-28">Estado</th>
                                <th class="px-5 py-3 text-left hidden sm:table-cell">Fecha</th>
                                <th class="px-5 py-3 text-right w-40">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($batches as $batch)
                                @php
                                    $statusColors = [
                                        'completed' => 'bg-emerald-50 text-emerald-700',
                                        'processing' => 'bg-blue-50 text-blue-700',
                                        'pending' => 'bg-gray-50 text-gray-500',
                                        'failed' => 'bg-red-50 text-red-600',
                                    ];
                                    $sc = $statusColors[$batch->status] ?? 'bg-gray-50 text-gray-500';
                                    $okCount = $batch->ok_count ?? 0;
                                    $totalCount = $batch->switches_count ?? 0;
                                @endphp
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    {{-- Nombre --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <i class="ri-node-tree text-indigo-400 shrink-0"></i>
                                            <span class="font-medium text-gray-800">{{ $batch->name }}</span>
                                        </div>
                                    </td>

                                    {{-- Switches --}}
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="text-emerald-600 font-semibold">{{ $okCount }}</span>
                                        <span class="text-gray-400 text-xs"> / {{ $totalCount }}</span>
                                    </td>

                                    {{-- Estado --}}
                                    <td class="px-5 py-3.5 text-center">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sc }}">
                                            {{ ucfirst($batch->status) }}
                                        </span>
                                    </td>

                                    {{-- Fecha --}}
                                    <td class="px-5 py-3.5 text-xs text-gray-500 hidden sm:table-cell">
                                        {{ $batch->created_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>

                                    {{-- Acciones --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            {{-- Topología --}}
                                            <a href="{{ route('admin.areas.topology', [$client, $batch]) }}"
                                                title="Ver topología"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                                                      text-indigo-500 hover:bg-indigo-50 hover:text-indigo-700 transition">
                                                <i class="ri-node-tree text-sm"></i>
                                            </a>
                                            {{-- Gestionar --}}
                                            <a href="{{ route('admin.clients.manage.batch', [$client, $batch]) }}"
                                                title="Gestionar área"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                                                      text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition">
                                                <i class="ri-settings-3-line text-sm"></i>
                                            </a>
                                            {{-- Eliminar --}}
                                            <button type="button"
                                                onclick="confirmDeleteBatch({{ $batch->id }}, '{{ addslashes($batch->name) }}', {{ $totalCount }})"
                                                title="Eliminar área"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                                                           text-gray-400 hover:bg-red-50 hover:text-red-600 transition">
                                                <i class="ri-delete-bin-line text-sm"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

    {{-- ── Modal de confirmación de eliminación de batch ── --}}
    <div id="modal-del-batch"
        class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i class="ri-error-warning-line text-red-600 text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-gray-800">Eliminar área</h3>
                    <p id="modal-del-batch-msg" class="text-sm text-gray-600 mt-1"></p>
                    <p class="text-xs text-red-600 mt-2 font-medium">
                        Esta acción eliminará el área, todos sus switches y sus conexiones. No se puede deshacer.
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeBatchModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200
                               rounded-lg hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <form id="form-del-batch" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700
                                   rounded-lg transition">
                        Sí, eliminar área
                    </button>
                </form>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            function confirmDeleteBatch(batchId, batchName, switchCount) {
                const modal = document.getElementById('modal-del-batch');
                const msg = document.getElementById('modal-del-batch-msg');
                const form = document.getElementById('form-del-batch');

                msg.textContent = `¿Deseas eliminar el área "${batchName}"? Contiene ${switchCount} switch(es).`;
                form.action = '{{ route('admin.clients.manage.show', $client) }}'.replace(
                    /\/[^\/]+$/, ''
                ) + `/batches/${batchId}`;
                // Construir URL correctamente
                form.action = `{{ url('admin/clientes-manager/' . $client->id . '/batches') }}/${batchId}`;
                modal.classList.remove('hidden');
            }

            function closeBatchModal() {
                document.getElementById('modal-del-batch').classList.add('hidden');
            }

            // Cerrar modal al hacer click en backdrop
            document.getElementById('modal-del-batch').addEventListener('click', function(e) {
                if (e.target === this) closeBatchModal();
            });
        </script>
    @endpush

</x-admin-layout>
