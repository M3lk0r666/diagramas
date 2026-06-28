<x-admin-layout title="Clientes | Diagramas" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Clientes'],
]">
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Clientes</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">

        @if (session('success'))
            <div class="px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Formulario de nuevo cliente ─────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4">Nuevo cliente</h3>
            <form method="POST" action="{{ route('admin.clients.store') }}" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div class="flex-1 min-w-48">
                    <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1">Nombre *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                           placeholder="Ej: Empresa ABC"
                           class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex-1 min-w-48">
                    <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1">Descripción</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="255"
                           placeholder="Opcional"
                           class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit"
                    class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    + Agregar cliente
                </button>
            </form>
        </div>

        {{-- ── Listado de clientes ──────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Cliente</th>
                        <th class="px-4 py-3 text-left">Descripción</th>
                        <th class="px-4 py-3 text-center">Diagramas</th>
                        <th class="px-4 py-3 text-center">Creado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($clients as $client)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-semibold">
                                <a href="{{ route('admin.clients.show', $client) }}"
                                   class="text-blue-600 hover:underline">{{ $client->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $client->description ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block bg-indigo-50 text-indigo-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                    {{ $client->batches_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-400">
                                {{ $client->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Editar nombre --}}
                                    <button onclick="openEdit({{ $client->id }}, '{{ addslashes($client->name) }}', '{{ addslashes($client->description ?? '') }}')"
                                        class="text-xs text-gray-500 hover:text-blue-600 px-2 py-1 rounded hover:bg-blue-50 transition">
                                        Editar
                                    </button>
                                    {{-- Eliminar --}}
                                    <form method="POST" action="{{ route('admin.clients.destroy', $client) }}"
                                          onsubmit="return confirm('¿Eliminar cliente {{ addslashes($client->name) }}? Los diagramas asociados quedarán sin cliente.')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50 transition">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                                No hay clientes registrados aún. Crea el primero arriba.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3">{{ $clients->links() }}</div>
        </div>
    </div>

    {{-- Modal de edición (inline) --}}
    <div id="edit-modal" class="fixed inset-0 bg-black/40 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Editar cliente</h3>
            <form id="edit-form" method="POST">
                @csrf @method('PUT')
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1">Nombre *</label>
                        <input type="text" id="edit-name" name="name" required maxlength="120"
                               class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1">Descripción</label>
                        <input type="text" id="edit-description" name="description" maxlength="255"
                               class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-5">
                    <button type="button" onclick="closeEdit()"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('js')
        <script>
            const baseUrl = '{{ rtrim(route("admin.clients.index"), "/") }}';

            function openEdit(id, name, description) {
                document.getElementById('edit-form').action = baseUrl + '/' + id;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-description').value = description;
                document.getElementById('edit-modal').classList.remove('hidden');
            }
            function closeEdit() {
                document.getElementById('edit-modal').classList.add('hidden');
            }
        </script>
    @endpush

</x-admin-layout>
