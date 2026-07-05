<x-admin-layout :title="$batch->name . ' — Diagrama | Diagramas'" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Diagramas', 'href' => route('admin.client.upload')],
    ['name' => $batch->name, 'href' => route('admin.batches.show', $batch)],
    ['name' => 'Diagrama exportado'],
]">
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Diagrama — {{ $batch->name }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 px-4 space-y-4">

        {{-- Mensajes flash --}}
        @if (session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        {{-- Header del batch --}}
        <div
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-4
                    flex flex-wrap items-center gap-4 text-sm">
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wide">Lote</span>
                <p class="font-semibold">{{ $batch->name }}</p>
            </div>
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wide">Equipos</span>
                <p class="font-semibold">{{ $batch->switches->where('parse_status', 'ok')->count() }}</p>
            </div>
            @if ($batch->client)
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wide">Cliente</span>
                    <p class="font-semibold">{{ $batch->client->name }}</p>
                </div>
            @endif

            <div class="ml-auto flex items-center gap-2">
                {{-- Regenerar --}}
                <form method="POST" action="{{ route('admin.batches.diagram.regenerate', $batch) }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-gray-100 hover:bg-blue-100 text-gray-600 hover:text-blue-700 rounded-full transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Regenerar
                    </button>
                </form>

                {{-- Descargar PNG --}}
                @if ($hasImage)
                    <a href="{{ route('admin.batches.diagram.image', $batch) }}"
                        download="{{ Str::slug($batch->name) }}_diagrama.png"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded-full transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Descargar PNG
                    </a>
                @endif
            </div>
        </div>

        {{-- Imagen del diagrama --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            @if ($hasImage)
                <div class="p-2 text-center bg-gray-50 dark:bg-gray-900">
                    <img src="{{ route('admin.batches.diagram.image', $batch) }}?t={{ time() }}"
                        alt="Diagrama de topología — {{ $batch->name }}"
                        class="max-w-full mx-auto rounded shadow-sm cursor-zoom-in" id="diagram-img"
                        onclick="toggleZoom(this)">
                </div>
                <p class="text-center text-xs text-gray-400 py-2">
                    Haz clic en la imagen para ampliar · <a href="{{ route('admin.batches.diagram.image', $batch) }}"
                        target="_blank" class="text-blue-500 hover:underline">Abrir en nueva pestaña</a>
                </p>
            @else
                {{-- Sin imagen todavía --}}
                <div class="py-20 text-center space-y-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-300 mx-auto" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                            d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                    </svg>
                    <p class="text-gray-400 text-sm">El diagrama aún no ha sido generado.</p>
                    <form method="POST" action="{{ route('admin.batches.diagram.regenerate', $batch) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                            Generar diagrama ahora
                        </button>
                    </form>
                    <p class="text-xs text-gray-400">
                        Requiere Python 3 con <code>networkx</code> y <code>matplotlib</code> instalados.
                    </p>
                </div>
            @endif
        </div>

        {{-- Resumen de nodos/aristas del JSON --}}
        @if ($batch->topology_json)
            @php
                $tj = $batch->topology_json;
                $nodesByRole = collect($tj['nodes'] ?? [])->groupBy('role');
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-4">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-3 text-sm">Resumen de topología</h3>
                <div class="flex flex-wrap gap-3">
                    @foreach (['core', 'backbone', 'distribution', 'access'] as $role)
                        @if ($nodesByRole->has($role))
                            @php
                                $colors = [
                                    'core' => 'bg-blue-900 text-white',
                                    'backbone' => 'bg-blue-600 text-white',
                                    'distribution' => 'bg-cyan-600 text-white',
                                    'access' => 'bg-green-600 text-white',
                                ];
                                $labels = [
                                    'core' => 'Core',
                                    'backbone' => 'Backbone',
                                    'distribution' => 'Distribución',
                                    'access' => 'Acceso',
                                ];
                            @endphp
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium {{ $colors[$role] }}">
                                {{ $labels[$role] }}: {{ $nodesByRole[$role]->count() }}
                            </span>
                        @endif
                    @endforeach
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        {{ count($tj['edges'] ?? []) }} enlaces
                    </span>
                </div>
            </div>
        @endif

        {{-- Galería de clústeres por hub --}}
        @if (!empty($batch->topology_clusters) && count($batch->topology_clusters) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-200 text-sm">
                        Clústeres por hub
                        <span class="ml-2 text-xs font-normal text-gray-400">({{ count($batch->topology_clusters) }}
                            hubs)</span>
                    </h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($batch->topology_clusters as $cluster)
                        @php
                            $filename = basename($cluster['image_path'] ?? '');
                            $imgUrl = $filename
                                ? route('admin.batches.diagram.cluster.image', [$batch, $filename])
                                : null;
                            $hubLabel = $cluster['hub_label'] ?? ($cluster['hub'] ?? 'Hub');
                            $nodeCount = $cluster['node_count'] ?? '?';
                            $edgeCount = $cluster['edge_count'] ?? '?';
                            $hubRole = $cluster['hub_role'] ?? 'backbone';
                            $roleColors = [
                                'core' => 'bg-blue-900',
                                'backbone' => 'bg-blue-600',
                                'distribution' => 'bg-cyan-600',
                            ];
                            $roleColor = $roleColors[$hubRole] ?? 'bg-gray-600';
                        @endphp
                        @if ($imgUrl)
                            <div
                                class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden flex flex-col
                                        hover:shadow-md transition-shadow">
                                {{-- Thumbnail --}}
                                <a href="{{ $imgUrl }}" target="_blank"
                                    class="block bg-gray-50 dark:bg-gray-900 overflow-hidden"
                                    title="Ver {{ $hubLabel }} en detalle">
                                    <img src="{{ $imgUrl }}?t={{ time() }}"
                                        alt="Clúster {{ $hubLabel }}" class="w-full object-cover"
                                        style="max-height:180px;object-fit:cover;object-position:center;"
                                        loading="lazy">
                                </a>
                                {{-- Card footer --}}
                                <div
                                    class="px-3 py-2 flex items-center gap-2 border-t border-gray-100 dark:border-gray-700">
                                    <span
                                        class="inline-block w-2 h-2 rounded-full {{ $roleColor }} flex-shrink-0"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate"
                                            title="{{ $hubLabel }}">{{ $hubLabel }}</p>
                                        <p class="text-xs text-gray-400">{{ $nodeCount }} nodos ·
                                            {{ $edgeCount }} enlaces</p>
                                    </div>
                                    <a href="{{ $imgUrl }}" download="{{ Str::slug($hubLabel) }}_cluster.png"
                                        class="flex-shrink-0 text-gray-400 hover:text-blue-600 transition"
                                        title="Descargar PNG">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    @push('js')
        <script>
            function toggleZoom(img) {
                img.classList.toggle('max-w-full');
                img.classList.toggle('w-full');
                img.classList.toggle('cursor-zoom-in');
                img.classList.toggle('cursor-zoom-out');
            }
        </script>
    @endpush

</x-admin-layout>
