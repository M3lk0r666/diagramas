<x-admin-layout
    title="Mapeo de Puertos | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'href' => route('dashboard')],
        ['name' => 'Mapeo de Puertos'],
    ]"
>
<div class="p-4 sm:p-6 space-y-5">

    {{-- Encabezado --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ri-git-branch-line text-indigo-500"></i> Mapeo de Puertos
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">Planes de migración de switches guardados</p>
        </div>
        <a href="{{ route('admin.port-mapping.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
            <i class="ri-add-line"></i> Nuevo mapeo
        </a>
    </div>

    {{-- Tabla --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        @if($mappings->isEmpty())
            <div class="py-16 text-center text-gray-400">
                <i class="ri-git-branch-line text-4xl mb-3 block"></i>
                <p class="text-sm">No hay mapeos guardados aún.</p>
                <a href="{{ route('admin.port-mapping.create') }}"
                   class="mt-3 inline-block text-indigo-600 hover:underline text-sm">Crear el primero →</a>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide border-b border-gray-100">
                        <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                        <th class="px-4 py-3 text-left font-semibold">IP</th>
                        <th class="px-4 py-3 text-left font-semibold">Origen</th>
                        <th class="px-4 py-3 text-left font-semibold">Destino</th>
                        <th class="px-4 py-3 text-left font-semibold">Actualizado</th>
                        <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($mappings as $m)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <a href="{{ route('admin.port-mapping.show', $m) }}"
                               class="hover:text-indigo-600">{{ $m->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">
                            {{ $m->ip ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $m->origin_summary }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $m->dest_summary }}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">
                            {{ $m->updated_at->diffForHumans() }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.port-mapping.show', $m) }}"
                                   class="text-xs px-3 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg transition">
                                    <i class="ri-pencil-line"></i> Abrir
                                </a>
                                <form method="POST" action="{{ route('admin.port-mapping.destroy', $m) }}"
                                      onsubmit="return confirm('¿Eliminar «{{ addslashes($m->name) }}»?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="text-xs px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg transition">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($mappings->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $mappings->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
</x-admin-layout>
