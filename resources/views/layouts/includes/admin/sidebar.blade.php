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
            'name' => 'Guía de Archivos',
            'icon' => 'ri-file-list-3-line',
            'href' => route('admin.guide.index'),
            'active' => request()->routeIs('admin.guide.*'),
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

{{-- ── CSS: margin del contenido según estado del sidebar ─────────── --}}
<style>
    /* Transición suave en el contenido principal */
    main > div { transition: margin-left 0.3s ease; }

    /* Sidebar colapsado en desktop: empujar contenido a 64px */
    @media (min-width: 640px) {
        body.sb-collapsed main > div { margin-left: 4rem !important; }
    }
    /* Asegurar que en desktop expandido el margen sea 256px */
    @media (min-width: 640px) {
        body:not(.sb-collapsed) main > div { margin-left: 16rem !important; }
    }
</style>

{{-- ── OVERLAY móvil ────────────────────────────────────────────────── --}}
<div id="sb-overlay"
     onclick="closeMobileSidebar()"
     class="fixed inset-0 z-30 bg-black/40 hidden sm:hidden"></div>

{{-- ── SIDEBAR ──────────────────────────────────────────────────────── --}}
<aside id="logo-sidebar"
    class="fixed top-0 left-0 z-40 h-screen pt-14 bg-white border-r border-gray-200
           transition-all duration-300 ease-in-out
           -translate-x-full sm:translate-x-0 w-64
           dark:bg-gray-800 dark:border-gray-700"
    aria-label="Sidebar">

    <div class="flex flex-col h-full">
        {{-- Lista de navegación (scroll) --}}
        <div class="flex-1 px-2 pt-2 overflow-y-auto overflow-x-hidden">
        <ul class="space-y-0.5 font-medium">
            @foreach ($links as $link)
                <li>
                    @isset($link['header'])
                        {{-- Encabezado de sección (se oculta colapsado) --}}
                        <div class="sb-header px-2 pt-3 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap">
                            {{ $link['header'] }}
                        </div>
                        {{-- Divisor compacto visible solo colapsado --}}
                        <div class="sb-divider hidden border-t border-gray-100 my-2 mx-1"></div>

                    @elseif(isset($link['submenu']))
                        @php
                            $ddId      = $link['dropdown_id'] ?? ('dd-' . Str::slug($link['name']));
                            $flyItems  = json_encode(array_map(fn($i) => [
                                'href'   => $i['href'],
                                'name'   => $i['name'],
                                'icon'   => $i['icon'],
                                'active' => $i['active'] ?? false,
                            ], $link['submenu']));
                        @endphp
                        {{-- Botón dropdown --}}
                        <button type="button"
                                id="btn-{{ $ddId }}"
                                onclick="toggleSidebarDD('{{ $ddId }}')"
                                onmouseenter="sbFlyoutShow(this)"
                                onmouseleave="sbFlyoutScheduleHide()"
                                data-flyout-items='{{ $flyItems }}'
                                data-flyout-label="{{ $link['name'] }}"
                                title="{{ $link['name'] }}"
                                class="sb-link flex items-center w-full px-2 py-2 text-sm text-gray-900 rounded-lg
                                       hover:bg-gray-100 transition
                                       {{ $link['active'] ?? false ? 'bg-gray-100 font-semibold' : '' }}">
                            <span class="w-5 h-5 inline-flex justify-center items-center shrink-0">
                                <i class="{{ $link['icon'] }} text-base"></i>
                            </span>
                            <span class="sb-label flex-1 ms-3 text-left whitespace-nowrap">{{ $link['name'] }}</span>
                            <i id="{{ $ddId }}-chevron"
                               class="sb-label ri-arrow-down-s-line text-gray-400 text-base transition-transform
                                      {{ $link['open'] ?? false ? '' : '-rotate-90' }}"></i>
                        </button>
                        {{-- Submenu normal (expandido) --}}
                        <ul id="{{ $ddId }}"
                            class="sb-submenu mt-0.5 ms-2 border-l border-gray-200 pl-3 space-y-0.5
                                   {{ $link['open'] ?? ($link['active'] ?? false) ? '' : 'hidden' }}">
                            @foreach ($link['submenu'] as $item)
                                <li>
                                    <a href="{{ $item['href'] }}"
                                       title="{{ $item['name'] }}"
                                       class="flex items-center gap-2 px-2 py-2 text-sm rounded-lg transition
                                              {{ $item['active'] ?? false
                                                  ? 'text-indigo-700 bg-indigo-50 font-semibold'
                                                  : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                                        <i class="{{ $item['icon'] }} text-sm shrink-0
                                                  {{ $item['active'] ?? false ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                                        <span class="sb-label">{{ $item['name'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                    @else
                        <a href="{{ $link['href'] }}"
                           title="{{ $link['name'] }}"
                           class="sb-link flex items-center px-2 py-2 text-sm text-gray-900 rounded-lg
                                  hover:bg-gray-100 transition
                                  {{ $link['active'] ?? false ? 'bg-gray-200 font-semibold' : '' }}">
                            <span class="w-5 h-5 inline-flex justify-center items-center shrink-0">
                                <i class="{{ $link['icon'] }} text-base"></i>
                            </span>
                            <span class="sb-label ms-3 whitespace-nowrap">{{ $link['name'] }}</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
        </div>

        {{-- Botón colapsar — anclado al fondo del sidebar (solo desktop) --}}
        <div class="hidden sm:block px-2 py-3 border-t border-gray-100">
            <button onclick="toggleSidebar()"
                    class="sb-link flex items-center w-full px-2 py-2 text-sm text-gray-500 rounded-lg
                           hover:bg-gray-100 hover:text-gray-800 transition">
                <span class="w-5 h-5 inline-flex justify-center items-center shrink-0">
                    <i id="sb-toggle-icon" class="ri-arrow-left-double-line text-base"></i>
                </span>
                <span class="sb-label ms-3 whitespace-nowrap">Colapsar menú</span>
            </button>
        </div>
    </div>
</aside>

{{-- ── FLYOUT flotante (submenús en modo colapsado) ─────────────────── --}}
<div id="sb-flyout"
     class="hidden fixed z-50 w-52 bg-white border border-gray-200 rounded-xl shadow-lg py-1 overflow-hidden"
     style="left: 4.25rem;"
     onmouseenter="sbFlyoutCancelHide()"
     onmouseleave="sbFlyoutScheduleHide()">
</div>

<script>
    const SB_KEY = 'sb_collapsed';
    let _flyTimer = null;

    /* ── Colapsar / expandir ─────────────────────────────────────── */
    function toggleSidebar() {
        const collapsed = document.body.classList.contains('sb-collapsed');
        applySidebar(!collapsed);
        localStorage.setItem(SB_KEY, !collapsed ? '1' : '0');
    }

    function applySidebar(collapse) {
        const sb      = document.getElementById('logo-sidebar');
        const icon    = document.getElementById('sb-toggle-icon');
        const labels  = document.querySelectorAll('.sb-label');
        const headers = document.querySelectorAll('.sb-header');
        const dividers= document.querySelectorAll('.sb-divider');
        const submenus= document.querySelectorAll('.sb-submenu');
        const links   = document.querySelectorAll('.sb-link');

        if (collapse) {
            document.body.classList.add('sb-collapsed');
            sb.style.width = '4rem';
            labels.forEach(el => el.classList.add('hidden'));
            headers.forEach(el => el.classList.add('hidden'));
            dividers.forEach(el => el.classList.remove('hidden'));
            submenus.forEach(el => el.classList.add('hidden'));
            links.forEach(el => el.classList.add('justify-center'));
            if (icon) { icon.classList.replace('ri-arrow-left-double-line','ri-arrow-right-double-line'); }
        } else {
            document.body.classList.remove('sb-collapsed');
            sb.style.width = '16rem';
            labels.forEach(el => el.classList.remove('hidden'));
            headers.forEach(el => el.classList.remove('hidden'));
            dividers.forEach(el => el.classList.add('hidden'));
            links.forEach(el => el.classList.remove('justify-center'));
            if (icon) { icon.classList.replace('ri-arrow-right-double-line','ri-arrow-left-double-line'); }
            sbFlyoutHide();
        }
    }

    /* ── Dropdown de submenú (modo expandido) ────────────────────── */
    function toggleSidebarDD(id) {
        if (document.body.classList.contains('sb-collapsed')) return;
        const ul      = document.getElementById(id);
        const chevron = document.getElementById(id + '-chevron');
        if (!ul) return;
        ul.classList.toggle('hidden');
        if (chevron) chevron.classList.toggle('-rotate-90');
    }

    /* ── Flyout (modo colapsado) ─────────────────────────────────── */
    function sbFlyoutShow(btn) {
        if (!document.body.classList.contains('sb-collapsed')) return;
        sbFlyoutCancelHide();

        const items = JSON.parse(btn.dataset.flyoutItems || '[]');
        const label = btn.dataset.flyoutLabel || '';
        if (!items.length) return;

        const flyout = document.getElementById('sb-flyout');
        const rect   = btn.getBoundingClientRect();

        flyout.innerHTML =
            `<div class="px-3 pt-2 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">${label}</div>` +
            items.map(item =>
                `<a href="${item.href}"
                    class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg mx-1 transition
                           ${item.active ? 'text-indigo-700 bg-indigo-50 font-semibold' : 'text-gray-700 hover:bg-gray-100'}">
                    <i class="${item.icon} text-sm ${item.active ? 'text-indigo-500' : 'text-gray-400'}"></i>
                    <span>${item.name}</span>
                 </a>`
            ).join('');

        // Posición vertical: alinear con el botón, sin salir de la pantalla
        const top = Math.min(rect.top, window.innerHeight - flyout.offsetHeight - 8);
        flyout.style.top = top + 'px';
        flyout.classList.remove('hidden');
    }

    function sbFlyoutScheduleHide() {
        _flyTimer = setTimeout(sbFlyoutHide, 150);
    }
    function sbFlyoutCancelHide() {
        clearTimeout(_flyTimer);
    }
    function sbFlyoutHide() {
        document.getElementById('sb-flyout').classList.add('hidden');
    }

    /* ── Móvil: abrir / cerrar ───────────────────────────────────── */
    function openMobileSidebar() {
        document.getElementById('logo-sidebar').classList.remove('-translate-x-full');
        document.getElementById('sb-overlay').classList.remove('hidden');
    }
    function closeMobileSidebar() {
        document.getElementById('logo-sidebar').classList.add('-translate-x-full');
        document.getElementById('sb-overlay').classList.add('hidden');
    }

    /* ── Inicializar ─────────────────────────────────────────────── */
    (function () {
        if (localStorage.getItem(SB_KEY) === '1') applySidebar(true);
    })();
</script>
