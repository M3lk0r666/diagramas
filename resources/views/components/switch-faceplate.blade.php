@php use App\Enums\PortStatus; @endphp

{{-- ═══════════════════════════════════════════════════════════════════
     SWITCH FACEPLATE — estilo ExtremeCloud IQ (tema claro)
     Uso: <x-switch-faceplate :device="$device" :ports="$ports" :update-url="..." />
═══════════════════════════════════════════════════════════════════ --}}

@once
    <style>
        /* ── Jack RJ45 (muesca con clip-path, sin imágenes) ── */
        .sw-jack {
            width: 34px; height: 32px;
            min-width: 34px; min-height: 32px;
            flex-shrink: 0;
            background: #fbfbfb;
            border: 1.5px solid #b7bbc2;
            border-radius: 4px;
            /* muesca hacia abajo (fila superior) */
            clip-path: polygon(0 0, 100% 0, 100% 62%, 82% 62%, 82% 100%, 18% 100%, 18% 62%, 0 62%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 4px;
            font-size: 13px;
            line-height: 1;
            color: #8a8f98;
            cursor: pointer;
            transition: transform .1s ease;
            user-select: none;
        }
        .sw-jack:hover { transform: scale(1.12); }
        /* fila inferior: muesca hacia arriba (invertido) */
        .sw-jack.sw-flip {
            clip-path: polygon(18% 0, 82% 0, 82% 38%, 100% 38%, 100% 100%, 0 100%, 0 38%, 18% 38%);
            align-items: flex-end;
            padding-top: 0;
            padding-bottom: 4px;
        }
        /* Estados (ver App\Enums\PortStatus) */
        .sw-jack.active     { background: #b5dd8f; border-color: #7fb84e; color: #3d6320; }
        .sw-jack.reassigned { background: #ffd98e; border-color: #e0a83c; color: #7a5410; }
        .sw-jack.disabled   { background: #f5b5b5; border-color: #d97070; color: #7c1d1d; }
        .sw-jack.sw-selected{ background: #6abf45 !important; border-color: #4e9930 !important; color: #fff !important; }
        /* Mgmt / Console: no interactivos */
        .sw-jack.sw-plain { background: #f4f4f4; border-color: #c9ccd1; cursor: default; }
        .sw-jack.sw-plain:hover { transform: none; }

        .sw-num { font-size: 11px; color: #444; height: 14px; line-height: 14px; text-align: center; }

        /* ── Popover "Port Info" ── */
        .sw-popover {
            position: absolute;
            width: 320px;
            background: #fff;
            border: 1px solid #c9ccd1;
            border-radius: 4px;
            box-shadow: 0 6px 22px rgba(0,0,0,.18);
            z-index: 40;
            font-size: 13px;
        }
        .sw-popover[hidden] { display: none; }
    </style>
@endonce

<div class="relative space-y-3"
     data-sw-faceplate
     data-update-url="{{ $updateUrl ?? '' }}"
     data-csrf="{{ csrf_token() }}">

    {{-- ── BARRA DE EQUIPO ─────────────────────────────────────────── --}}
    {{-- Switch standalone: barra única global --}}
    @if (empty($device['members']))
        <div class="bg-violet-50 border border-violet-200 rounded-xl px-4 py-2.5 flex flex-wrap gap-x-7 gap-y-2 text-xs">
            @foreach ([
                'IP Address'       => $device['ip']       ?? '—',
                'MAC Address'      => $device['mac']      ?? '—',
                'Software Version' => $device['software'] ?? '—',
                'Model'            => $device['model']    ?? '—',
                'Serial #'         => $device['serial']   ?? '—',
                'Make'             => $device['make']     ?? '—',
            ] as $label => $value)
                <div class="shrink-0">
                    <b class="block text-violet-800">{{ $label }}:</b>
                    <span class="text-violet-600 font-mono">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── FACEPLATE(S): uno por slot ──────────────────────────────── --}}
    @foreach ($slots as $slot)
        @php
            $slotKey     = $slot['slot'] ?? 0;
            $activePorts = collect($portsBySlot[$slotKey] ?? [])
                ->filter(fn ($p) => $p['status'] === \App\Enums\PortStatus::Active)
                ->values();
            $m = !empty($device['members'])
                ? collect($device['members'])->firstWhere('slot', $slot['slot'])
                : null;
        @endphp

        {{-- Wrapper por slot: [columna izquierda: info bar + faceplate] [columna derecha: tabla] --}}
        <div class="flex items-stretch gap-4">

            {{-- ── COLUMNA IZQUIERDA: info bar + faceplate ──────────── --}}
            <div class="shrink-0 flex flex-col gap-2">

                {{-- Barra violet (solo stacks) --}}
                @if ($m)
                <div class="bg-violet-50 border border-violet-200 rounded-xl px-4 py-2.5 flex flex-wrap gap-x-7 gap-y-2 text-xs">
                    @foreach ([
                        'IP Address'       => $device['ip']       ?? '—',
                        'MAC Address'      => $m['mac']            ?? '—',
                        'Software Version' => $device['software'] ?? '—',
                        'Model'            => $device['model']    ?? '—',
                        'Serial #'         => $m['serial']         ?? '—',
                        'Make'             => $device['make']     ?? '—',
                    ] as $label => $value)
                        <div class="shrink-0">
                            <b class="block text-violet-800">{{ $label }}:</b>
                            <span class="text-violet-600 font-mono">{{ $value }}</span>
                        </div>
                    @endforeach
                    @if (!empty($m['role']) || !empty($m['state']))
                        <div class="shrink-0 ml-auto self-center text-right">
                            @if (!empty($m['role']))<div class="text-violet-700 font-semibold">{{ $m['role'] }}</div>@endif
                            @if (!empty($m['state']))<div class="text-violet-400">{{ $m['state'] }}</div>@endif
                        </div>
                    @endif
                </div>
                @endif

                {{-- Faceplate card (solo puertos) --}}
                <div class="bg-white border border-gray-200 rounded-xl p-4 pb-5 flex-1">

                    {{-- Título del slot --}}
                    <div class="flex items-center gap-2 mb-3 text-sm">
                        <span class="font-bold text-gray-800">{{ $device['model'] ?? 'Switch' }}</span>
                        @if ($slot['label'])
                            <span class="text-[11px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded px-2 py-0.5">
                                {{ $slot['label'] }}
                            </span>
                        @endif
                    </div>

                    {{-- Ports row: Mgmt/Console + RJ45 + SFP --}}
                    <div class="flex gap-6 min-w-0 overflow-x-auto">

                        {{-- Columna Mgmt / Console --}}
                        <div class="flex flex-col gap-3.5 items-center pt-[18px] shrink-0">
                            <div>
                                <div class="text-[11px] text-gray-500 text-center mb-0.5">Mgmt</div>
                                <div class="sw-jack sw-plain">&nbsp;</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-500 text-center mb-0.5">Console</div>
                                <div class="sw-jack sw-plain sw-flip">&nbsp;</div>
                            </div>
                        </div>

                        {{-- Bloques RJ45 + SFP --}}
                        <div class="flex gap-6 items-start px-0.5 pt-1 pb-2.5">

                            @forelse ($slot['blocks'] as $block)
                                <div class="flex gap-2 shrink-0">
                                    @foreach ($block as $col)
                                        <div class="flex flex-col items-center gap-1 shrink-0">
                                            <div class="sw-num">{{ $col['top']['number'] ?? '' }}</div>
                                            @if ($col['top'])
                                                <x-switch-faceplate-jack :port="$col['top']" />
                                            @else
                                                <div class="w-[34px] h-[32px] shrink-0"></div>
                                            @endif
                                            @if ($col['bottom'])
                                                <x-switch-faceplate-jack :port="$col['bottom']" flip />
                                            @else
                                                <div class="w-[34px] h-[32px] shrink-0"></div>
                                            @endif
                                            <div class="sw-num">{{ $col['bottom']['number'] ?? '' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic self-center">Sin puertos RJ45 en este slot</p>
                            @endforelse

                            {{-- SFP+: pegado a los RJ45 --}}
                            @if (count($slot['sfp']))
                                <div class="shrink-0 border-l border-gray-300 pl-5 flex flex-col">
                                    <span class="inline-block border border-gray-300 rounded text-[10px] text-gray-500 px-2 py-0.5 mb-2 self-start">
                                        10GbE SFP+
                                    </span>
                                    <div class="flex gap-2">
                                        @foreach ($slot['sfp'] as $col)
                                            <div class="flex flex-col items-center gap-1 shrink-0">
                                                <div class="sw-num">{{ $col['top']['number'] ?? '' }}</div>
                                                @if ($col['top'])
                                                    <x-switch-faceplate-jack :port="$col['top']" />
                                                @endif
                                                @if ($col['bottom'])
                                                    <x-switch-faceplate-jack :port="$col['bottom']" flip />
                                                @else
                                                    <div class="w-[34px] h-[32px] shrink-0"></div>
                                                @endif
                                                <div class="sw-num">{{ $col['bottom']['number'] ?? '' }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>{{-- fin columna izquierda --}}

            {{-- ── COLUMNA DERECHA: tabla puertos activos ───────────── --}}
            @if ($activePorts->isNotEmpty())
            <div class="flex-1 min-w-0 bg-white border border-gray-200 rounded-xl p-4 flex flex-col">
                <p class="text-xs font-semibold text-gray-500 mb-3">
                    Puertos Activos
                    <span class="text-gray-300 font-normal ml-1">({{ $activePorts->count() }})</span>
                </p>
                <div data-sw-table class="flex flex-col flex-1 min-h-0">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="text-gray-400 border-b border-gray-100 text-left">
                                <th class="pb-2 font-semibold pr-3">Puerto</th>
                                <th class="pb-2 font-semibold pr-3">Descripción</th>
                                <th class="pb-2 font-semibold pr-3">VLAN</th>
                                <th class="pb-2 font-semibold pr-3">Estado Puerto</th>
                                <th class="pb-2 font-semibold pr-3">Estado Link</th>
                                <th class="pb-2 font-semibold pr-3">Velocidad</th>
                                <th class="pb-2 font-semibold">Dúplex</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($activePorts as $ap)
                            <tr data-port-id="{{ $ap['id'] }}" class="hover:bg-gray-50 transition-colors">
                                <td class="py-1.5 pr-3 font-mono text-gray-700 whitespace-nowrap">{{ $ap['id'] }}</td>
                                <td class="py-1.5 pr-3 text-gray-600">
                                    <span data-desc-cell class="block truncate max-w-[200px]"
                                          title="{{ $ap['description'] }}">
                                        {{ $ap['description'] ?: '—' }}
                                    </span>
                                </td>
                                <td class="py-1.5 pr-3 text-gray-500">{{ $ap['vlan'] ?: '—' }}</td>
                                <td class="py-1.5 pr-3 text-gray-500">E</td>
                                <td class="py-1.5 pr-3 text-gray-500">A</td>
                                <td class="py-1.5 pr-3 text-gray-500 whitespace-nowrap">{{ $ap['speed'] ?: '—' }}</td>
                                <td class="py-1.5 text-gray-500">{{ $ap['duplex'] ?: '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{-- Controles de paginación: el JS los inserta aquí si hay > 8 filas --}}
                </div>
            </div>
            @endif

        </div>{{-- fin wrapper slot --}}
    @endforeach

    {{-- ── LEYENDA + RESUMEN ───────────────────────────────────────── --}}
    <div class="flex justify-between items-center flex-wrap gap-2.5 text-xs text-gray-500 px-1">
        <div class="flex gap-4 flex-wrap items-center">
            @foreach ([
                ['Activo',        '#b5dd8f', '#7fb84e'],
                ['Sin link',      '#fbfbfb', '#b7bbc2'],
                ['Re-asignado',   '#ffd98e', '#e0a83c'],
                ['Deshabilitado', '#f5b5b5', '#d97070'],
            ] as [$label, $bg, $border])
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-[3px]"
                          style="background:{{ $bg }}; border:1px solid {{ $border }}"></span>
                    {{ $label }}
                </span>
            @endforeach
        </div>
        <div>
            @foreach ($counts as $value => $n)
                <b class="text-gray-800">{{ $n }}</b>
                {{ strtolower(PortStatus::from($value)->label()) }}{{ !$loop->last ? ' · ' : '' }}
            @endforeach
        </div>
    </div>

    {{-- ── POPOVER "PORT INFO" ─────────────────────────────────────── --}}
    <div class="sw-popover" data-sw-popover hidden>
        <div class="bg-gray-100 border-b border-gray-200 px-3 py-2 flex justify-between items-center rounded-t">
            <h3 class="text-[13px] font-bold" style="color:#0091ad">Port Info</h3>
            <button type="button" data-sw-close
                    class="text-gray-400 hover:text-gray-700 text-base leading-none px-1" title="Cerrar">&times;</button>
        </div>

        <div class="max-h-[250px] overflow-y-auto py-2">
            <div class="px-6 py-2 text-gray-700">Port Name: <b data-sw-f="name"></b></div>
            <div class="px-6 py-2 text-gray-700">Type: <b data-sw-f="type"></b></div>
            <div class="px-6 py-2 text-gray-700">Port Status: <b data-sw-f="status"></b></div>
            <div class="px-6 py-2 text-gray-700">Access VLAN: <b data-sw-f="vlan"></b></div>

            {{-- Descripción editable: clic en el campo lo habilita y activa "Aceptar" --}}
            <div class="px-6 py-2 text-gray-700">
                <label class="block mb-1.5">Descripción:</label>
                <input type="text" data-sw-desc readonly maxlength="60"
                       title="Clic para editar"
                       class="w-full min-w-0 text-xs border border-gray-200 bg-gray-50 text-gray-500 rounded
                              px-2.5 py-1.5 cursor-pointer transition
                              focus:outline-none focus:ring-1 focus:ring-[#0091ad] focus:border-[#0091ad]">
                <p data-sw-desc-msg class="text-[11px] mt-1.5 hidden"></p>
            </div>

            <div class="px-6 py-2 text-gray-700" data-sw-row="speed">Speed: <b data-sw-f="speed"></b></div>
            <div class="px-6 py-2 text-gray-700" data-sw-row="duplex">Transmission Mode: <b data-sw-f="duplex"></b></div>
        </div>

        <div class="border-t border-gray-200 bg-gray-50 px-6 py-3 rounded-b flex items-center justify-between">
            <div class="text-xs font-bold" style="color:#0091ad">Actions</div>
            <button type="button" data-sw-save disabled
                    class="text-xs font-semibold text-white rounded-full px-4 py-1.5 transition
                           bg-[#0091ad] hover:bg-[#007a91]
                           disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed">
                Aceptar
            </button>
        </div>
    </div>

    {{-- ── MINI-POPUP para puertos no activos (no editables) ──────── --}}
    <div data-sw-minipop hidden
         class="absolute z-40 text-[11px] font-semibold text-white bg-gray-700/95 rounded-md
                px-2.5 py-1.5 shadow-lg pointer-events-none whitespace-nowrap"></div>
</div>

@once
    @push('js')
        <script>
            (function () {
                'use strict';

                const POP_W    = 320;
                const PAGE_SIZE = 6;

                document.querySelectorAll('[data-sw-faceplate]').forEach(initFaceplate);

                // ── Paginación de tabla de puertos activos ──────────────────
                function initTable(tableWrapper) {
                    const tbody = tableWrapper.querySelector('tbody');
                    if (!tbody) return;
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    if (rows.length <= PAGE_SIZE) return; // sin paginación si cabe todo

                    let page = 0;
                    const totalPages = Math.ceil(rows.length / PAGE_SIZE);

                    // Controles de paginación
                    const pager = document.createElement('div');
                    pager.className = 'flex items-center justify-between pt-2 mt-1 border-t border-gray-100 text-[11px] text-gray-400 select-none';
                    pager.innerHTML =
                        '<button data-sw-prev class="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition">‹ Ant</button>' +
                        '<span data-sw-page class="font-mono"></span>' +
                        '<button data-sw-next class="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition">Sig ›</button>';
                    tableWrapper.appendChild(pager);

                    const prevBtn  = pager.querySelector('[data-sw-prev]');
                    const nextBtn  = pager.querySelector('[data-sw-next]');
                    const pageInfo = pager.querySelector('[data-sw-page]');

                    function render() {
                        const start = page * PAGE_SIZE;
                        const end   = start + PAGE_SIZE;
                        rows.forEach(function (row, i) {
                            row.hidden = (i < start || i >= end);
                        });
                        pageInfo.textContent = (page + 1) + ' / ' + totalPages;
                        prevBtn.disabled = page === 0;
                        nextBtn.disabled = page === totalPages - 1;
                    }

                    prevBtn.addEventListener('click', function () { if (page > 0) { page--; render(); } });
                    nextBtn.addEventListener('click', function () { if (page < totalPages - 1) { page++; render(); } });
                    render();
                }

                function initFaceplate(root) {
                    // Inicializar paginación en cada tabla de puertos activos
                    root.querySelectorAll('[data-sw-table]').forEach(initTable);

                    const popover   = root.querySelector('[data-sw-popover]');
                    const minipop   = root.querySelector('[data-sw-minipop]');
                    const descInput = popover.querySelector('[data-sw-desc]');
                    const saveBtn   = popover.querySelector('[data-sw-save]');
                    const descMsg   = popover.querySelector('[data-sw-desc-msg]');
                    const updateUrl = root.dataset.updateUrl;
                    const csrf      = root.dataset.csrf;

                    let selectedJack = null;
                    let miniTimer    = null;

                    // ── Clic en un puerto ──
                    // Solo los puertos ACTIVOS abren el popover editable; el resto
                    // muestra una leyenda breve para evitar documentar donde no se debe.
                    root.querySelectorAll('[data-sw-jack]').forEach(function (jack) {
                        jack.addEventListener('click', function (e) {
                            e.stopPropagation();
                            if (jack.dataset.status === 'active') {
                                hideMini();
                                openPopover(jack);
                            } else {
                                closePopover();
                                showMini(jack);
                            }
                        });
                    });

                    // ── Mini-popup de estado (puertos no editables) ──
                    function showMini(jack) {
                        minipop.textContent = 'Puerto ' + jack.dataset.id + ' — ' +
                            jack.dataset.statusLabel.toLowerCase() + ' · no editable';
                        minipop.hidden = false;

                        const rootRect = root.getBoundingClientRect();
                        const jackRect = jack.getBoundingClientRect();
                        let left = jackRect.left - rootRect.left + jackRect.width / 2 - minipop.offsetWidth / 2;
                        left = Math.max(8, Math.min(left, root.clientWidth - minipop.offsetWidth - 8));
                        minipop.style.left = left + 'px';
                        minipop.style.top  = (jackRect.bottom - rootRect.top + 8) + 'px';

                        clearTimeout(miniTimer);
                        miniTimer = setTimeout(hideMini, 1800);
                    }

                    function hideMini() {
                        clearTimeout(miniTimer);
                        minipop.hidden = true;
                    }

                    function openPopover(jack) {
                        if (selectedJack) selectedJack.classList.remove('sw-selected');
                        selectedJack = jack;
                        jack.classList.add('sw-selected');

                        const d = jack.dataset;
                        setField('name',   d.id);
                        setField('type',   d.type);
                        setField('status', d.statusLabel);
                        setField('vlan',   d.vlan || '—');
                        toggleRow('speed',  d.speed);
                        toggleRow('duplex', d.duplex);

                        resetDescField(d.desc || '');

                        // Posicionar cerca del jack, con clamp a los bordes del contenedor
                        const rootRect = root.getBoundingClientRect();
                        const jackRect = jack.getBoundingClientRect();
                        let left = jackRect.left - rootRect.left + jackRect.width / 2 - POP_W / 2;
                        left = Math.max(8, Math.min(left, root.clientWidth - POP_W - 8));
                        popover.style.left = left + 'px';
                        popover.style.top  = (jackRect.bottom - rootRect.top + 10) + 'px';
                        popover.hidden = false;
                    }

                    function closePopover() {
                        popover.hidden = true;
                        if (selectedJack) {
                            selectedJack.classList.remove('sw-selected');
                            selectedJack = null;
                        }
                    }

                    function setField(name, value) {
                        popover.querySelector('[data-sw-f="' + name + '"]').textContent = value || '—';
                    }

                    function toggleRow(name, value) {
                        const row = popover.querySelector('[data-sw-row="' + name + '"]');
                        row.classList.toggle('hidden', !value);
                        if (value) setField(name, value);
                    }

                    // ── Edición de descripción ──
                    // El input inicia como "solo lectura"; al hacer clic se habilita
                    // junto con el botón Aceptar. Enter también guarda.
                    function resetDescField(value) {
                        descInput.value = value;
                        descInput.dataset.original = value;
                        descInput.readOnly = true;
                        descInput.classList.add('bg-gray-50', 'text-gray-500', 'border-gray-200', 'cursor-pointer');
                        descInput.classList.remove('bg-white', 'text-gray-800', 'border-[#0091ad]');
                        saveBtn.disabled = true;
                        saveBtn.textContent = 'Aceptar';
                        descMsg.classList.add('hidden');
                    }

                    descInput.addEventListener('click', function () {
                        if (!descInput.readOnly) return;
                        if (!updateUrl) {
                            showMsg('Edición no disponible en esta vista.', true);
                            return;
                        }
                        descInput.readOnly = false;
                        descInput.classList.remove('bg-gray-50', 'text-gray-500', 'border-gray-200', 'cursor-pointer');
                        descInput.classList.add('bg-white', 'text-gray-800', 'border-[#0091ad]');
                        descInput.focus();
                        saveBtn.disabled = false;
                    });

                    descInput.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' && !saveBtn.disabled) {
                            e.preventDefault();
                            saveBtn.click();
                        }
                    });

                    saveBtn.addEventListener('click', async function () {
                        if (!selectedJack || !updateUrl) return;

                        const newDesc = descInput.value.trim();
                        if (newDesc === (descInput.dataset.original || '')) {
                            resetDescField(newDesc); // sin cambios → volver a solo lectura
                            return;
                        }

                        saveBtn.disabled = true;
                        saveBtn.textContent = 'Guardando…';

                        try {
                            const res = await fetch(updateUrl, {
                                method: 'PATCH',
                                headers: {
                                    'X-CSRF-TOKEN': csrf,
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    port: selectedJack.dataset.id,
                                    display_string: newDesc,
                                }),
                            });
                            let json = null;
                            try { json = await res.json(); } catch (e) { /* respuesta no JSON */ }
                            if (!res.ok || !json || !json.ok) {
                                throw new Error((json && (json.error || json.message)) ||
                                    'Error al guardar (HTTP ' + res.status + ')');
                            }

                            selectedJack.dataset.desc = newDesc;
                            resetDescField(newDesc);
                            showMsg('Descripción guardada.', false);

                            // ── Actualizar celda de descripción en la tabla sin recargar ──
                            const portId  = selectedJack.dataset.id;
                            const tableRow = root.querySelector('[data-port-id="' + portId + '"]');
                            if (tableRow) {
                                const cell = tableRow.querySelector('[data-desc-cell]');
                                if (cell) {
                                    cell.textContent = newDesc || '—';
                                    cell.title       = newDesc || '';
                                }
                            }
                        } catch (err) {
                            saveBtn.disabled = false;
                            saveBtn.textContent = 'Aceptar';
                            showMsg(err.message || 'Error al guardar.', true);
                        }
                    });

                    function showMsg(text, isError) {
                        descMsg.textContent = text;
                        descMsg.classList.remove('hidden', 'text-red-500', 'text-emerald-600');
                        descMsg.classList.add(isError ? 'text-red-500' : 'text-emerald-600');
                    }

                    // ── Cierres: X, Escape, clic fuera ──
                    popover.querySelector('[data-sw-close]').addEventListener('click', closePopover);
                    popover.addEventListener('click', function (e) { e.stopPropagation(); });
                    document.addEventListener('click', function (e) {
                        if (!popover.hidden && !popover.contains(e.target)) closePopover();
                        hideMini();
                    });
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') { closePopover(); hideMini(); }
                    });
                }
            })();
        </script>
    @endpush
@endonce
