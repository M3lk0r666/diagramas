<x-admin-layout title="{{ $switch->sys_name ?? 'Switch' }} | Diagramas" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Switches', 'href' => route('admin.switches.index')],
    ['name' => $switch->sys_name ?? 'Detalle'],
]">

    @php
        $isOnline = $switch->parse_status === 'ok';
        $activeCount = count($switch->active_ports ?? []);
        $edpCount = count($switch->edp_ports ?? []);
        $vlanCount = count($switch->vlans ?? []);
        $routeCount = count($switch->ip_routes ?? []);
        $statusLabel = $isOnline ? 'ONLINE' : strtoupper($switch->parse_status ?? 'UNKNOWN');
        $statusColor = $isOnline
            ? 'text-emerald-600 bg-emerald-50 border-emerald-200'
            : 'text-red-600 bg-red-50 border-red-200';
        $dotColor = $isOnline ? 'bg-emerald-500' : 'bg-red-500';
        $arreglo = $switch->is_stacked ? 'STACK' : 'STANDALONE';

        // Botón "Volver al inventario" si se llegó desde client-hub
        $fromClientId = request()->query('from_client');
        $fromClient   = $fromClientId ? \App\Models\Client::find((int) $fromClientId) : null;

        // Rol del switch → icono y etiqueta
        $swRole = $switch->is_stacked
            ? 'stack'
            : \App\Services\TopologyBuilderService::detectRoleStatic($switch->sys_name ?? '');
        $roleIconMap = [
            'core' => 'core_switch.png',
            'backbone' => 'backbone_switch.png',
            'dist' => 'dist_switch.png',
            'access' => 'access_switch.png',
            'stack' => 'stack_switch.png',
        ];
        $roleLabelMap = [
            'core' => 'Core',
            'backbone' => 'Backbone',
            'dist' => 'Distribución',
            'access' => 'Acceso',
            'stack' => 'Stack',
        ];
        $roleIconFile = $roleIconMap[$swRole] ?? 'access_switch.png';
        $roleIconUrl = route('admin.topology.icon', $roleIconFile);
        $roleLabel = $roleLabelMap[$swRole] ?? ucfirst($swRole);
    @endphp

    <div class="space-y-5 max-w-7xl mx-auto">

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- ── HEADER BAR ── --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                @if($fromClient)
                    <a href="{{ route('admin.hub.inventario', $fromClient) }}"
                       class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium
                              text-yellow-600 bg-white hover:bg-yellow-50 border border-yellow-200 rounded-lg transition">
                        <i class="ri-arrow-left-s-line text-base"></i> {{ $fromClient->name }}
                    </a>
                @endif
                {{-- Badge estado --}}
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold
                             rounded-full border {{ $statusColor }}">
                    <span class="w-2 h-2 rounded-full {{ $dotColor }} animate-pulse"></span>
                    {{ $statusLabel }} — {{ $switch->sys_name ?? '—' }}
                </span>
                {{-- @if ($switch->batch)
                    <a href="{{ route('admin.batches.show', $switch->batch) }}"
                        class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
                        <i class="ri-node-tree text-xs"></i>
                        {{ $switch->batch->name }}
                    </a>
                @endif --}}
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2 flex-wrap">
                @if ($activeCount > 0)
                    <a href="{{ route('admin.switches.ports-diagram', $switch) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold
                              text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                        <i class="ri-git-branch-line text-sm"></i> Ver diagrama de puertos
                    </a>
                @endif
                <button onclick="openSwitchPng()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold
                               text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition">
                    <i class="ri-image-line text-sm"></i> Ver imagen PNG
                </button>
                @if ($switch->config_path)
                    <a href="{{ route('admin.switches.config.download', $switch) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold
                              text-gray-700 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition">
                        <i class="ri-file-text-line text-sm"></i> Config.txt
                    </a>
                @endif
                <form method="POST" action="{{ route('admin.switches.destroy', $switch) }}"
                    onsubmit="return confirm('¿Eliminar {{ addslashes($switch->sys_name ?? 'este switch') }}? No se puede deshacer.')">
                    @csrf @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold
                                   text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition">
                        <i class="ri-delete-bin-line text-sm"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>

        <h1 class="text-2xl font-bold text-gray-800">Detalle del Switch</h1>

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- ── STATS CARDS ── --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

            {{-- Puertos activos --}}
            <div
                class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-4
                        border-l-4 border-l-emerald-400">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                    <i class="ri-router-line text-lg text-emerald-500"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800">
                        {{ $activeCount }}
                    </div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">Puertos activos</div>
                </div>
            </div>

            {{-- Vecinos EDP --}}
            <div
                class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-4
                        border-l-4 border-l-blue-400">
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                    <i class="ri-share-line text-lg text-blue-500"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800">{{ $edpCount }}</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">Vecinos EDP</div>
                </div>
            </div>

            {{-- Estado --}}
            <div
                class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-4
                        border-l-4 {{ $isOnline ? 'border-l-emerald-400' : 'border-l-red-400' }}">
                <div
                    class="w-10 h-10 rounded-xl {{ $isOnline ? 'bg-emerald-50' : 'bg-red-50' }}
                            flex items-center justify-center shrink-0">
                    <i class="ri-pulse-line text-lg {{ $isOnline ? 'text-emerald-500' : 'text-red-500' }}"></i>
                </div>
                <div>
                    <div class="mt-0.5">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full
                                     {{ $isOnline ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white' }}">
                            <span
                                class="w-1.5 h-1.5 rounded-full bg-white {{ $isOnline ? 'animate-pulse' : '' }}"></span>
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-1">Estado Switch</div>
                </div>
            </div>

            {{-- VLANs --}}
            <div
                class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-4
                        border-l-4 border-l-amber-400">
                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                    <i class="ri-stack-line text-lg text-amber-500"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800">{{ $vlanCount }}</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">VLANs</div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- ── INFO GRID ── --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5 text-sm">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">MAC Address</p>
                    <p class="font-mono font-semibold text-blue-600 text-sm">{{ $switch->system_mac ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Modelo / Tipo</p>
                    <p class="font-medium text-gray-700 text-sm">{{ $switch->system_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Número de Serie</p>
                    <p class="font-mono text-gray-700 text-sm">{{ $switch->serial_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Versión Firmware</p>
                    <p class="font-mono text-gray-700 text-sm">{{ $switch->firmware_version ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">IP Gestión</p>
                    <p class="font-mono font-semibold text-blue-600 text-sm">{{ $switch->management_ip ?? '—' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Contacto SysContact</p>
                    <p class="text-gray-600 text-sm">{{ $switch->sys_contact ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Estado Físico</p>
                    <div class="flex items-center gap-2">
                        <img src="{{ $roleIconUrl }}" alt="{{ $roleLabel }}"
                            class="w-9 h-9 object-contain shrink-0">
                        <div>
                            <div class="text-sm font-semibold text-gray-700">{{ $roleLabel }}</div>
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $arreglo }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- ── STACK (solo si aplica) ── --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        @if ($switch->is_stacked && !empty($switch->stack_members))
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-3">
                    <i class="ri-stack-line text-amber-500"></i>
                    <h3 class="font-semibold text-gray-700 text-sm">
                        Arreglo en Stack — {{ count($switch->stack_members) }} switches
                    </h3>
                    @if ($switch->stack_topology)
                        @php preg_match('/is\s+a\s+(\S+)/i', $switch->stack_topology, $tm); @endphp
                        @if (!empty($tm[1]))
                            <span
                                class="text-xs px-2 py-0.5 rounded-full
                                     {{ stripos($tm[1], 'ring') !== false ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700' }}
                                     font-medium">
                                {{ $tm[1] }}
                            </span>
                        @endif
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                            <tr>
                                <th class="px-5 py-2.5 text-left">Slot</th>
                                <th class="px-5 py-2.5 text-left">Serie</th>
                                <th class="px-5 py-2.5 text-left">MAC Address</th>
                                <th class="px-5 py-2.5 text-left">Rol</th>
                                <th class="px-5 py-2.5 text-left">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($switch->stack_members as $member)
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-2.5 font-bold text-blue-600">
                                        Slot-{{ $member['slot'] }}
                                        @if (!empty($member['is_current']))
                                            <span class="ml-1 text-xs text-gray-400 font-normal">(actual)</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 font-mono text-xs text-gray-600">
                                        {{ $member['serial_number'] ?? '—' }}</td>
                                    <td class="px-5 py-2.5 font-mono text-xs text-gray-400">{{ $member['mac'] ?? '—' }}
                                    </td>
                                    <td class="px-5 py-2.5">
                                        @php
                                            $roleCls = [
                                                'Master' => 'bg-amber-100 text-amber-700',
                                                'Backup' => 'bg-emerald-100 text-emerald-700',
                                                'Standby' => 'bg-sky-100 text-sky-700',
                                            ];
                                            $rc = $roleCls[$member['role'] ?? ''] ?? 'bg-gray-100 text-gray-500';
                                        @endphp
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $rc }}">
                                            {{ $member['role'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-2.5 text-xs text-gray-500">{{ $member['stack_state'] ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- ── VLANs ── --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-gray-700 text-sm">VLANs</h3>
                    <span class="text-xs bg-emerald-100 text-emerald-700 font-semibold px-2 py-0.5 rounded-full">
                        {{ $vlanCount }} ACTIVO{{ $vlanCount !== 1 ? 'S' : '' }}
                    </span>
                </div>
                <button class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 transition">
                    <i class="ri-settings-3-line"></i> Configurar
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Nombre</th>
                            <th class="px-5 py-2.5 text-left">VID</th>
                            <th class="px-5 py-2.5 text-left">IP / Máscara</th>
                            <th class="px-5 py-2.5 text-center">Estado</th>
                            <th class="px-5 py-2.5 text-center">Puertos</th>
                            <th class="px-5 py-2.5 text-left">VR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($switch->vlans ?? [] as $vlan)
                            @php
                                $portsActive = $vlan['ports_active'] ?? '0';
                                $portNum = (int) $portsActive;
                                $vOnline = $portNum > 0 || in_array('A', $vlan['flags_active'] ?? []);
                            @endphp
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-5 py-2.5 font-semibold text-gray-800">{{ $vlan['name'] }}</td>
                                <td class="px-5 py-2.5 font-mono text-xs text-gray-500">{{ $vlan['vid'] }}</td>
                                <td class="px-5 py-2.5 font-mono text-xs">
                                    @if (!empty($vlan['protocol_addr']))
                                        <span class="text-blue-600">{{ $vlan['protocol_addr'] }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-center">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-bold rounded-full
                                                 {{ $vOnline ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white' }}">
                                        {{ $vOnline ? 'ONLINE' : 'OFFLINE' }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-center">
                                    <span
                                        class="text-xs font-semibold {{ $vOnline ? 'text-emerald-600' : 'text-red-500' }}">
                                        {{ $portsActive }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-xs text-gray-400">{{ $vlan['virtual_router'] ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-gray-400 text-sm">Sin VLANs
                                    registradas</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- ── RUTAS IP + VECINOS EDP (2 columnas) ── --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- Rutas IP --}}
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2">
                    <i class="ri-route-line text-indigo-400"></i>
                    <h3 class="font-semibold text-gray-700 text-sm">Rutas IP ({{ $routeCount }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                            <tr>
                                <th class="px-5 py-2.5 text-left">Destino</th>
                                <th class="px-5 py-2.5 text-left">Gateway</th>
                                <th class="px-5 py-2.5 text-left">VLAN</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($switch->ip_routes ?? [] as $route)
                                @php
                                    $oriColors = [
                                        '#S' => 'bg-blue-100 text-blue-700',
                                        '#D' => 'bg-violet-100 text-violet-700',
                                        '#C' => 'bg-emerald-100 text-emerald-700',
                                        '#O' => 'bg-orange-100 text-orange-700',
                                    ];
                                    $oriCls = $oriColors[$route['ori']] ?? 'bg-gray-100 text-gray-600';
                                @endphp
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-2.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-bold px-1.5 py-0.5 rounded {{ $oriCls }}">
                                                {{ $route['ori'] }}
                                            </span>
                                            <span class="font-mono text-xs font-medium text-gray-700">
                                                {{ $route['destination'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-2.5 font-mono text-xs text-gray-500">{{ $route['gateway'] }}
                                    </td>
                                    <td class="px-5 py-2.5">
                                        @if (!empty($route['vlan']))
                                            <span
                                                class="text-xs font-semibold text-blue-600">{{ $route['vlan'] }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-8 text-center text-gray-400 text-sm">Sin rutas
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Vecinos EDP --}}
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2">
                    <i class="ri-share-circle-line text-emerald-400"></i>
                    <h3 class="font-semibold text-gray-700 text-sm">Vecinos EDP ({{ $edpCount }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                            <tr>
                                <th class="px-5 py-2.5 text-left">Puerto local</th>
                                <th class="px-5 py-2.5 text-left">Vecino</th>
                                <th class="px-5 py-2.5 text-right">VLANs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($switch->edp_ports ?? [] as $edp)
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-2.5">
                                        <span
                                            class="font-mono font-bold text-blue-600 text-sm">{{ $edp['port'] }}</span>
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                                            <span class="font-medium text-gray-700">{{ $edp['neighbor'] }}</span>
                                        </div>
                                        @if (!empty($edp['remote_port']))
                                            <div class="text-xs text-gray-400 ml-3.5 mt-0.5 font-mono">
                                                Puerto remoto: {{ $edp['remote_port'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-right">
                                        <span
                                            class="text-xs font-semibold text-gray-600">{{ $edp['num_vlans'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-8 text-center text-gray-400 text-sm">Sin vecinos
                                        EDP</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- ── PUERTOS ACTIVOS ── --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-700 text-sm">Detalle de Puertos Activos</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Monitoreo en tiempo real de interfaces (E + A)</p>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold
                             bg-emerald-500 text-white rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                    LIVE STATUS
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Puerto</th>
                            <th class="px-5 py-2.5 text-left">Descripción</th>
                            <th class="px-5 py-2.5 text-left">VLAN</th>
                            <th class="px-5 py-2.5 text-left">Velocidad</th>
                            <th class="px-5 py-2.5 text-left">Dúplex</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($switch->active_ports ?? [] as $port)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-5 py-2.5">
                                    <span class="font-mono font-bold text-blue-600">{{ $port['port'] }}</span>
                                </td>
                                <td class="px-5 py-2.5 text-xs port-desc-cell cursor-pointer group/desc"
                                    data-port="{{ $port['port'] }}" data-desc="{{ $port['display_string'] }}"
                                    onclick="startEditDesc(this)" title="Clic para editar descripción">
                                    <span
                                        class="port-desc-text {{ $port['display_string'] ? 'text-gray-700' : 'text-gray-300 italic' }}">{{ $port['display_string'] ?: '—' }}</span>
                                    <i
                                        class="ri-pencil-line ml-1 text-gray-300 opacity-0 group-hover/desc:opacity-100 transition-opacity text-[11px]"></i>
                                </td>
                                <td class="px-5 py-2.5">
                                    <span
                                        class="font-mono text-xs text-gray-600">({{ $port['vlan_name'] ?? '—' }})</span>
                                </td>
                                <td class="px-5 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <i class="ri-flashlight-line text-amber-400 text-xs"></i>
                                        <span class="font-semibold text-gray-700 text-xs">
                                            {{ $port['speed_actual'] }} Mbps
                                        </span>
                                        <span
                                            class="px-1.5 py-0.5 text-xs font-bold bg-emerald-500 text-white rounded-full">
                                            ONLINE
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-2.5">
                                    <span
                                        class="px-2 py-0.5 text-xs font-bold rounded
                                                 {{ strtoupper($port['duplex_actual']) === 'FULL' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ strtoupper($port['duplex_actual']) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">Sin puertos
                                    activos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-2.5 border-t border-gray-50 bg-gray-50/50 text-xs text-gray-400 text-center">
                <i class="ri-time-line mr-1"></i>
                Última actualización sincronizada: {{ $switch->parsed_at?->diffForHumans() ?? 'hace un momento' }}
            </div>
        </div>

    </div>{{-- /max-w-7xl --}}

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ── MODAL: Imagen PNG del switch ── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div id="png-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.6)">
        <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full flex flex-col" style="max-height:92vh">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <h3 class="font-semibold text-gray-800">Diagrama — {{ $switch->sys_name ?? 'Switch' }}</h3>
                    <div class="flex rounded-lg overflow-hidden border border-gray-200 text-xs font-medium">
                        <button id="btn-mode-diagram" onclick="setMode(false)"
                            class="px-3 py-1.5 bg-violet-600 text-white transition">Solo diagrama</button>
                        <button id="btn-mode-vlans" onclick="setMode(true)"
                            class="px-3 py-1.5 bg-white text-gray-500 hover:bg-gray-50 transition">+ VLANs</button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="generatePng(true)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                        <i class="ri-refresh-line"></i> Regenerar
                    </button>
                    <a id="png-download" href="#" download
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-violet-50 hover:bg-violet-100 text-violet-700 rounded-lg transition hidden">
                        <i class="ri-download-line"></i> Descargar
                    </a>
                    <button onclick="closePngModal()"
                        class="text-gray-400 hover:text-gray-600 transition text-xl leading-none">&times;</button>
                </div>
            </div>
            <div class="flex-1 overflow-auto p-4 flex items-center justify-center min-h-0">
                <div id="png-loading" class="text-center text-gray-400 hidden">
                    <svg class="animate-spin w-10 h-10 mx-auto mb-3 text-violet-500"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                    <p class="text-sm">Generando diagrama...</p>
                </div>
                <div id="png-error" class="text-center text-red-500 hidden">
                    <p class="font-medium">Error al generar el diagrama</p>
                    <p id="png-error-detail" class="text-xs mt-1 text-red-400"></p>
                </div>
                <img id="png-img" src="" alt="Diagrama del switch"
                    class="max-w-full rounded-lg shadow hidden" style="max-height:calc(92vh - 130px)">
            </div>
        </div>
    </div>

    @push('js')
        <script>
            const generateBaseUrl = '{{ route('admin.switches.diagram.generate', $switch) }}';
            const imageBaseUrl = '{{ route('admin.switches.diagram.image', $switch) }}';
            const csrfToken = '{{ csrf_token() }}';
            let withVlans = false;

            function setMode(vlans) {
                withVlans = vlans;
                document.getElementById('btn-mode-diagram').className =
                    'px-3 py-1.5 transition ' + (!vlans ? 'bg-violet-600 text-white' :
                        'bg-white text-gray-500 hover:bg-gray-50');
                document.getElementById('btn-mode-vlans').className =
                    'px-3 py-1.5 transition ' + (vlans ? 'bg-violet-600 text-white' :
                        'bg-white text-gray-500 hover:bg-gray-50');
                generatePng(false);
            }

            function generateUrl() {
                return generateBaseUrl + (withVlans ? '?with_vlans=1' : '');
            }

            function imageUrl() {
                return imageBaseUrl + (withVlans ? '?with_vlans=1' : '');
            }

            function downloadName() {
                return '{{ $switch->sys_name ?? 'switch' }}_diagram' + (withVlans ? '_vlans' : '') + '.png';
            }

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
                const errBox = document.getElementById('png-error');
                const img = document.getElementById('png-img');
                const dl = document.getElementById('png-download');

                if (!forceRegen) {
                    const probe = new Image();
                    probe.onload = () => showImage();
                    probe.onerror = () => doGenerate();
                    probe.src = imageUrl() + '?t=' + Date.now();
                    return;
                }
                doGenerate();

                async function doGenerate() {
                    [loading, errBox, img, dl].forEach(el => el.classList.add('hidden'));
                    loading.classList.remove('hidden');
                    try {
                        const res = await fetch(generateUrl(), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                        });
                        const json = await res.json();
                        if (!res.ok || json.error) throw new Error(json.detail || json.error || 'Error desconocido');
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
                    img.src = imageUrl() + '?t=' + Date.now();
                    img.classList.remove('hidden');
                    dl.href = img.src;
                    dl.download = downloadName();
                    dl.classList.remove('hidden');
                }
            }
            // ── Inline edit de descripción de puerto ──────────────────────
            const descUpdateUrl = '{{ route('admin.switches.ports.description', $switch) }}';

            function startEditDesc(td) {
                if (td.querySelector('input')) return;
                const currentDesc = td.dataset.desc || '';
                const span = td.querySelector('.port-desc-text');
                const pencil = td.querySelector('.ri-pencil-line');
                if (span) span.style.display = 'none';
                if (pencil) pencil.style.display = 'none';

                const input = document.createElement('input');
                input.type = 'text';
                input.value = currentDesc;
                input.className =
                    'text-xs border border-indigo-300 rounded px-1.5 py-0.5 focus:outline-none focus:ring-1 focus:ring-indigo-400 min-w-[130px] w-full';
                td.appendChild(input);
                input.focus();
                input.select();

                async function saveDesc() {
                    const newDesc = input.value.trim();
                    if (newDesc === (td.dataset.desc || '')) {
                        cancelEdit();
                        return;
                    }
                    try {
                        const res = await fetch(descUpdateUrl, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                port: td.dataset.port,
                                display_string: newDesc
                            }),
                        });
                        const json = await res.json();
                        if (!json.ok) throw new Error(json.error || 'Error');
                        td.dataset.desc = newDesc;
                        finishEdit(newDesc);
                    } catch (err) {
                        cancelEdit();
                        console.error('Error guardando descripción:', err);
                    }
                }

                function finishEdit(savedDesc) {
                    input.remove();
                    if (span) {
                        span.textContent = savedDesc || '—';
                        span.className = 'port-desc-text ' + (savedDesc ? 'text-gray-700' : 'text-gray-300 italic');
                        span.style.display = '';
                    }
                    if (pencil) pencil.style.display = '';
                }

                function cancelEdit() {
                    input.remove();
                    if (span) span.style.display = '';
                    if (pencil) pencil.style.display = '';
                }

                input.addEventListener('blur', saveDesc);
                input.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        input.blur();
                    }
                    if (e.key === 'Escape') {
                        input.removeEventListener('blur', saveDesc);
                        cancelEdit();
                    }
                });
            }
        </script>
    @endpush

</x-admin-layout>
