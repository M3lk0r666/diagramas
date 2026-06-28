<x-admin-layout title="Ensamblador de Diagramas" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Ensamblador'],
]">

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Ensamblador de Diagramas</h1>
                <p class="text-sm text-gray-500 mt-1">Crea diagramas globales de red — por imágenes PNG o con elementos vectoriales.</p>
            </div>
            <a href="{{ route('admin.assembler.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="ri-add-line"></i> Nuevo proyecto
            </a>
        </div>

        @if (session('success'))
            <div class="px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Projects grid --}}
        @if ($projects->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-16 text-center">
                <i class="ri-layout-grid-line text-5xl text-gray-300 dark:text-gray-600"></i>
                <p class="mt-4 text-gray-500">No hay proyectos de ensamblaje aún.</p>
                <a href="{{ route('admin.assembler.create') }}"
                   class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="ri-add-line"></i> Crear primer proyecto
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($projects as $project)
                    @php
                        $isVectorial = ($project->type ?? 'png') === 'vectorial';
                        $editorRoute = $isVectorial
                            ? route('admin.assembler.vectorial', $project)
                            : route('admin.assembler.edit', $project);
                        $canvasObjs  = $project->canvas_json['objects']    ?? ($project->canvas_json['nodes'] ?? []);
                        $canvasEdges = $project->canvas_json['connectors'] ?? ($project->canvas_json['edges'] ?? []);
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow overflow-hidden">

                        {{-- Preview banner --}}
                        <div class="h-36 flex items-center justify-center border-b border-gray-100 dark:border-gray-700
                            {{ $isVectorial
                                ? 'bg-gradient-to-br from-indigo-50 to-slate-100 dark:from-indigo-950 dark:to-gray-800'
                                : 'bg-gradient-to-br from-slate-50 to-slate-100 dark:from-gray-700 dark:to-gray-800' }}">
                            @if (count($canvasObjs) > 0)
                                <div class="text-center">
                                    <i class="{{ $isVectorial ? 'ri-node-tree' : 'ri-layout-grid-line' }} text-3xl
                                        {{ $isVectorial ? 'text-indigo-400' : 'text-blue-400' }}"></i>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ count($canvasObjs) }} {{ $isVectorial ? 'switch(es)' : 'imagen(es)' }}
                                        @if (count($canvasEdges) > 0)
                                            · {{ count($canvasEdges) }} conex.
                                        @endif
                                    </p>
                                </div>
                            @else
                                <div class="text-center">
                                    <i class="{{ $isVectorial ? 'ri-node-tree' : 'ri-image-2-line' }}
                                        text-3xl text-gray-300 dark:text-gray-600"></i>
                                    <p class="text-xs text-gray-400 mt-1">Canvas vacío</p>
                                </div>
                            @endif
                        </div>

                        <div class="p-4">
                            <div class="flex items-start gap-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white truncate flex-1">
                                    {{ $project->name }}
                                </h3>
                                {{-- Type badge --}}
                                <span class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $isVectorial
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                                    <i class="{{ $isVectorial ? 'ri-node-tree' : 'ri-image-line' }}"></i>
                                    {{ $isVectorial ? 'Vectorial' : 'PNG' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="ri-building-2-line mr-1"></i>{{ $project->client->name ?? '—' }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                Modificado {{ $project->updated_at->diffForHumans() }}
                            </p>
                            <div class="flex items-center gap-2 mt-4">
                                <a href="{{ $editorRoute }}"
                                   class="flex-1 text-center px-3 py-1.5 text-white text-xs font-medium rounded-lg transition-colors
                                    {{ $isVectorial
                                        ? 'bg-indigo-600 hover:bg-indigo-700'
                                        : 'bg-blue-600 hover:bg-blue-700' }}">
                                    <i class="ri-edit-line mr-1"></i>Abrir editor
                                </a>
                                <form method="POST" action="{{ route('admin.assembler.destroy', $project) }}"
                                      onsubmit="return confirm('¿Eliminar «{{ addslashes($project->name) }}»?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="px-3 py-1.5 text-xs text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-1">{{ $projects->links() }}</div>
        @endif
    </div>

</x-admin-layout>
