<x-admin-layout
    title="{{ $batch->name }} — Gestión | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'href' => route('dashboard')],
        ['name' => 'Gestión de Clientes', 'href' => route('admin.clients.manage.index')],
        ['name' => $client->name, 'href' => route('admin.clients.manage.show', $client)],
        ['name' => $batch->name],
    ]">

    <div class="space-y-5">

        {{-- ── Encabezado ── --}}
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                    <i class="ri-building-line"></i>
                    <span>{{ $client->name }}</span>
                    <i class="ri-arrow-right-s-line"></i>
                    <i class="ri-node-tree text-indigo-400"></i>
                    <span class="text-indigo-600 font-medium">{{ $batch->name }}</span>
                </div>
                <h1 class="text-xl font-bold text-gray-800">Gestión del área</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $switches->count() }} switch(es) ·
                    {{ $switches->where('parse_status', 'ok')->count() }} OK ·
                    {{ $switches->where('parse_status', '!=', 'ok')->count() }} con error
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.areas.topology', [$client, $batch]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition">
                    <i class="ri-node-tree text-sm"></i> Ver topología
                </a>
                <a href="{{ route('admin.areas.show', [$client, $batch]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                          text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition">
                    <i class="ri-file-list-line text-sm"></i> Estado del batch
                </a>
            </div>
        </div>

        {{-- ── Flash ── --}}
        @if(session('success'))
            <div class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
                <i class="ri-checkbox-circle-line shrink-0"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
                <i class="ri-error-warning-line shrink-0"></i> {{ session('error') }}
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- ── Sección 1: Agregar archivos ── --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <i class="ri-upload-2-line text-emerald-500"></i>
                <h2 class="font-semibold text-gray-700 text-sm">Agregar archivos a esta área</h2>
            </div>
            <div class="p-5">
                <form id="form-upload"
                      action="{{ route('admin.upload.store') }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="mode" value="existing">
                    <input type="hidden" name="existing_batch" value="{{ $batch->id }}">

                    {{-- Drop zone --}}
                    <div id="drop-zone"
                         class="border-2 border-dashed border-gray-200 rounded-xl p-8 text-center
                                cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/40 transition-all group">
                        <input id="file-input" type="file" name="files[]" multiple accept=".txt"
                               class="hidden">
                        <div id="drop-placeholder">
                            <i class="ri-upload-cloud-2-line text-3xl text-gray-300 group-hover:text-emerald-400 transition mb-2 block"></i>
                            <p class="text-sm font-medium text-gray-600">
                                Arrastra archivos <span class="text-gray-400">o</span>
                                <button type="button" onclick="document.getElementById('file-input').click()"
                                        class="text-emerald-600 hover:underline font-semibold">
                                    selecciona desde tu equipo
                                </button>
                            </p>
                            <p class="text-xs text-gray-400 mt-1">Archivos .txt de configuración de switches</p>
                        </div>
                        <div id="drop-selected" class="hidden">
                            <i class="ri-file-check-line text-3xl text-emerald-500 mb-2 block"></i>
                            <p id="drop-selected-label" class="text-sm font-semibold text-emerald-700"></p>
                            <button type="button" onclick="clearFiles()"
                                    class="text-xs text-gray-400 hover:text-red-500 mt-1 hover:underline">
                                Quitar selección
                            </button>
                        </div>
                    </div>

                    {{-- Botón submit --}}
                    <div class="flex justify-end mt-4">
                        <button type="submit" id="btn-upload"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold
                                       text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition
                                       disabled:opacity-40 disabled:cursor-not-allowed"
                                disabled>
                            <i class="ri-upload-2-line"></i>
                            Subir y procesar archivos
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- ── Sección 2: Switches en este diagrama ── --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="ri-server-line text-indigo-400"></i>
                    <h2 class="font-semibold text-gray-700 text-sm">Switches en este diagrama</h2>
                    <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2 py-0.5 rounded-full">
                        {{ $switches->count() }}
                    </span>
                </div>
                {{-- Búsqueda inline --}}
                <div class="relative hidden sm:block">
                    <i class="ri-search-line absolute left-2.5 top-2.5 text-gray-400 text-xs"></i>
                    <input id="sw-search" type="text" placeholder="Buscar…"
                           class="pl-7 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg
                                  focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 outline-none w-44">
                </div>
            </div>

            @if($switches->isEmpty())
                <div class="text-center py-16 text-gray-400">
                    <i class="ri-server-line text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay switches en este diagrama todavía.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="sw-table">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-center w-10">#</th>
                                <th class="px-4 py-3 text-left">Hostname</th>
                                <th class="px-4 py-3 text-left hidden md:table-cell">IP Gestión</th>
                                <th class="px-4 py-3 text-left hidden lg:table-cell">Modelo</th>
                                <th class="px-4 py-3 text-left hidden xl:table-cell">Archivo original</th>
                                <th class="px-4 py-3 text-center w-24">Estado</th>
                                <th class="px-4 py-3 text-center w-20">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50" id="sw-tbody">
                            @foreach($switches as $i => $sw)
                                @php
                                    $isOk = $sw->parse_status === 'ok';
                                    $statusCls = $isOk
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-red-50 text-red-600';
                                    $statusLabel = $isOk ? 'OK' : ucfirst($sw->parse_status);
                                @endphp
                                <tr class="sw-row hover:bg-gray-50/60 transition-colors"
                                    data-search="{{ strtolower(($sw->sys_name ?? '') . ' ' . ($sw->management_ip ?? '') . ' ' . ($sw->system_type ?? '') . ' ' . ($sw->original_filename ?? '')) }}">

                                    {{-- # --}}
                                    <td class="px-4 py-3 text-center text-gray-400 text-xs font-mono">
                                        {{ $i + 1 }}
                                    </td>

                                    {{-- Hostname --}}
                                    <td class="px-4 py-3">
                                        @if($isOk)
                                            <a href="{{ route('admin.switches.show', $sw->id) }}"
                                               class="font-medium text-gray-800 hover:text-indigo-600 hover:underline">
                                                {{ $sw->sys_name ?? '—' }}
                                            </a>
                                        @else
                                            <span class="font-medium text-gray-500">
                                                {{ $sw->sys_name ?? $sw->original_filename ?? '—' }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- IP --}}
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600 hidden md:table-cell">
                                        {{ $sw->management_ip ?? '—' }}
                                    </td>

                                    {{-- Modelo --}}
                                    <td class="px-4 py-3 text-xs text-gray-500 hidden lg:table-cell">
                                        {{ $sw->system_type ?? '—' }}
                                    </td>

                                    {{-- Archivo --}}
                                    <td class="px-4 py-3 text-xs text-gray-400 font-mono hidden xl:table-cell truncate max-w-[180px]"
                                        title="{{ $sw->original_filename }}">
                                        {{ $sw->original_filename ?? '—' }}
                                    </td>

                                    {{-- Estado --}}
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusCls }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>

                                    {{-- Acciones --}}
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            @if($isOk)
                                                <a href="{{ route('admin.switches.show', $sw->id) }}"
                                                   title="Ver detalle"
                                                   class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                                                          text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition">
                                                    <i class="ri-eye-line text-sm"></i>
                                                </a>
                                            @endif
                                            <button type="button"
                                                    onclick="confirmDeleteSwitch({{ $sw->id }}, '{{ addslashes($sw->sys_name ?? $sw->original_filename) }}')"
                                                    title="Eliminar switch"
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                                                           text-gray-400 hover:text-red-600 hover:bg-red-50 transition">
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

    {{-- ── Modal de confirmación de eliminación de switch ── --}}
    <div id="modal-del-sw"
         class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i class="ri-server-line text-red-600 text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-gray-800">Eliminar switch</h3>
                    <p id="modal-del-sw-msg" class="text-sm text-gray-600 mt-1"></p>
                    <p class="text-xs text-red-600 mt-2 font-medium">
                        Se eliminarán también sus conexiones y archivo de configuración. No se puede deshacer.
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeSwModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200
                               rounded-lg hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <form id="form-del-sw" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700
                                   rounded-lg transition">
                        Sí, eliminar switch
                    </button>
                </form>
            </div>
        </div>
    </div>

    @push('js')
    <script>
    // ── Upload drag-drop ──────────────────────────────────────────────────────
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const btnUpload = document.getElementById('btn-upload');
    const placeholder = document.getElementById('drop-placeholder');
    const selected   = document.getElementById('drop-selected');
    const selLabel   = document.getElementById('drop-selected-label');

    function updateDropUI(files) {
        if (!files || files.length === 0) {
            placeholder.classList.remove('hidden');
            selected.classList.add('hidden');
            btnUpload.disabled = true;
        } else {
            placeholder.classList.add('hidden');
            selected.classList.remove('hidden');
            selLabel.textContent = files.length === 1
                ? files[0].name
                : `${files.length} archivos seleccionados`;
            btnUpload.disabled = false;
        }
    }

    function clearFiles() {
        fileInput.value = '';
        updateDropUI(null);
    }

    dropZone.addEventListener('click', (e) => {
        if (!e.target.closest('button')) fileInput.click();
    });

    fileInput.addEventListener('change', () => updateDropUI(fileInput.files));

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-emerald-400', 'bg-emerald-50');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-emerald-400', 'bg-emerald-50');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-emerald-400', 'bg-emerald-50');
        const dt = e.dataTransfer;
        // Crear un FileList-like desde los items arrastrados
        const files = Array.from(dt.files).filter(f => f.name.endsWith('.txt'));
        if (files.length === 0) return;
        // Asignar al input via DataTransfer
        const transfer = new DataTransfer();
        files.forEach(f => transfer.items.add(f));
        fileInput.files = transfer.files;
        updateDropUI(fileInput.files);
    });

    // ── Búsqueda de switches ──────────────────────────────────────────────────
    const swSearch = document.getElementById('sw-search');
    if (swSearch) {
        swSearch.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#sw-tbody tr.sw-row').forEach(row => {
                row.classList.toggle('hidden', q && !row.dataset.search.includes(q));
            });
        });
    }

    // ── Modal eliminar switch ─────────────────────────────────────────────────
    function confirmDeleteSwitch(switchId, switchName) {
        const modal = document.getElementById('modal-del-sw');
        document.getElementById('modal-del-sw-msg').textContent =
            `¿Deseas eliminar el switch "${switchName}"?`;
        document.getElementById('form-del-sw').action =
            `{{ url('admin/clientes-manager/' . $client->id . '/batches/' . $batch->id . '/switches') }}/${switchId}`;
        modal.classList.remove('hidden');
    }

    function closeSwModal() {
        document.getElementById('modal-del-sw').classList.add('hidden');
    }

    document.getElementById('modal-del-sw').addEventListener('click', function(e) {
        if (e.target === this) closeSwModal();
    });
    </script>
    @endpush

</x-admin-layout>
