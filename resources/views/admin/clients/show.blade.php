<x-admin-layout :title="$client->name . ' | Clientes'" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Clientes', 'href' => route('admin.clients.index')],
    ['name' => $client->name],
]">
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $client->name }}</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">

        @if (session('success'))
            <div class="px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Encabezado del cliente ───────────────────────────── --}}
        <div
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $client->name }}</h2>
                @if ($client->description)
                    <p class="text-sm text-gray-400 mt-1">{{ $client->description }}</p>
                @endif
                <p class="text-xs text-gray-400 mt-2">Cliente desde {{ $client->created_at->format('d/m/Y') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="inline-block bg-indigo-50 text-indigo-700 text-sm font-semibold px-3 py-1 rounded-full">
                    {{ $client->batches->count() }} diagrama(s)
                </span>
                <a href="{{ route('admin.home') }}?client={{ $client->id }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    + Nuevo diagrama
                </a>
            </div>
        </div>

        {{-- ── Diagramas del cliente ────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold">Diagramas / Lotes</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Nombre</th>
                        <th class="px-4 py-3 text-center">Switches</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-center">Fecha</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($client->batches as $batch)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-semibold">
                                <a href="{{ route('admin.batches.show', $batch) }}"
                                    class="text-blue-600 hover:underline">{{ $batch->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-block bg-teal-50 text-teal-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                    {{ $batch->switches_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $stClass = match ($batch->status) {
                                        'completed' => 'bg-green-100 text-green-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        'pending' => 'bg-gray-100 text-gray-500',
                                        default => 'bg-amber-100 text-amber-700',
                                    };
                                @endphp
                                <span
                                    class="inline-block {{ $stClass }} text-xs font-medium px-2 py-0.5 rounded-full">
                                    {{ ucfirst($batch->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-400">
                                {{ $batch->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.switches.index', ['batch' => $batch->id]) }}"
                                    class="text-xs text-gray-500 hover:text-blue-600 hover:underline">
                                    Ver switches →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                                Este cliente no tiene diagramas aún.
                                <a href="{{ route('admin.home') }}" class="text-blue-600 hover:underline ml-1">Subir
                                    archivos</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-admin-layout>
