<x-admin-layout title="{{ $switch->sys_name ?? 'Switch' }} | Diagramas" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Switches', 'href' => route('admin.switches.index')],
    ['name' => $switch->sys_name ?? 'Detalle'],
]">

    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $switch->sys_name ?? 'Switch sin nombre' }}</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">

        {{-- ── HEADER ─────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                        {{ $switch->sys_name ?? '—' }}
                    </h2>
                    @if($switch->sys_location)
                        <p class="text-sm text-gray-400 mt-0.5">{{ $switch->sys_location }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if($switch->batch)
                        <a href="{{ route('admin.batches.show', $switch->batch) }}"
                           class="text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded-full hover:bg-blue-100 transition">
                            {{ $switch->batch->name }}
                        </a>
                    @endif
                    @if($switch->config_path)
                        <a href="{{ route('admin.switches.config.download', $switch) }}"
                           title="Descargar configuración .txt"
                           class="inline-flex items-center gap-1.5 text-xs bg-gray-100 hover:bg-emerald-100 text-gray-600 hover:text-emerald-700 px-3 py-1.5 rounded-full transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Config .txt
                        </a>
                    @endif

                    {{-- Eliminar switch --}}
                    <form method="POST" action="{{ route('admin.switches.destroy', $switch) }}"
                          onsubmit="return confirm('¿Eliminar {{ addslashes($switch->sys_name ?? 'este switch') }}? Esta acción no se puede deshacer.')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                title="Eliminar switch"
                                class="inline-flex items-center gap-1.5 text-xs bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-700 px-3 py-1.5 rounded-full transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 mt-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">MAC</p>
                    <p class="font-mono font-medium mt-0.5">{{ $switch->system_mac ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Tipo</p>
                    <p class="mt-0.5">{{ $switch->system_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Serie</p>
                    <p class="font-mono mt-0.5">{{ $switch->serial_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">IP Gestión</p>
                    <p class="font-mono mt-0.5">{{ $switch->management_ip ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Firmware</p>
                    <p class="mt-0.5">{{ $switch->firmware_version ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Contacto</p>
                    <p class="mt-0.5 text-gray-600 text-xs">{{ $switch->sys_contact ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- ── ARREGLO (STACK / STANDALONE) ─────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                <img src="{{ asset('storage/media/' . ($switch->is_stacked ? 'switch-stacking.svg' : 'switch-standalone.svg')) }}"
                     alt="{{ $switch->is_stacked ? 'Stacking' : 'Standalone' }}" class="w-6 h-6">
                <h3 class="font-semibold">
                    @if($switch->is_stacked)
                        Arreglo en Stack — {{ count($switch->stack_members ?? []) }} switches
                        @if($switch->stack_topology)
                            <span class="ml-2 inline-flex flex-col gap-1 align-middle">
                                @foreach(explode("\n", $switch->stack_topology) as $line)
                                    @php
                                        // Separa la etiqueta ("Stack/Active Topology is a") del valor (Ring / Daisy-Chain)
                                        preg_match('/^(.*\bis\s+a\b)\s+(.+)$/i', trim($line), $tm);
                                        $label = $tm[1] ?? $line;
                                        $value = $tm[2] ?? null;
                                        $isRing = $value && stripos($value, 'ring') !== false;
                                        $badgeClass = $isRing
                                            ? 'bg-purple-100 text-purple-700'
                                            : 'bg-emerald-100 text-emerald-700';
                                    @endphp
                                    <span class="text-xs text-gray-500">
                                        {{ $label }}
                                        @if($value)
                                            <span class="ml-1 px-2 py-0.5 rounded-full font-semibold {{ $badgeClass }}">
                                                {{ $value }}
                                            </span>
                                        @endif
                                    </span>
                                @endforeach
                            </span>
                        @endif
                    @else
                        Arreglo Standalone (1 switch)
                    @endif
                </h3>
            </div>

            @if($switch->is_stacked)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">Slot</th>
                                <th class="px-4 py-2 text-left">Serie</th>
                                <th class="px-4 py-2 text-left">MAC Address</th>
                                <th class="px-4 py-2 text-left">Rol</th>
                                <th class="px-4 py-2 text-left">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($switch->stack_members ?? [] as $member)
                                <tr>
                                    <td class="px-4 py-2 font-bold text-blue-700">
                                        Slot-{{ $member['slot'] }}
                                        @if(!empty($member['is_current']))
                                            <span class="ml-1 text-xs text-gray-400">(actual)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $member['serial_number'] ?? '—' }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ $member['mac'] ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        @php
                                            $roleColors = [
                                                'Master'  => 'bg-amber-100 text-amber-800',
                                                'Backup'  => 'bg-emerald-100 text-emerald-800',
                                                'Standby' => 'bg-sky-100 text-sky-800',
                                            ];
                                            $roleClass = $roleColors[$member['role'] ?? ''] ?? 'bg-gray-100 text-gray-600';
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $roleClass }}">
                                            {{ $member['role'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $member['stack_state'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin información de miembros</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ── VLANs ───────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold">VLANs ({{ count($switch->vlans ?? []) }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Nombre</th>
                            <th class="px-4 py-2 text-left">VID</th>
                            <th class="px-4 py-2 text-left">IP / Máscara</th>
                            <th class="px-4 py-2 text-left">Flags activas</th>
                            <th class="px-4 py-2 text-center">Puertos activos</th>
                            <th class="px-4 py-2 text-left">VR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($switch->vlans ?? [] as $vlan)
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $vlan['name'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $vlan['vid'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $vlan['protocol_addr'] ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @php $letters = $vlan['flags_active'] ?? []; @endphp
                                    @if(!empty($letters))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($letters as $l)
                                                <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded text-xs font-mono font-bold">{{ $l }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center text-gray-600">{{ $vlan['ports_active'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $vlan['virtual_router'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin VLANs registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── RUTAS IP ────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold">Rutas IP ({{ count($switch->ip_routes ?? []) }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Ori</th>
                            <th class="px-4 py-2 text-left">Destino</th>
                            <th class="px-4 py-2 text-left">Gateway</th>
                            <th class="px-4 py-2 text-center">Mtr</th>
                            <th class="px-4 py-2 text-left">Flags</th>
                            <th class="px-4 py-2 text-left">VLAN</th>
                            <th class="px-4 py-2 text-left">Duración</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($switch->ip_routes ?? [] as $route)
                            <tr>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs font-mono font-bold">{{ $route['ori'] }}</span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs font-medium">{{ $route['destination'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $route['gateway'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $route['mtr'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-400">{{ $route['flags'] ?? '—' }}</td>
                                <td class="px-4 py-2 font-medium">{{ $route['vlan'] }}</td>
                                <td class="px-4 py-2 text-xs text-gray-400">{{ $route['duration'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin rutas registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── VECINOS EDP ─────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold">Vecinos EDP ({{ count($switch->edp_ports ?? []) }})</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Puerto</th>
                        <th class="px-4 py-2 text-left">Vecino</th>
                        <th class="px-4 py-2 text-left">Neighbor-ID</th>
                        <th class="px-4 py-2 text-left">Puerto remoto</th>
                        <th class="px-4 py-2 text-center">VLANs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($switch->edp_ports ?? [] as $edp)
                        <tr>
                            <td class="px-4 py-2 font-bold text-blue-700">{{ $edp['port'] }}</td>
                            <td class="px-4 py-2 font-medium">{{ $edp['neighbor'] }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-gray-400">{{ $edp['neighbor_id'] }}</td>
                            <td class="px-4 py-2 font-mono font-bold">{{ $edp['remote_port'] }}</td>
                            <td class="px-4 py-2 text-center">{{ $edp['num_vlans'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin vecinos EDP</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── PUERTOS ACTIVOS ─────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold">Puertos activos (E + A) — {{ count($switch->active_ports ?? []) }}</h3>
                <div class="flex items-center gap-2">
                    @if(count($switch->active_ports ?? []) > 0)
                        <a href="{{ route('admin.switches.ports-diagram', $switch) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                            Ver diagrama de puertos
                        </a>
                    @endif
                    {{-- Botón Imagen PNG --}}
                    <button onclick="openSwitchPng()"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Ver imagen PNG
                    </button>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Puerto</th>
                        <th class="px-4 py-2 text-left">Descripción</th>
                        <th class="px-4 py-2 text-left">VLAN</th>
                        <th class="px-4 py-2 text-left">Velocidad</th>
                        <th class="px-4 py-2 text-left">Dúplex</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($switch->active_ports ?? [] as $port)
                        <tr>
                            <td class="px-4 py-2 font-bold">{{ $port['port'] }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $port['display_string'] ?: '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $port['vlan_name'] }}</td>
                            <td class="px-4 py-2">{{ $port['speed_actual'] }} Mbps</td>
                            <td class="px-4 py-2">{{ $port['duplex_actual'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin puertos activos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    {{-- ── Modal: Imagen PNG del switch ──────────────────────── --}}
    <div id="png-modal"
         class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.6)">
        <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full flex flex-col" style="max-height:92vh">
            {{-- Cabecera --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <h3 class="font-semibold text-gray-800">
                        Diagrama — {{ $switch->sys_name ?? 'Switch' }}
                    </h3>
                    {{-- Toggle con/sin VLANs --}}
                    <div class="flex rounded-lg overflow-hidden border border-gray-200 text-xs font-medium">
                        <button id="btn-mode-diagram" onclick="setMode(false)"
                                class="px-3 py-1.5 bg-indigo-600 text-white transition">
                            Solo diagrama
                        </button>
                        <button id="btn-mode-vlans" onclick="setMode(true)"
                                class="px-3 py-1.5 bg-white text-gray-500 hover:bg-gray-50 transition">
                            + VLANs
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="generatePng(true)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Regenerar
                    </button>
                    <a id="png-download" href="#" download
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg transition hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Descargar
                    </a>
                    <button onclick="closePngModal()"
                            class="text-gray-400 hover:text-gray-600 transition text-xl leading-none">&times;</button>
                </div>
            </div>

            {{-- Cuerpo --}}
            <div class="flex-1 overflow-auto p-4 flex items-center justify-center min-h-0">
                <div id="png-loading" class="text-center text-gray-400 hidden">
                    <svg class="animate-spin w-10 h-10 mx-auto mb-3 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <p class="text-sm">Generando diagrama...</p>
                </div>
                <div id="png-error" class="text-center text-red-500 hidden">
                    <p class="font-medium">Error al generar el diagrama</p>
                    <p id="png-error-detail" class="text-xs mt-1 text-red-400"></p>
                </div>
                <img id="png-img" src="" alt="Diagrama del switch"
                     class="max-w-full rounded-lg shadow hidden"
                     style="max-height: calc(92vh - 130px)">
            </div>
        </div>
    </div>

    @push('js')
    <script>
        const generateBaseUrl = '{{ route("admin.switches.diagram.generate", $switch) }}';
        const imageBaseUrl    = '{{ route("admin.switches.diagram.image", $switch) }}';
        const csrfToken       = '{{ csrf_token() }}';
        let   withVlans       = false;

        function setMode(vlans) {
            withVlans = vlans;
            document.getElementById('btn-mode-diagram').className =
                'px-3 py-1.5 transition ' + (!vlans ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50');
            document.getElementById('btn-mode-vlans').className =
                'px-3 py-1.5 transition ' + (vlans  ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50');
            generatePng(false);   // intenta cargar cache; genera si no existe
        }

        function generateUrl()  { return generateBaseUrl + (withVlans ? '?with_vlans=1' : ''); }
        function imageUrl()     { return imageBaseUrl    + (withVlans ? '?with_vlans=1' : ''); }
        function downloadName() { return '{{ ($switch->sys_name ?? "switch") }}_diagram' + (withVlans ? '_vlans' : '') + '.png'; }

        function openSwitchPng() {
            document.getElementById('png-modal').classList.remove('hidden');
            document.getElementById('png-modal').classList.add('flex');
            generatePng(false);
        }

        function closePngModal() {
            document.getElementById('png-modal').classList.add('hidden');
            document.getElementById('png-modal').classList.remove('flex');
        }

        document.getElementById('png-modal').addEventListener('click', function(e) {
            if (e.target === this) closePngModal();
        });

        async function generatePng(forceRegen = false) {
            const loading = document.getElementById('png-loading');
            const errBox  = document.getElementById('png-error');
            const img     = document.getElementById('png-img');
            const dl      = document.getElementById('png-download');

            if (!forceRegen) {
                const probe = new Image();
                probe.onload = () => showImage();
                probe.onerror = () => doGenerate();
                probe.src = imageUrl() + (imageUrl().includes('?') ? '&' : '?') + 't=' + Date.now();
                return;
            }

            doGenerate();

            async function doGenerate() {
                [loading, errBox, img, dl].forEach(el => el.classList.add('hidden'));
                loading.classList.remove('hidden');

                try {
                    const res = await fetch(generateUrl(), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    });
                    const json = await res.json();

                    if (!res.ok || json.error) {
                        throw new Error(json.detail || json.error || 'Error desconocido');
                    }

                    showImage();
                } catch (err) {
                    loading.classList.add('hidden');
                    errBox.classList.remove('hidden');
                    document.getElementById('png-error-detail').textContent = err.message;
                }
            }

            function showImage() {
                loading.classList.add('hidden');
                errBox.classList.add('hidden');
                const ts = Date.now();
                img.src = imageUrl() + (imageUrl().includes('?') ? '&' : '?') + 't=' + ts;
                img.classList.remove('hidden');
                dl.href = img.src;
                dl.download = downloadName();
                dl.classList.remove('hidden');
            }
        }
    </script>
    @endpush

</x-admin-layout>
