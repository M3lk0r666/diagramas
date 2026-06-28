<x-admin-layout title="Switches | Diagramas" :breadcrumbs="[['name' => 'Dashboard', 'href' => route('dashboard')], ['name' => 'Switches']]">

    <x-slot name="header">
        <h2 class="font-semibold text-xl">Switches analizados</h2>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="max-w-7xl mx-auto py-8 px-4">

            <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
                <p class="text-sm text-gray-500">
                    Total: <span class="font-semibold text-gray-700">{{ $switches->total() }}</span> switches
                    @if($search)
                        · búsqueda: <span class="font-semibold text-blue-600">"{{ $search }}"</span>
                        <a href="{{ route('admin.switches.index', array_filter(['client'=>$clientId,'batch'=>$batchId])) }}"
                           class="ml-1 text-xs text-gray-400 hover:text-red-500">✕ limpiar</a>
                    @endif
                </p>

                <div class="flex flex-wrap items-center gap-3">
                    <form method="GET" action="{{ route('admin.switches.index') }}"
                          class="flex flex-wrap items-center gap-2" id="filter-form">

                        {{-- Búsqueda por texto --}}
                        <div class="relative">
                            <input type="text" name="search" value="{{ $search }}"
                                   placeholder="Buscar nombre, IP o modelo…"
                                   class="text-sm border-gray-300 rounded-lg pl-8 pr-3 py-1.5
                                          focus:ring-blue-500 focus:border-blue-500 w-56"
                                   onkeydown="if(event.key==='Enter'){this.form.submit()}">
                            <svg class="absolute left-2.5 top-2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                            </svg>
                        </div>

                        {{-- Filtros selector --}}
                        <select name="client" onchange="this.form.submit()"
                            class="text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Todos los clientes</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) $clientId === (string) $client->id)>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>

                        <select name="batch" onchange="this.form.submit()"
                            class="text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Todos los diagramas</option>
                            @foreach ($batches as $batch)
                                <option value="{{ $batch->id }}" @selected((string) $batchId === (string) $batch->id)>
                                    {{ $batch->name }}
                                </option>
                            @endforeach
                        </select>

                        <button type="submit"
                                class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-blue-100 text-gray-600 hover:text-blue-700 rounded-lg transition">
                            Buscar
                        </button>
                    </form>

                    <a href="{{ route('admin.home') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                        + Nuevo diagrama
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left">Hostname</th>
                            <th class="px-4 py-3 text-left">IP Gestión</th>
                            <th class="px-4 py-3 text-left">MAC</th>
                            <th class="px-4 py-3 text-left">Tipo</th>
                            <th class="px-4 py-3 text-center">Arreglo</th>
                            <th class="px-4 py-3 text-left">Firmware</th>
                            <th class="px-4 py-3 text-center">VLANs</th>
                            <th class="px-4 py-3 text-center">Vecinos EDP</th>
                            <th class="px-4 py-3 text-left">Diagrama</th>
                            <th class="px-4 py-3 text-center">Config</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-4 py-3 text-center">Acc.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($switches as $sw)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.switches.show', $sw) }}"
                                        class="font-semibold text-blue-600 hover:underline">
                                        {{ $sw->sys_name ?? '—' }}
                                    </a>
                                    {{-- @if ($sw->sys_location)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $sw->sys_location }}</p>
                                    @endif --}}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ $sw->management_ip ?? '—' }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">
                                    {{ $sw->system_mac ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $sw->system_type ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1.5"
                                        title="{{ $sw->is_stacked ? 'Stacking (' . count($sw->stack_members ?? []) . ' switches)' : 'Standalone' }}">
                                        <img src="{{ asset('storage/media/' . ($sw->is_stacked ? 'switch-stacking.svg' : 'switch-standalone.svg')) }}"
                                            alt="{{ $sw->is_stacked ? 'Stacking' : 'Standalone' }}" class="w-5 h-5">
                                        <span class="text-xs text-gray-500">
                                            {{ $sw->is_stacked ? 'Stacking' : 'Standalone' }}
                                        </span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    {{ $sw->firmware_version ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-block bg-indigo-50 text-indigo-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                        {{ count($sw->vlans ?? []) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-block bg-teal-50 text-teal-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                        {{ count($sw->edp_ports ?? []) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    @if ($sw->batch)
                                        <a href="{{ route('admin.batches.show', $sw->batch) }}"
                                            class="hover:text-blue-600 hover:underline">
                                            {{ $sw->batch->name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($sw->config_path)
                                        <a href="{{ route('admin.switches.config.download', $sw) }}"
                                           title="Descargar configuración de {{ $sw->sys_name }}"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 hover:bg-blue-100 text-gray-500 hover:text-blue-700 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($sw->parse_status === 'ok')
                                        <span class="inline-block bg-green-100 text-green-700 text-xs font-medium px-2 py-0.5 rounded-full">OK</span>
                                    @else
                                        <span class="inline-block bg-red-100 text-red-700 text-xs font-medium px-2 py-0.5 rounded-full"
                                              title="{{ $sw->parse_error }}">Error</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('admin.switches.destroy', $sw) }}"
                                          onsubmit="return confirm('¿Eliminar {{ addslashes($sw->sys_name ?? 'este switch') }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                title="Eliminar switch"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 hover:bg-red-100 text-red-400 hover:text-red-600 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-10 text-center text-gray-400">
                                    No hay switches procesados aún.
                                    <a href="{{ route('admin.home') }}"
                                        class="text-blue-600 hover:underline ml-1">Subir archivos</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $switches->links() }}</div>
        </div>
    </div>

</x-admin-layout>
