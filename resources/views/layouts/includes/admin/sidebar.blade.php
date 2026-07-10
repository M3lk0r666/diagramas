@php
    $_clientsSubmenuActive = request()->routeIs([
        'admin.hub.*',
        'admin.inventario.*',
        'admin.areas.*',
        'admin.topology.*',
    ]);
    $links = [
        [
            'name' => 'Dashboard',
            'icon' => 'ri-dashboard-line',
            'href' => route('admin.dashboard'),
            'active' => request()->routeIs('admin.dashboard'),
        ],
        [
            'header' => 'GESTIÓN',
        ],
        [
            'name' => 'Gestión Clientes',
            'icon' => 'ri-building-2-line',
            'href' => route('admin.clients.manage.index'),
            'active' => request()->routeIs(['admin.clients.manage.*', 'admin.clients.*']),
        ],
        [
            'header' => 'UPLOADS',
        ],
        [
            'name' => 'Subir Archivos',
            'icon' => 'ri-upload-2-line',
            'href' => route('admin.client.upload'),
            'active' => request()->routeIs(['admin.batches.*', 'admin.client.*']),
        ],
        [
            'header' => 'CONSULTAR',
        ],
        [
            'name' => 'Clientes',
            'icon' => 'ri-building-line',
            'active' => $_clientsSubmenuActive,
            'dropdown_id' => 'dd-clientes',
            'open' => $_clientsSubmenuActive,
            'submenu' => [
                [
                    'name' => 'Seleccionar Cliente',
                    'icon' => 'ri-apps-2-line',
                    'href' => route('admin.hub.index'),
                    'active' => request()->routeIs('admin.hub.index'),
                ],
                [
                    'name' => 'Inventario',
                    'icon' => 'ri-list-check-3',
                    'href' => route('admin.inventario.index'),
                    'active' => request()->routeIs(['admin.inventario.*', 'admin.hub.inventario']),
                ],
                /* [
                    'name' => 'Topología',
                    'icon' => 'ri-flow-chart',
                    'href' => route('admin.topology.index'),
                    'active' => request()->routeIs('admin.topology.*'),
                ], */
                [
                    'name' => 'Áreas',
                    'icon' => 'ri-node-tree',
                    'href' => route('admin.areas.index'),
                    'active' => request()->routeIs('admin.areas.*'),
                ],
            ],
        ],
        [
            'header' => 'DISEÑO',
        ],
        [
            'name' => 'Ensamblador',
            'icon' => 'ri-layout-grid-line',
            'href' => route('admin.assembler.index'),
            'active' => request()->routeIs('admin.assembler.*'),
        ],
        [
            'name' => 'Mapeo de Puertos',
            'icon' => 'ri-git-branch-line',
            'href' => route('admin.port-mapping.index'),
            'active' => request()->routeIs('admin.port-mapping.*'),
        ],
        [
            'header' => 'VISTA 3D',
        ],
        [
            'name' => 'Vista 3D',
            'icon' => 'ri-box-3-fill',
            'active' => request()->routeIs(['admin.iso.*', 'admin.ive.*']),
            'open' => request()->routeIs(['admin.iso.*', 'admin.ive.*']),
            'submenu' => [
                [
                    'name' => 'Isométrica',
                    'icon' => 'ri-box-2-line',
                    'href' => route('admin.iso.index'),
                    'active' => request()->routeIs('admin.iso.*'),
                ],
                [
                    'name' => 'IVE · Global',
                    'icon' => 'ri-box-3-line',
                    'href' => route('admin.ive.index'),
                    'active' => request()->routeIs('admin.ive.*'),
                ],
            ],
        ],
    ];
@endphp

<aside id="logo-sidebar"
    class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full bg-white border-r border-gray-200 sm:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
    aria-label="Sidebar">
    <div class="h-full px-3 pb-4 overflow-y-auto bg-white dark:bg-gray-800">
        <ul class="space-y-1 font-medium">
            @foreach ($links as $link)
                <li>
                    @isset($link['header'])
                        <div class="px-2 pt-3 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            {{ $link['header'] }}
                        </div>
                    @elseif(isset($link['submenu']))
                        @php $ddId = $link['dropdown_id'] ?? ('dd-' . Str::slug($link['name'])); @endphp
                        {{-- Dropdown toggle button --}}
                        <button type="button" onclick="toggleSidebarDD('{{ $ddId }}')"
                            class="flex items-center w-full p-2 text-sm text-gray-900 rounded-lg
                                   hover:bg-gray-100 transition
                                   {{ $link['active'] ?? false ? 'bg-gray-100 font-semibold' : '' }}">
                            <span class="w-5 h-5 inline-flex justify-center items-center shrink-0">
                                <i class="{{ $link['icon'] }} text-base"></i>
                            </span>
                            <span class="flex-1 ms-3 text-left whitespace-nowrap">{{ $link['name'] }}</span>
                            <i id="{{ $ddId }}-chevron"
                                class="ri-arrow-down-s-line text-gray-400 text-base transition-transform
                                      {{ $link['open'] ?? false ? '' : '-rotate-90' }}"></i>
                        </button>
                        {{-- Submenu items --}}
                        <ul id="{{ $ddId }}"
                            class="mt-1 ms-2 border-l border-gray-200 pl-3 space-y-0.5
                            {{ $link['open'] ?? ($link['active'] ?? false) ? '' : 'hidden' }}">
                            @foreach ($link['submenu'] as $item)
                                <li>
                                    <a href="{{ $item['href'] }}"
                                        class="flex items-center gap-2 p-2 text-sm rounded-lg transition
                                              {{ $item['active'] ?? false
                                                  ? 'text-indigo-700 bg-indigo-50 font-semibold'
                                                  : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                                        <i
                                            class="{{ $item['icon'] }} text-sm shrink-0
                                                    {{ $item['active'] ?? false ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                                        <span>{{ $item['name'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <a href="{{ $link['href'] }}"
                            class="flex items-center p-2 text-sm text-gray-900 rounded-lg
                                   hover:bg-gray-100 transition
                                   {{ $link['active'] ?? false ? 'bg-gray-200 font-semibold' : '' }}">
                            <span class="w-5 h-5 inline-flex justify-center items-center shrink-0">
                                <i class="{{ $link['icon'] }} text-base"></i>
                            </span>
                            <span class="ms-3">{{ $link['name'] }}</span>
                        </a>
                @endif
                </li>
                @endforeach
            </ul>
        </div>
    </aside>

    <script>
        function toggleSidebarDD(id) {
            const ul = document.getElementById(id);
            const chevron = document.getElementById(id + '-chevron');
            if (!ul) return;
            ul.classList.toggle('hidden');
            if (chevron) chevron.classList.toggle('-rotate-90');
        }
    </script>
