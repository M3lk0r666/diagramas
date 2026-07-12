<x-admin-layout title="Diagramas | Beto´s" :breadcrumbs="[
    [
        'name' => 'Dashboard',
        'href' => route('dashboard'),
    ],
    [
        'name' => 'Carga de Archivos',
    ],
]">


    <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
        <h1 class="text-xl font-semibold text-blue-900">
            Subir (Uploads) de Archivos de Configuración
        </h1>
        <p class="mt-2 text-sm text-blue-700">
            Desde esta sección puedes subir múltiples archivos <strong>.txt</strong> con información de backups.
            Una vez cargados, se habilitara el boton de <strong>Iniciar Procesamiento</strong> el cual procesará cada
            archivo automáticamente.
        </p>
    </div>

    {{-- Aviso guía de archivos --}}
    <div class="mb-4 flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3.5">
        <div class="flex items-center gap-3 min-w-0">
            <i class="ri-error-warning-line text-amber-500 text-xl shrink-0"></i>
            <p class="text-sm text-amber-800">
                <strong>Antes de subir</strong>, asegúrate de que el archivo tenga la estructura de secciones correcta.
            </p>
        </div>
        <a href="{{ route('admin.guide.index') }}"
           class="shrink-0 flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition">
            <i class="ri-file-list-3-line"></i>
            Ver guía
        </a>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="w-full py-8 px-6">
            {{-- Drop zone --}}
            <div id="drop-zone"
                class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-xl p-12 bg-gray-50 dark:bg-gray-800 cursor-pointer hover:border-blue-400 transition-colors"
                onclick="document.getElementById('file-input').click()">
                <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p class="text-gray-500">Arrastra archivos <span class="font-semibold text-blue-600">.txt</span> aquí o
                    haz
                    clic para seleccionar</p>
                <p class="text-sm text-gray-400 mt-1">Puedes seleccionar múltiples archivos</p>
            </div>

            <form id="upload-form" action="{{ route('admin.upload.store') }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                <input id="file-input" name="files[]" type="file" accept=".txt" multiple class="hidden">
                <input type="hidden" name="mode" id="upload-mode" value="new">

                {{-- Selector de modo: nuevo lote vs. lote existente --}}
                <div class="mt-6 flex gap-3">
                    <button type="button" id="btn-mode-new" onclick="resetForm()"
                        class="flex-1 py-2 px-4 rounded-lg border-2 border-blue-600 bg-blue-600 text-white text-sm font-medium transition">
                        + Nuevo diagrama
                    </button>
                    <button type="button" id="btn-mode-existing" onclick="setMode('existing')"
                        class="flex-1 py-2 px-4 rounded-lg border-2 border-gray-300 text-gray-600 text-sm font-medium hover:border-blue-400 hover:text-blue-600 transition">
                        Agregar a diagrama existente
                    </button>
                </div>

                {{-- Panel: nuevo lote --}}
                <div id="panel-new" class="space-y-4 mt-4">
                    {{-- Cliente --}}
                    <div>
                        <label for="client-select"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Cliente <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <select id="client-select" name="client_id"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <option value="">— Sin cliente —</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}"
                                    {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">
                            ¿No existe el cliente? <a href="{{ route('admin.clients.index') }}"
                                class="text-blue-600 hover:underline">Créalo aquí</a>.
                        </p>
                    </div>
                    {{-- Nombre del diagrama --}}
                    <div>
                        <label for="diagram-name"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Nombre del diagrama <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input id="diagram-name" name="name" type="text" maxlength="120"
                            placeholder="Ej: Diagrama Norte, Edificio A, Campus Sur…"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Panel: lote existente --}}
                <div id="panel-existing" class="mt-4 hidden">
                    <label for="existing-batch" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Selecciona el diagrama al que agregar los archivos
                    </label>
                    <select id="existing-batch" name="existing_batch"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="">— Selecciona un diagrama —</option>
                        @foreach ($allBatches as $b)
                            <option value="{{ $b->id }}">
                                {{ $b->name }}{{ $b->client ? ' — ' . $b->client->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('existing_batch')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Lista de archivos seleccionados --}}
                <div id="file-list" class="mt-6 space-y-2 hidden">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300">Archivos seleccionados:</h3>
                    <div id="files-container"></div>
                </div>

                <button id="process-btn" type="submit"
                    class="hidden mt-6 w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Iniciar Procesamiento
                </button>
            </form>

            <div class="mt-6 mb-6 rounded-lg border border-orange-200 bg-orange-50 p-4">
                <h1 class="text-xl font-semibold text-blue-900">
                    Lotes Procesados
                </h1>
                <p class="mt-2 text-sm text-blue-700">
                    En esta sección apareceran los lotes procesados, y en caso de querer acceder a uno de estos,
                    bastará
                    con dar clic sobre el lote deseado y se mostrará el listado de Archivos Procesados
                </p>
            </div>

            {{-- Lotes recientes --}}
            @if ($recentBatches->count())
                <div class="mt-10">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-3">Lotes recientes</h3>
                    <div class="space-y-2">
                        @foreach ($recentBatches as $batch)
                            <a href="{{ route('admin.batches.show', $batch) }}"
                                class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-sm transition">
                                <span class="font-medium">{{ $batch->name }}</span>
                                <div class="flex items-center gap-4 text-sm text-gray-500">
                                    <span>{{ $batch->processed }}/{{ $batch->total_files }} archivos</span>
                                    <span @class([
                                        'px-2 py-1 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-700' => $batch->status === 'completed',
                                        'bg-blue-100 text-blue-700' => $batch->status === 'processing',
                                        'bg-red-100 text-red-700' => $batch->status === 'failed',
                                        'bg-gray-100 text-gray-600' => $batch->status === 'pending',
                                    ])>{{ $batch->status }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>


    @push('js')
        <script>
            // ── Modo nuevo / existente ───────────────────────────────
            function setMode(mode) {
                document.getElementById('upload-mode').value = mode;
                const isNew = mode === 'new';
                document.getElementById('panel-new').classList.toggle('hidden', !isNew);
                document.getElementById('panel-existing').classList.toggle('hidden', isNew);
                document.getElementById('btn-mode-new').className =
                    'flex-1 py-2 px-4 rounded-lg border-2 text-sm font-medium transition ' +
                    (isNew ? 'border-blue-600 bg-blue-600 text-white' :
                        'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600');
                document.getElementById('btn-mode-existing').className =
                    'flex-1 py-2 px-4 rounded-lg border-2 text-sm font-medium transition ' +
                    (!isNew ? 'border-blue-600 bg-blue-600 text-white' :
                        'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600');
            }

            // ── Limpiar todo el formulario ────────────────────────────
            function resetForm() {
                // Limpiar archivos seleccionados
                const dt = new DataTransfer();
                input.files = dt.files;

                // Ocultar lista de archivos y botón
                container.innerHTML = '';
                list.classList.add('hidden');
                btn.classList.add('hidden');

                // Resetear campos de texto y selects
                document.getElementById('client-select').value = '';
                document.getElementById('diagram-name').value = '';
                document.getElementById('existing-batch').value = '';

                // Resetear drop zone (quitar highlight si lo hubiera)
                dropZone.classList.remove('border-blue-400');

                // Asegurar que el modo quede en 'new'
                setMode('new');
            }

            // ── Archivos ─────────────────────────────────────────────
            const input = document.getElementById('file-input');
            const list = document.getElementById('file-list');
            const container = document.getElementById('files-container');
            const btn = document.getElementById('process-btn');
            const dropZone = document.getElementById('drop-zone');

            input.addEventListener('change', renderFiles);

            dropZone.addEventListener('dragover', e => {
                e.preventDefault();
                dropZone.classList.add('border-blue-400');
            });
            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-blue-400'));
            dropZone.addEventListener('drop', e => {
                e.preventDefault();
                dropZone.classList.remove('border-blue-400');
                // Transferir archivos al input via DataTransfer
                const dt = new DataTransfer();
                [...e.dataTransfer.files].filter(f => f.name.endsWith('.txt')).forEach(f => dt.items.add(f));
                input.files = dt.files;
                renderFiles();
            });

            function renderFiles() {
                container.innerHTML = '';
                if (input.files.length === 0) {
                    list.classList.add('hidden');
                    btn.classList.add('hidden');
                    return;
                }
                list.classList.remove('hidden');
                btn.classList.remove('hidden');
                [...input.files].forEach(f => {
                    container.insertAdjacentHTML('beforeend', `
                <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">${f.name}</span>
                    <span class="ml-auto text-xs text-gray-400">${(f.size/1024).toFixed(1)} KB</span>
                </div>
            `);
                });
            }
        </script>
    @endpush

</x-admin-layout>
