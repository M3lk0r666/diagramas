<x-admin-layout
    title="Crear topología GoJS | Diagramas"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'href' => route('dashboard')],
        ['name' => 'Topología', 'href' => route('admin.topology.index')],
        ['name' => 'Editor GoJS'],
    ]">

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">

            @if (session('info'))
                <div class="rounded-lg bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 text-sm">
                    {{ session('info') }}
                </div>
            @endif

            {{-- Acceso directo a lienzo en blanco --}}
            <div class="flex items-center justify-between p-4 bg-indigo-50 border border-indigo-200 rounded-xl">
                <div>
                    <p class="text-sm font-semibold text-indigo-800">¿Quieres empezar desde cero?</p>
                    <p class="text-xs text-indigo-500 mt-0.5">Abre el editor en blanco y arrastra los dispositivos que necesites desde la paleta.</p>
                </div>
                <a href="{{ route('admin.topology.gojs.blank') }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                           text-white text-xs font-semibold rounded-lg transition shadow-sm whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Lienzo en blanco
                </a>
            </div>

            <form id="topology-form" method="POST" action="{{ route('admin.topology.gojs.build') }}">
                @csrf

                {{-- Nombre --}}
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 space-y-2">
                    <label for="topo-name" class="block text-sm font-semibold text-gray-700">
                        Nombre de la topología
                    </label>
                    <input id="topo-name" name="name" type="text"
                        placeholder="Ej: Campus Norte, Edificio Admin, Piso 2…"
                        value="{{ old('name') }}"
                        class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500
                               @error('name') border-red-400 @enderror"
                        required maxlength="100">
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-400">Este nombre aparecerá como título en el editor GoJS.</p>
                </div>

                @error('switch_ids')
                    <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                        Debes seleccionar al menos un switch.
                    </div>
                @enderror

                {{-- Tabla de switches --}}
                <div class="rounded-xl border border-gray-200 overflow-hidden mt-4">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-700">Switches disponibles</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Selecciona los equipos que cargarán en el editor GoJS</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <input id="sw-search" type="text" placeholder="Buscar…"
                                    class="text-sm border-gray-300 rounded-lg pl-8 pr-3 py-1.5 w-44
                                           focus:ring-indigo-500 focus:border-indigo-500">
                                <svg class="absolute left-2.5 top-2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                                </svg>
                            </div>
                            <button type="button" id="btn-select-all"
                                class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 transition font-medium">
                                Seleccionar todos
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-2 bg-indigo-50 border-b border-indigo-100 text-xs text-indigo-700 font-medium" id="sel-counter">
                        0 switches seleccionados
                    </div>

                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-4 py-3 w-10 text-center">
                                    <input type="checkbox" id="check-page"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </th>
                                <th class="px-4 py-3 text-left">Hostname</th>
                                <th class="px-4 py-3 text-left">Modelo</th>
                                <th class="px-4 py-3 text-left">IP gestión</th>
                                <th class="px-4 py-3 text-left">Diagrama</th>
                                <th class="px-4 py-3 text-center">Tipo</th>
                            </tr>
                        </thead>
                        <tbody id="sw-tbody" class="divide-y divide-gray-100"></tbody>
                    </table>

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

                <div id="hidden-ids"></div>

                <div class="flex justify-end pt-2">
                    <button type="submit" id="btn-submit" disabled
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700
                               disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold
                               rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                        </svg>
                        Abrir en editor GoJS
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('js')
    <script>
    const ALL_SWITCHES = @json($switchesData);
    const PER_PAGE = 15;
    let page = 1, searchQ = '', selectedIds = new Set(), visible = [...ALL_SWITCHES];

    function applyFilter() {
        const q = searchQ.toLowerCase();
        visible = q ? ALL_SWITCHES.filter(s =>
            s.sys_name.toLowerCase().includes(q) || s.model.toLowerCase().includes(q) ||
            s.ip.toLowerCase().includes(q) || s.batch_name.toLowerCase().includes(q)
        ) : [...ALL_SWITCHES];
        page = 1;
        render();
    }

    function render() {
        const total = visible.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        page = Math.min(page, totalPages);
        const rows = visible.slice((page - 1) * PER_PAGE, page * PER_PAGE);

        const tbody = document.getElementById('sw-tbody');
        tbody.innerHTML = rows.length === 0
            ? `<tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Sin switches</td></tr>`
            : rows.map(s => `
            <tr class="hover:bg-indigo-50 cursor-pointer transition-colors" data-id="${s.id}">
                <td class="px-4 py-2.5 text-center">
                    <input type="checkbox" class="row-check rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        data-id="${s.id}" ${selectedIds.has(s.id) ? 'checked' : ''}>
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
            </tr>`).join('');

        const pageIds = rows.map(s => s.id);
        const allChk = pageIds.length > 0 && pageIds.every(id => selectedIds.has(id));
        const chk = document.getElementById('check-page');
        chk.checked = allChk;
        chk.indeterminate = !allChk && pageIds.some(id => selectedIds.has(id));

        tbody.querySelectorAll('tr[data-id]').forEach(row => {
            const id = parseInt(row.dataset.id);
            const c  = row.querySelector('.row-check');
            row.addEventListener('click', e => { if (e.target === c) return; c.checked = !c.checked; toggle(id, c.checked); });
            c.addEventListener('change', () => toggle(id, c.checked));
        });

        document.getElementById('page-info').textContent = `Página ${page} de ${totalPages} · ${total} switches`;
        document.getElementById('btn-prev').disabled = page <= 1;
        document.getElementById('btn-next').disabled = page >= totalPages;

        const pageBtns = document.getElementById('page-btns');
        pageBtns.innerHTML = pageRange(page, totalPages).map(p =>
            p === '…' ? `<span class="px-2 text-gray-400">…</span>`
                : `<button type="button" data-p="${p}"
                    class="px-2.5 py-1 rounded text-xs font-medium transition
                    ${p === page ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-indigo-100 hover:text-indigo-700'}"
                  >${p}</button>`
        ).join('');
        pageBtns.querySelectorAll('button[data-p]').forEach(b =>
            b.addEventListener('click', () => { page = parseInt(b.dataset.p); render(); })
        );

        updateCounter();
        syncHidden();
    }

    function toggle(id, checked) {
        if (checked) selectedIds.add(id); else selectedIds.delete(id);
        updateCounter();
        syncHidden();
        const rows = visible.slice((page - 1) * PER_PAGE, page * PER_PAGE);
        const pageIds = rows.map(s => s.id);
        const allChk = pageIds.length > 0 && pageIds.every(i => selectedIds.has(i));
        const chk = document.getElementById('check-page');
        chk.checked = allChk;
        chk.indeterminate = !allChk && pageIds.some(i => selectedIds.has(i));
    }

    function updateCounter() {
        const n = selectedIds.size;
        document.getElementById('sel-counter').textContent = `${n} switch${n !== 1 ? 'es' : ''} seleccionado${n !== 1 ? 's' : ''}`;
        document.getElementById('btn-submit').disabled = n === 0;
    }

    function syncHidden() {
        document.getElementById('hidden-ids').innerHTML =
            [...selectedIds].map(id => `<input type="hidden" name="switch_ids[]" value="${id}">`).join('');
    }

    function pageRange(c, t) {
        if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1);
        const p = [1];
        if (c > 3) p.push('…');
        for (let i = Math.max(2, c - 1); i <= Math.min(t - 1, c + 1); i++) p.push(i);
        if (c < t - 2) p.push('…');
        p.push(t);
        return p;
    }

    document.getElementById('sw-search').addEventListener('input', e => { searchQ = e.target.value.trim(); applyFilter(); });
    document.getElementById('btn-prev').addEventListener('click', () => { page--; render(); });
    document.getElementById('btn-next').addEventListener('click', () => { page++; render(); });
    document.getElementById('check-page').addEventListener('change', e => {
        visible.slice((page - 1) * PER_PAGE, page * PER_PAGE).forEach(s => {
            if (e.target.checked) selectedIds.add(s.id); else selectedIds.delete(s.id);
        });
        render();
    });

    let allSel = false;
    document.getElementById('btn-select-all').addEventListener('click', () => {
        allSel = !allSel;
        if (allSel) { visible.forEach(s => selectedIds.add(s.id)); document.getElementById('btn-select-all').textContent = 'Deseleccionar todos'; }
        else         { visible.forEach(s => selectedIds.delete(s.id)); document.getElementById('btn-select-all').textContent = 'Seleccionar todos'; }
        render();
    });

    document.getElementById('topology-form').addEventListener('submit', e => {
        if (selectedIds.size === 0) { e.preventDefault(); alert('Selecciona al menos un switch.'); }
    });

    render();
    </script>
    @endpush

</x-admin-layout>
