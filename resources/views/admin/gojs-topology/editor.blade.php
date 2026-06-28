<x-admin-layout
    title="{{ $topologyName }} | Editor GoJS"
    :breadcrumbs="[
        ['name' => 'Dashboard',   'href' => route('dashboard')],
        ['name' => 'Topología',   'href' => route('admin.topology.index')],
        ['name' => 'Editor GoJS', 'href' => route('admin.topology.gojs.create')],
        ['name' => $topologyName],
    ]">

    {{-- ── Barra de acciones ───────────────────────────────────────────────────── --}}
    <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-2 flex-wrap">
        <h2 class="text-sm font-semibold text-gray-700 mr-2 truncate max-w-xs" title="{{ $topologyName }}">
            {{ $topologyName }}
        </h2>
        <div class="flex items-center gap-2 ml-auto flex-wrap">
            <a href="{{ route('admin.topology.gojs.create') }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                       bg-gray-100 hover:bg-gray-200 text-gray-600 transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar selección
            </a>
            <button id="btn-fit" type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                       bg-gray-100 hover:bg-gray-200 text-gray-600 transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
                Ajustar vista
            </button>
            <button id="btn-layout" type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                       bg-gray-100 hover:bg-gray-200 text-gray-600 transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Organizar
            </button>
            <button id="btn-export" type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                       bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exportar PNG
            </button>
            <button id="btn-view-img" type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                       bg-gray-100 hover:bg-gray-200 text-gray-600 transition
                       {{ $hasImage ? '' : 'opacity-50 cursor-not-allowed' }}"
                {{ $hasImage ? '' : 'disabled' }}>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Ver imagen
            </button>
            <a id="btn-download" href="#"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                       bg-gray-100 hover:bg-gray-200 text-gray-600 transition
                       {{ $hasImage ? '' : 'opacity-50 pointer-events-none' }}"
                download="{{ Str::slug($topologyName) }}.png">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Descargar
            </a>
        </div>
    </div>

    {{-- ── Layout principal ─────────────────────────────────────────────────────── --}}
    <div class="flex" style="height: calc(100vh - 210px); min-height: 500px;">

        {{-- Paleta izquierda --}}
        <div class="bg-gray-50 border-r border-gray-200 flex flex-col" style="width:160px; flex-shrink:0;">
            <div class="px-3 py-2 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                Dispositivos
            </div>
            <div id="gojs-palette" style="flex:1; min-height:0;"></div>
            <div class="px-3 py-2 border-t border-gray-200 text-xs text-gray-400 leading-snug">
                Arrastra a la derecha para agregar
            </div>
        </div>

        {{-- Canvas GoJS --}}
        <div id="gojs-diagram" class="flex-1 bg-white" style="min-width:0;"></div>
    </div>

    {{-- Barra de atajos --}}
    <div class="bg-gray-50 border-t border-gray-200 px-4 py-1.5 text-xs text-gray-400 flex items-center gap-4 flex-wrap">
        <span>
            <kbd class="bg-white border border-gray-300 rounded px-1 py-0.5 text-gray-500">Hover nodo</kbd>
            aparecen ● → arrastra a otro nodo para enlazar
        </span>
        <span><kbd class="bg-white border border-gray-300 rounded px-1 py-0.5 text-gray-500">Clic derecho</kbd> editar IP / Modelo</span>
        <span><kbd class="bg-white border border-gray-300 rounded px-1 py-0.5 text-gray-500">2× clic en línea</kbd> etiqueta de puerto</span>
        <span><kbd class="bg-white border border-gray-300 rounded px-1 py-0.5 text-gray-500">2× clic en nodo</kbd> editar nombre</span>
        <span><kbd class="bg-white border border-gray-300 rounded px-1 py-0.5 text-gray-500">Ctrl+G</kbd> agrupar</span>
        <span><kbd class="bg-white border border-gray-300 rounded px-1 py-0.5 text-gray-500">Supr</kbd> eliminar</span>
        <span><kbd class="bg-white border border-gray-300 rounded px-1 py-0.5 text-gray-500">Ctrl+Z/Y</kbd> deshacer/rehacer</span>
        <span class="ml-auto text-gray-300">GoJS 2.3</span>
    </div>

    {{-- Overlay exportar --}}
    <div id="export-overlay"
        class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl px-8 py-6 flex flex-col items-center gap-3">
            <svg class="w-8 h-8 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <p class="text-sm font-medium text-gray-700">Generando PNG…</p>
        </div>
    </div>

    {{-- Panel propiedades del nodo seleccionado --}}
    <div id="node-props-panel"
        class="hidden fixed bottom-6 left-64 z-40 bg-white rounded-xl shadow-xl border border-gray-200 p-4 w-72">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Propiedades</span>
            <button id="props-close" type="button" class="text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nombre / hostname</label>
                <input id="prop-name" type="text"
                    class="w-full text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">IP de gestión</label>
                <input id="prop-ip" type="text" placeholder="192.168.1.1"
                    class="w-full text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Modelo</label>
                <input id="prop-model" type="text" placeholder="Ej: WS-C2960X-48FPD-L"
                    class="w-full text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button id="props-apply"
                class="flex-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition">
                Aplicar
            </button>
            <button id="props-close2"
                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-medium rounded-lg transition">
                Cerrar
            </button>
        </div>
    </div>

    {{-- Modal imagen --}}
    <div id="img-modal"
        class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
        role="dialog" aria-modal="true">
        <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <span class="font-semibold text-gray-700 text-sm">{{ $topologyName }}</span>
                <button id="modal-close" type="button" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="overflow-auto flex-1 p-4 flex items-center justify-center bg-gray-50">
                <img id="modal-img" src="" alt="Topología" class="max-w-full max-h-full rounded-lg shadow">
            </div>
        </div>
    </div>

    @push('js')
    {{-- GoJS 2.3 --}}
    <script src="https://cdn.jsdelivr.net/npm/gojs@2.3/release/go.js"></script>
    <script>
    // ── Datos del servidor ────────────────────────────────────────────────────────
    const NODES_DATA  = @json($nodesData);
    const LINKS_DATA  = @json($linksData);
    const EXPORT_URL  = "{{ route('admin.topology.gojs.export') }}";
    const IMAGE_URL   = "{{ $hasImage ? route('admin.topology.gojs.image', $pngKey) : '' }}";
    const PNG_NAME    = "{{ Str::slug($topologyName) }}.png";
    const CSRF_TOKEN  = "{{ csrf_token() }}";

    // ── Iconos disponibles ────────────────────────────────────────────────────────
    // Para agregar un nuevo tipo de dispositivo:
    //   1. Copia el archivo PNG en:  laravel/scripts/icons/mi_icono.png
    //   2. Agrega una entrada aquí con la clave que usarás como "category":
    //        miClave: "{{ route('admin.topology.icon', 'mi_icono.png') }}",
    //   3. Agrega ese ítem en el array de la paleta (myPalette.model, más abajo):
    //        { key: '_miClave', text: 'Mi dispositivo', category: 'miClave' },
    // ─────────────────────────────────────────────────────────────────────────────
    const ICON = {
        core:     "{{ route('admin.topology.icon', 'core_switch.png') }}",
        backbone: "{{ route('admin.topology.icon', 'backbone_switch.png') }}",
        dist:     "{{ route('admin.topology.icon', 'dist_switch.png') }}",
        access:   "{{ route('admin.topology.icon', 'access_switch.png') }}",
        stack:    "{{ route('admin.topology.icon', 'stack_switch.png') }}",
        // ── Agrega más iconos aquí ──────────────────────────────────────────────
        // router:   "{{ route('admin.topology.icon', 'router.png') }}",
        // firewall: "{{ route('admin.topology.icon', 'firewall.png') }}",
        // ───────────────────────────────────────────────────────────────────────
        default:  "{{ route('admin.topology.icon', 'access_switch.png') }}",
    };

    // ── GoJS setup ────────────────────────────────────────────────────────────────
    const go  = window.go;
    const $   = go.GraphObject.make;

    // ── Puerto: punto de conexión con portId explícito ───────────────────────────
    // CRÍTICO: portId es obligatorio para que node.ports lo reconozca como puerto
    // y no confunda el nodo completo con un puerto.
    function makePort(pid, spot) {
        return $(go.Shape, 'Circle', {
            portId: pid,                        // 'T' | 'B' | 'L' | 'R'
            alignment: spot,
            alignmentFocus: spot,
            desiredSize: new go.Size(9, 9),
            fill: '#6366F1',
            stroke: 'white',
            strokeWidth: 1.5,
            opacity: 0,                         // oculto por defecto
            fromLinkable: true,
            toLinkable: true,
            fromLinkableSelfNode: false,
            toLinkableSelfNode: false,
            cursor: 'crosshair',
        });
    }

    function makeNodeTemplate() {
        return $(go.Node, 'Spot',
            {
                selectionAdorned: true,
                mouseEnter: (e, node) => {
                    node.ports.each(p => { if (p.portId !== '') p.opacity = 1; });
                },
                mouseLeave: (e, node) => {
                    node.ports.each(p => { if (p.portId !== '') p.opacity = 0; });
                },
            },
            // ── Panel principal (icono + nombre + IP + Modelo) ────────────────────
            $(go.Panel, 'Vertical',
                // Icono
                $(go.Picture,
                    {
                        name: 'IMG',
                        width: 48, height: 48,
                        margin: new go.Margin(4, 4, 2, 4),
                        imageStretch: go.GraphObject.Uniform,
                    },
                    new go.Binding('source', 'category', cat => ICON[cat] || ICON.default)
                ),
                // Nombre (doble clic para editar)
                $(go.TextBlock,
                    {
                        font: 'bold 11px sans-serif',
                        stroke: '#1E293B',
                        maxSize: new go.Size(120, NaN),
                        wrap: go.TextBlock.WrapFit,
                        textAlign: 'center',
                        editable: true,
                        margin: new go.Margin(0, 2, 1, 2),
                    },
                    new go.Binding('text', 'text').makeTwoWay()
                ),
                // IP (doble clic para editar; oculto en paleta donde no hay IP)
                $(go.TextBlock,
                    {
                        font: '9px sans-serif',
                        stroke: '#6B7280',
                        textAlign: 'center',
                        editable: true,
                        isMultiline: false,
                        maxSize: new go.Size(120, NaN),
                        margin: new go.Margin(0, 2, 0, 2),
                    },
                    new go.Binding('text', 'ip', v => v ? '⬡ ' + v : '').makeTwoWay(
                        v => v.replace(/^⬡\s*/, '')
                    ),
                    new go.Binding('visible', 'ip', v => !!v)
                ),
                // Modelo (doble clic para editar; oculto en paleta donde no hay modelo)
                $(go.TextBlock,
                    {
                        font: '9px sans-serif',
                        stroke: '#9CA3AF',
                        textAlign: 'center',
                        editable: true,
                        isMultiline: false,
                        maxSize: new go.Size(120, NaN),
                        margin: new go.Margin(0, 2, 4, 2),
                    },
                    new go.Binding('text', 'model', v => v || '').makeTwoWay(),
                    new go.Binding('visible', 'model', v => !!v)
                )
            ),
            // ── Puertos ──────────────────────────────────────────────────────────
            makePort('T', go.Spot.Top),
            makePort('B', go.Spot.Bottom),
            makePort('L', go.Spot.Left),
            makePort('R', go.Spot.Right)
        );
    }

    // ── Link template ─────────────────────────────────────────────────────────────
    function makeLinkTemplate() {
        return $(go.Link,
            {
                routing: go.Link.AvoidsNodes,
                corner: 8,
                curve: go.Link.JumpOver,
                relinkableFrom: true,
                relinkableTo: true,
                reshapable: true,
                toShortLength: 4,
                // Doble clic en la línea → editar etiqueta directamente
                doubleClick: (e, link) => {
                    const tb = link.findObject('LINK_LABEL');
                    if (tb) myDiagram.commandHandler.editTextBlock(tb);
                },
            },
            $(go.Shape, { strokeWidth: 1.5, stroke: '#64748B' }),
            // Etiqueta del enlace (puerto). Doble clic en la línea para escribir.
            $(go.TextBlock,
                {
                    name: 'LINK_LABEL',
                    font: '10px sans-serif',
                    stroke: '#475569',
                    background: 'rgba(255,255,255,0.9)',
                    segmentOffset: new go.Point(0, -11),
                    editable: true,
                    text: '',           // vacío por defecto; se muestra al escribir
                },
                new go.Binding('text', 'text').makeTwoWay(),
                new go.Binding('visible', 'text', t => t.trim() !== '')
            )
        );
    }

    // ── Group template ─────────────────────────────────────────────────────────────
    function makeGroupTemplate() {
        return $(go.Group, 'Auto',
            {
                layout: $(go.GridLayout, { wrappingColumn: 4, cellSize: new go.Size(1, 1), spacing: new go.Size(10, 10) }),
                isSubGraphExpanded: true,
                ungroupable: true,
            },
            $(go.Shape, 'Rectangle',
                { fill: 'rgba(99,102,241,0.06)', stroke: '#6366F1', strokeWidth: 1.5, strokeDashArray: [6, 3] }
            ),
            $(go.Panel, 'Vertical',
                { margin: 8 },
                $(go.TextBlock,
                    {
                        alignment: go.Spot.Left,
                        font: 'bold 11px sans-serif',
                        stroke: '#4F46E5',
                        editable: true,
                        margin: new go.Margin(0, 0, 6, 0),
                    },
                    new go.Binding('text', 'text').makeTwoWay()
                ),
                $(go.Placeholder, { padding: 8 })
            )
        );
    }

    // ── Diagram ───────────────────────────────────────────────────────────────────
    const myDiagram = $(go.Diagram, 'gojs-diagram',
        {
            'undoManager.isEnabled': true,
            layout: $(go.ForceDirectedLayout, {
                defaultSpringLength: 160,
                defaultElectricalCharge: 250,
                defaultGravitationalMass: 0,
                isOngoing: false,
            }),
            'animationManager.isEnabled': true,
            allowDrop: true,
            'toolManager.mouseWheelBehavior': go.ToolManager.WheelZoom,
        }
    );

    // Habilitar Ctrl+G para agrupar (debe setearse fuera del constructor en GoJS 2.3)
    myDiagram.commandHandler.archetypeGroupData = { isGroup: true, text: 'Grupo' };

    myDiagram.nodeTemplate = makeNodeTemplate();
    myDiagram.linkTemplate = makeLinkTemplate();
    myDiagram.groupTemplate = makeGroupTemplate();

    myDiagram.model = $(go.GraphLinksModel,
        {
            nodeKeyProperty: 'key',
            linkKeyProperty: 'key',
            nodeDataArray: NODES_DATA.map(n => Object.assign({}, n)),
            linkDataArray: LINKS_DATA.map(l => Object.assign({}, l)),
        }
    );

    // ── Palette ───────────────────────────────────────────────────────────────────
    const myPalette = $(go.Palette, 'gojs-palette',
        {
            nodeTemplateMap: myDiagram.nodeTemplateMap,
            layout: $(go.GridLayout, {
                wrappingColumn: 1,
                cellSize: new go.Size(1, 1),
                spacing: new go.Size(4, 8),
            }),
            contentAlignment: go.Spot.TopCenter,
        }
    );

    // ── Ítems de la paleta ────────────────────────────────────────────────────────
    // Para agregar un nuevo dispositivo a la paleta:
    //   { key: '_miClave', text: 'Nombre visible', category: 'miClave' }
    // La "category" debe coincidir con la clave del objeto ICON de arriba.
    // ─────────────────────────────────────────────────────────────────────────────
    myPalette.model = $(go.GraphLinksModel, {
        nodeDataArray: [
            { key: '_core',     text: 'Core',         category: 'core'     },
            { key: '_backbone', text: 'Backbone',      category: 'backbone' },
            { key: '_dist',     text: 'Distribución',  category: 'dist'     },
            { key: '_access',   text: 'Acceso',        category: 'access'   },
            { key: '_stack',    text: 'Stack',         category: 'stack'    },
            // { key: '_router',   text: 'Router',        category: 'router'   },
        ],
    });

    // ── Clic derecho en el canvas → abrir panel de propiedades ───────────────────
    // Se usa el evento nativo en vez del contextMenu de GoJS para mayor fiabilidad.
    document.getElementById('gojs-diagram').addEventListener('contextmenu', e => {
        e.preventDefault();
        const rect = document.getElementById('gojs-diagram').getBoundingClientRect();
        const viewPt = new go.Point(e.clientX - rect.left, e.clientY - rect.top);
        const docPt  = myDiagram.transformViewToDoc(viewPt);
        const part   = myDiagram.findPartAt(docPt, true);
        if (part instanceof go.Node && !part.isGroup) {
            openPropsPanel(part.data);
        }
    });

    // ── Panel de propiedades (abre con clic derecho → "Editar IP / Modelo") ────────
    let _propsNodeData = null;

    function openPropsPanel(data) {
        _propsNodeData = data;
        document.getElementById('prop-name').value  = data.text  || '';
        document.getElementById('prop-ip').value    = data.ip    || '';
        document.getElementById('prop-model').value = data.model || '';
        document.getElementById('node-props-panel').classList.remove('hidden');
        document.getElementById('prop-name').focus();
    }

    function closePropsPanel() {
        document.getElementById('node-props-panel').classList.add('hidden');
        _propsNodeData = null;
    }

    document.getElementById('props-apply').addEventListener('click', () => {
        if (!_propsNodeData) return;
        myDiagram.startTransaction('edit props');
        myDiagram.model.setDataProperty(_propsNodeData, 'text',  document.getElementById('prop-name').value.trim());
        myDiagram.model.setDataProperty(_propsNodeData, 'ip',    document.getElementById('prop-ip').value.trim());
        myDiagram.model.setDataProperty(_propsNodeData, 'model', document.getElementById('prop-model').value.trim());
        myDiagram.commitTransaction('edit props');
        closePropsPanel();
    });

    document.getElementById('props-close').addEventListener('click',  closePropsPanel);
    document.getElementById('props-close2').addEventListener('click', closePropsPanel);

    // ── Botones ───────────────────────────────────────────────────────────────────
    document.getElementById('btn-fit').addEventListener('click', () => {
        myDiagram.commandHandler.scrollToPart(null);
        myDiagram.zoomToFit();
    });

    document.getElementById('btn-layout').addEventListener('click', () => {
        myDiagram.layoutDiagram(true);
    });

    // ── Export PNG ────────────────────────────────────────────────────────────────
    let currentImageUrl = IMAGE_URL;

    document.getElementById('btn-export').addEventListener('click', async () => {
        const overlay = document.getElementById('export-overlay');
        overlay.classList.remove('hidden');

        try {
            const imgData = myDiagram.makeImageData({ background: 'white', scale: 2, type: 'image/png' });

            const res = await fetch(EXPORT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ image: imgData }),
            });

            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error desconocido');

            currentImageUrl = data.url;

            // Habilitar botones Ver imagen / Descargar
            const btnView = document.getElementById('btn-view-img');
            btnView.disabled = false;
            btnView.classList.remove('opacity-50', 'cursor-not-allowed');

            const btnDl = document.getElementById('btn-download');
            btnDl.href = currentImageUrl;
            btnDl.classList.remove('opacity-50', 'pointer-events-none');

            overlay.classList.add('hidden');
            showToast('PNG exportado correctamente ✓', 'success');
        } catch (err) {
            overlay.classList.add('hidden');
            showToast('Error al exportar: ' + err.message, 'error');
        }
    });

    // ── Ver imagen ────────────────────────────────────────────────────────────────
    document.getElementById('btn-view-img').addEventListener('click', () => {
        if (!currentImageUrl) return;
        document.getElementById('modal-img').src = currentImageUrl + '?t=' + Date.now();
        document.getElementById('img-modal').classList.remove('hidden');
    });

    document.getElementById('modal-close').addEventListener('click', () => {
        document.getElementById('img-modal').classList.add('hidden');
    });

    document.getElementById('img-modal').addEventListener('click', e => {
        if (e.target === document.getElementById('img-modal')) {
            document.getElementById('img-modal').classList.add('hidden');
        }
    });

    // ── Download ──────────────────────────────────────────────────────────────────
    if (currentImageUrl) {
        document.getElementById('btn-download').href = currentImageUrl;
    }

    // ── Toast ─────────────────────────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const el = document.createElement('div');
        el.textContent = msg;
        el.className = [
            'fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-lg text-sm font-medium shadow-lg transition-all duration-300',
            type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white',
        ].join(' ');
        document.body.appendChild(el);
        setTimeout(() => el.style.opacity = '0', 3000);
        setTimeout(() => el.remove(), 3400);
    }
    </script>
    @endpush

</x-admin-layout>
