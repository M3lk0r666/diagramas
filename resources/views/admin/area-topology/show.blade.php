<x-admin-layout
    title="{{ $batch->name }} · Switches | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard',      'href' => route('dashboard')],
        ['name' => 'Áreas',          'href' => route('admin.areas.index')],
        ['name' => $client->name,    'href' => route('admin.areas.client', $client)],
        ['name' => $batch->name],
    ]">

    <div class="space-y-5">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ $batch->name }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ $client->name }} · Área #{{ $batch->id }}</p>
            </div>
            @if($okCount > 0)
                <a href="{{ route('admin.areas.topology', [$client, $batch]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700
                          text-white text-sm font-semibold rounded-lg transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064"/>
                    </svg>
                    Ver topología del área
                </a>
            @endif
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <div class="text-2xl font-bold text-gray-800">{{ $switches->count() }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Total switches</div>
            </div>
            <div class="bg-white rounded-xl border border-green-200 p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ $okCount }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Procesados OK</div>
            </div>
            <div class="bg-white rounded-xl border border-red-200 p-4 text-center">
                <div class="text-2xl font-bold {{ $failCount > 0 ? 'text-red-500' : 'text-gray-300' }}">{{ $failCount }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Con errores</div>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-700">Switches del área</h2>
            </div>

            @if($switches->isEmpty())
                <div class="p-12 text-center text-gray-400">
                    <p>No hay switches en esta área.</p>
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 text-left">Hostname</th>
                            <th class="px-6 py-3 text-left">Modelo</th>
                            <th class="px-6 py-3 text-left">IP gestión</th>
                            <th class="px-6 py-3 text-left">Número de serie</th>
                            <th class="px-6 py-3 text-center">Tipo</th>
                            <th class="px-6 py-3 text-center">Estado</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($switches as $sw)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3 font-medium text-gray-800">
                                    {{ $sw->sys_name ?? '—' }}
                                </td>
                                <td class="px-6 py-3 text-xs text-gray-500">{{ $sw->system_type ?? '—' }}</td>
                                <td class="px-6 py-3 text-xs font-mono text-gray-500">{{ $sw->management_ip ?? '—' }}</td>
                                <td class="px-6 py-3 text-xs font-mono text-gray-400">{{ $sw->serial_number ?? '—' }}</td>
                                <td class="px-6 py-3 text-center">
                                    @if($sw->is_stacked)
                                        <span class="inline-block px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full text-xs font-medium">Stack</span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 bg-gray-50 text-gray-500 rounded-full text-xs">Standalone</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-center">
                                    @if($sw->parse_status === 'ok')
                                        <span class="inline-block px-2 py-0.5 bg-green-50 text-green-700 rounded-full text-xs font-medium">OK</span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-xs font-medium">Error</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('admin.switches.show', $sw) }}"
                                       class="text-xs text-blue-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</x-admin-layout>
