<x-admin-layout title="Diagramas | Beto´s" :breadcrumbs="[
    [
        'name' => 'Dashboard',
        'href' => route('dashboard'),
    ],
    [
        'name' => 'Archivos Procesados',
    ],
]">

    <x-slot name="header">
        <h2 class="font-semibold text-xl">Análisis de Switches</h2>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="max-w-3xl mx-auto py-8 px-4">
            <h2 class="text-xl font-semibold mb-6">{{ $batch->name }}</h2>

            {{-- Barra de progreso --}}
            <div id="progress-card"
                class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                    <span id="status-text">Procesando...</span>
                    <span><span id="processed">{{ $batch->processed }}</span> / {{ $batch->total_files }}</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                    <div id="progress-bar" class="bg-blue-600 h-3 rounded-full transition-all duration-500"
                        style="width: {{ $batch->progress_percent }}%"></div>
                </div>
            </div>

            {{-- Errores --}}
            <div id="error-list" class="mt-4 space-y-2 @if (!$batch->error_log) hidden @endif">
                @foreach ($batch->error_log ?? [] as $err)
                    <div
                        class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-300">
                        <strong>{{ $err['file'] }}</strong>: {{ $err['message'] }}
                    </div>
                @endforeach
            </div>

            {{-- Switches procesados --}}
            <div class="mt-6">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm text-gray-500">
                        {{ $switches->total() }} equipos procesados
                        @if($switches->lastPage() > 1)
                            · página {{ $switches->currentPage() }} de {{ $switches->lastPage() }}
                        @endif
                    </p>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-4 py-3 text-left w-6"></th>
                                <th class="px-4 py-3 text-left">Hostname</th>
                                <th class="px-4 py-3 text-left">Modelo</th>
                                <th class="px-4 py-3 text-left font-mono">MAC</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($switches as $sw)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                    <td class="px-4 py-3">
                                        <span class="inline-block w-2 h-2 rounded-full {{ $sw->parse_status === 'ok' ? 'bg-green-500' : 'bg-red-400' }}"></span>
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        @if($sw->parse_status === 'ok')
                                            <a href="{{ route('admin.switches.show', $sw) }}"
                                               class="text-blue-600 hover:underline">
                                                {{ $sw->sys_name ?? $sw->original_filename }}
                                            </a>
                                        @else
                                            <span class="text-gray-500" title="{{ $sw->parse_error }}">
                                                {{ $sw->sys_name ?? $sw->original_filename }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $sw->system_type ?? '—' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ $sw->system_mac ?? '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($sw->parse_status === 'ok')
                                            <span class="inline-block px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded-full">OK</span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 bg-red-100 text-red-600 text-xs font-medium rounded-full"
                                                  title="{{ $sw->parse_error }}">Error</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        Sin equipos procesados aún
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($switches->hasPages())
                    <div class="mt-4">{{ $switches->links() }}</div>
                @endif
            </div>

            @if ($batch->status === 'completed' || $batch->status === 'failed')
                {{-- <div class="mt-8 flex gap-4">
                    <a href="{{ route('admin.switches.index') }}" class="btn-primary">Ver switches</a>
                    <a href="{{ route('admin.topology.index') }}" class="btn-secondary">Ver topología</a>
                </div> --}}
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('admin.switches.index', ['batch' => $batch->id]) }}"
                        class="px-5 py-2 rounded-lg bg-blue-600 text-white font-medium shadow hover:bg-blue-700 transition">
                        Ver switches
                    </a>
                    <a href="{{ route('admin.topology.index') }}"
                        class="px-5 py-2 rounded-lg bg-gray-200 text-gray-800 font-medium shadow hover:bg-gray-300 transition">
                        Ver topología
                    </a>
                    <a href="{{ route('admin.batches.diagram', $batch) }}"
                        class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-medium shadow hover:bg-indigo-700 transition inline-flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Diagrama PNG
                    </a>
                </div>
            @endif
        </div>
    </div>


    @push('js')
        <script>
            @if ($batch->status === 'processing' || $batch->status === 'pending')
                const poll = setInterval(async () => {
                    const r = await fetch('{{ route('admin.batches.status', $batch) }}');
                    const d = await r.json();

                    document.getElementById('progress-bar').style.width = d.progress + '%';
                    document.getElementById('processed').textContent = d.processed;
                    document.getElementById('status-text').textContent = d.status === 'processing' ?
                        `Procesando... (${d.processed}/${d.total})` :
                        d.status;

                    if (d.status === 'completed' || d.status === 'failed') {
                        clearInterval(poll);
                        window.location.reload();
                    }
                }, 2000);
            @endif
        </script>
    @endpush

</x-admin-layout>
