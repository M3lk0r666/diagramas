<x-admin-layout
    title="Crear topología personalizada | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard',    'href' => route('dashboard')],
        ['name' => 'Topología',    'href' => route('admin.topology.index')],
        ['name' => 'Personalizada'],
    ]">

    <x-slot name="header">
        <h2 class="font-semibold text-xl">Crear topología personalizada</h2>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">

            @if (session('info'))
                <div class="rounded-lg bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 text-sm">
                    {{ session('info') }}
                </div>
            @endif

            <form id="topology-form" method="POST" action="{{ route('admin.topology.custom.build') }}">
                @csrf

                {{-- Nombre de la topología --}}
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 space-y-2">
                    <label for="topo-name" class="block text-sm font-semibold text-gray-700">
                        Nombre de la topología
                    </label>
                    <input id="topo-name" name="name" type="text"
                        placeholder="Ej: Edificio Cómputo, Campus Norte, Piso 3…"
                        value="{{ old('name') }}"
                        class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500
                               @error('name') border-red-400 @enderror"
                        required maxlength="100">
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @error('switch_ids')
                    <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                        Debes seleccionar al menos un switch.
                    </div>
                @enderror

                {{-- Listado de switches --}}
                <div class="rounded-xl border border-gray-200 overflow-hidden mt-4">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-700">Switches disponibles</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Selecciona los equipos que conformarán la topología</p>
                        </div>
                        <div class="flex items-center gap-3">
                            {{-- Buscador --}}
                            <div class="relative">
                                <input id="sw-search" type="text" placeholder="Buscar…"
                                    class="text-sm border-gray-300 rounded-lg pl-8 pr-3 py-1.5 w-44
                                           focus:ring-blue-500 focus:border-blue-500">
                                <svg class="absolute left-2.5 top-2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                                </svg>
                            </div>
                            {{-- Seleccionar / deseleccionar todo --}}
                            <button type="button" id="btn-select-all"
                                class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 transition font-medium">
                                Seleccionar todos
                            </button>
                        </div>
                    </div>

                    {{-- Contador --}}
                    <div class="px-6 py-2 bg-blue-50 border-b border-blue-100 text-xs text-blue-700 font-medium" id="sel-counter">
                        0 switches seleccionados
                    </div>

                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-4 py-3 w-10 text-center">
                                    <input type="checkbox" id="check-page"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th class="px-4 py-3 text-left">Hostname</th>
                                <th class="px-4 py-3 text-left">Modelo</th>
                                <th class="px-4 py-3 text-left">IP gestión</th>
                                <th class="px-4 py-3 text-left">Diagrama</th>
                                <th class="px-4 py-3 text-center">Tipo</th>
                            </tr>
                        </thead>
                        <tbody id="sw-tbody" class="divide-y divide-gray-100">
                            {{-- Renderizado por JS --}}
                        </tbody>
                    </table>

                    {{-- Paginación --}}
                    <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
                        <span id="page-info"></span>
                        <div class="flex items-center gap-2">
                            <button type="button" id="btn-prev"
                                class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600
                                       disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-medium">
                                ← Anterior
                            </button>
                            <div id="page-btns" class="flex gap-1"></div>
                            <button type="button" id="btn-next"
                                class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600
                                       disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-medium">
                                Siguiente →
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Inputs ocultos para los IDs seleccionados --}}
                <div id="hidden-ids"></div>

                {{-- Botón generar --}}
                <div class="flex justify-end pt-2">
                    <button type="submit" id="btn-submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700
                               disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold
                               rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        Generar topología
                    </button>
                </div>

            </form>
        </div>
    </div>

    @push('js')
    <script>
    // ── Datos cargados desde PHP ──────────────────────────────────────────────
    const ALL_SWITCHES = @json($switchesData);

    const PER_PAGE   = 15;
    let page         = 1;
    let searchQ      = '';
    let selectedIds  = new Set();
    let visible      = [];

    // ── Filtrado ──────────────────────────────────────────────────────────────
    function applyFilter() {
        const q = searchQ.toLowerCase();
        visible = q
            ? ALL_SWITCHES.filter(s =>
                s.sys_name.toLowerCase().includes(q) ||
                s.model.toLowerCase().includes(q) ||
                s.ip.toLowerCase().includes(q) ||
                s.batch_name.toLowerCase().includes(q))
            : [...ALL_SWITCHES];
        page = 1;
        render();
    }

    // ── Render tabla ──────────────────────────────────────────────────────────
    function render() {
        const total      = visible.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        page             = Math.min(page, totalPages);
        const start      = (page - 1) * PER_PAGE;
        const rows       = visible.slice(start, start + PER_PAGE);

        const tbody = document.getElementById('sw-tbody');
        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Sin switches para mostrar</td></tr>`;
        } else {
            tbody.innerHTML = rows.map(s => {
                const checked = selectedIds.has(s.id) ? 'checked' : '';
                return `
                <tr class="hover:bg-blue-50 cursor-pointer transition-colors" data-id="${s.id}">
                    <td class="px-4 py-2.5 text-center">
                        <input type="checkbox" class="row-check rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            data-id="${s.id}" ${checked}>
                    </td>
                    <td class="px-4 py-2.5 font-medium text-gray-800">${s.sys_name}</td>
                    <td class="px-4 py-2.5 text-xs text-gray-500">${s.model}</td>
                    <td class="px-4 py-2.5 text-xs font-mono text-gray-500">${s.ip}</td>
                    <td class="px-4 py-2.5 text-xs text-gray-400">${s.batch_name}</td>
                    <td class="px-4 py-2.5 text-center">
                        ${s.is_stacked
                            ? '<span class="inline-block px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full text-xs">Stack</span>'
                            : '<span class="inline-block px-2 py-0.5 bg-gray-50 text-gray-500 rounded-full text-xs">Standalone</span>'
                        }
                    </td>
                </tr>`;
            }).join('');
        }

        // Checkbox "seleccionar página"
        const pageIds = rows.map(s => s.id);
        const allChecked = pageIds.length > 0 && pageIds.every(id => selectedIds.has(id));
        document.getElementById('check-page').checked = allChecked;
        document.getElementById('check-page').indeterminate = !allChecked && pageIds.some(id => selectedIds.has(id));

        // Listeners en filas
        tbody.querySelectorAll('tr[data-id]').forEach(row => {
            const id  = parseInt(row.dataset.id);
            const chk = row.querySelector('.row-check');
            row.addEventListener('click', e => {
                if (e.target === chk) return; // el checkbox lo maneja solo
                chk.checked = !chk.checked;
                toggleId(id, chk.checked);
            });
            chk.addEventListener('change', () => toggleId(id, chk.checked));
        });

        // Paginación
        document.getElementById('page-info').textContent =
            `Página ${page} de ${totalPages} · ${total} switches`;
        document.getElementById('btn-prev').disabled = page <= 1;
        document.getElementById('btn-next').disabled = page >= totalPages;

        const pageBtns = document.getElementById('page-btns');
        pageBtns.innerHTML = pageRange(page, totalPages).map(p =>
            p === '…'
                ? `<span class="px-2 text-gray-400">…</span>`
                : `<button type="button" data-p="${p}"
                    class="px-2.5 py-1 rounded text-xs font-medium transition
                           ${p === page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-700'}"
                  >${p}</button>`
        ).join('');
        pageBtns.querySelectorAll('button[data-p]').forEach(b =>
            b.addEventListener('click', () => { page = parseInt(b.dataset.p); render(); })
        );

        updateCounter();
        syncHiddenInputs();
    }

    function toggleId(id, checked) {
        if (checked) selectedIds.add(id); else selectedIds.delete(id);
        updateCounter();
        syncHiddenInputs();
        // Actualizar check-page
        const rows = visible.slice((page - 1) * PER_PAGE, page * PER_PAGE);
        const pageIds = rows.map(s => s.id);
        const allChecked = pageIds.length > 0 && pageIds.every(i => selectedIds.has(i));
        document.getElementById('check-page').checked = allChecked;
        document.getElementById('check-page').indeterminate = !allChecked && pageIds.some(i => selectedIds.has(i));
    }

    function updateCounter() {
        const n = selectedIds.size;
        document.getElementById('sel-counter').textContent = `${n} switch${n !== 1 ? 'es' : ''} seleccionado${n !== 1 ? 's' : ''}`;
        document.getElementById('btn-submit').disabled = n === 0;
    }

    function syncHiddenInputs() {
        const container = document.getElementById('hidden-ids');
        container.innerHTML = [...selectedIds].map(id =>
            `<input type="hidden" name="switch_ids[]" value="${id}">`
        ).join('');
    }

    function pageRange(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = [1];
        if (current > 3) pages.push('…');
        for (let p = Math.max(2, current - 1); p <= Math.min(total - 1, current + 1); p++) pages.push(p);
        if (current < total - 2) pages.push('…');
        pages.push(total);
        return pages;
    }

    // ── Eventos globales ─────────────────────────────────────────────────────
    document.getElementById('sw-search').addEventListener('input', e => {
        searchQ = e.target.value.trim();
        applyFilter();
    });

    document.getElementById('btn-prev').addEventListener('click', () => { page--; render(); });
    document.getElementById('btn-next').addEventListener('click', () => { page++; render(); });

    // Checkbox "seleccionar página"
    document.getElementById('check-page').addEventListener('change', e => {
        const rows = visible.slice((page - 1) * PER_PAGE, page * PER_PAGE);
        rows.forEach(s => { if (e.target.checked) selectedIds.add(s.id); else selectedIds.delete(s.id); });
        render();
    });

    // Seleccionar todos / deseleccionar todos
    let allSelected = false;
    document.getElementById('btn-select-all').addEventListener('click', () => {
        allSelected = !allSelected;
        if (allSelected) {
            visible.forEach(s => selectedIds.add(s.id));
            document.getElementById('btn-select-all').textContent = 'Deseleccionar todos';
        } else {
            visible.forEach(s => selectedIds.delete(s.id));
            document.getElementById('btn-select-all').textContent = 'Seleccionar todos';
        }
        render();
    });

    // ── Validación antes de enviar ───────────────────────────────────────────
    document.getElementById('topology-form').addEventListener('submit', e => {
        if (selectedIds.size === 0) {
            e.preventDefault();
            alert('Selecciona al menos un switch para generar la topología.');
        }
    });

    // ── Inicializar ───────────────────────────────────────────────────────────
    visible = [...ALL_SWITCHES];
    document.getElementById('btn-submit').disabled = true;
    render();
    </script>
    @endpush

</x-admin-layout>
