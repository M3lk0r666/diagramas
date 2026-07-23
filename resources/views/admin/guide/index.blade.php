<x-admin-layout title="Guía de Archivos | Diagramas" :breadcrumbs="[['name' => 'Dashboard', 'href' => route('admin.dashboard')], ['name' => 'Guía de Archivos']]">

    @php
        $sections = [
            'show configuration' => 'Configuración completa del switch (running-config)',
            'show version detail' => 'Versión de firmware, modelo y número de serie',
            'show switch detail' => 'Detalles del hardware: puertos, tipo de chasis, uptime',
            'show vlan' => 'Lista de VLANs, IDs, IPs, flags y puertos asociados',
            'show iproute' => 'Tabla de rutas IP estáticas y dinámicas',
            'show edp ports all' => 'Vecinos EDP detectados por puerto (topología)',
            'show ports no-refresh' => 'Estado de puertos: velocidad, link, duplex, contadores',
            'show stacking' => 'Estado del stack: roles, slots, modelo por unidad',
        ];
    @endphp

    {{-- ── HEADER ─────────────────────────────────────────────────────── --}}
    <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5 flex items-start gap-4">
        <div class="shrink-0 w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center text-white text-xl">
            <i class="ri-file-list-3-line"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-blue-900">Guía de Archivos de Configuración</h1>
            <p class="mt-1 text-sm text-blue-700">
                Antes de subir un archivo al sistema, este debe tener una estructura específica de secciones.
                Aquí encontrarás la plantilla, la herramienta de backup automatizado y cómo preparar manualmente el
                archivo.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- ── COLUMNA IZQUEIRDA: Imagen + Descargas ───────────────────────── --}}
        <div class="space-y-5">

            {{-- Imagen de guía --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                    <i class="ri-image-line text-gray-500"></i>
                    <span class="text-sm font-semibold text-gray-700">Vista de referencia del archivo</span>
                </div>
                <div class="p-4">
                    @if (file_exists(public_path('img/guia-backup.png')))
                        <img src="{{ asset('img/guia-backup.png') }}" alt="Guía de estructura de archivo"
                            class="w-full rounded-lg border border-gray-200 shadow-sm cursor-zoom-in"
                            onclick="document.getElementById('img-modal').classList.remove('hidden')">
                    @else
                        <div
                            class="flex flex-col items-center justify-center py-10 text-center border-2 border-dashed border-gray-200 rounded-lg bg-gray-50">
                            <i class="ri-image-add-line text-4xl text-gray-300 mb-3"></i>
                            <p class="text-sm text-gray-500 font-medium">Imagen de guía no disponible</p>
                            <p class="text-xs text-gray-400 mt-1">
                                Coloca el archivo <code class="bg-gray-100 px-1 rounded">guia-backup.png</code>
                                en <code class="bg-gray-100 px-1 rounded">public/img/</code>
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Herramienta de backup --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                    <i class="ri-tools-line text-gray-500"></i>
                    <span class="text-sm font-semibold text-gray-700">Herramienta de Backup Automatizado</span>
                </div>
                <div class="p-5 space-y-4">
                    <p class="text-sm text-gray-600">
                        La <strong>Multi-Vendor Backup Tool</strong> se conecta al switch vía SSH/Telnet,
                        ejecuta los comandos necesarios y genera automáticamente el archivo
                        con la estructura correcta, listo para subirse al sistema.
                    </p>

                    <div
                        class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800 flex gap-2">
                        <i class="ri-information-line text-base shrink-0 mt-0.5"></i>
                        <span>Compatible con switches <strong>Extreme Networks</strong> (EXOS). El archivo generado ya
                            incluye el encabezado y todas las secciones en el orden correcto.</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- Descargar .exe --}}
                        @if (file_exists(public_path('downloads/backup-tool.exe')))
                            <a href="{{ asset('downloads/backup-tool.exe') }}" download
                                class="flex items-center gap-3 p-4 rounded-xl border-2 border-blue-200 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 transition group">
                                <div
                                    class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center text-white shrink-0">
                                    <i class="ri-windows-line text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-blue-800 group-hover:text-blue-900">Descargar
                                        .exe</div>
                                    <div class="text-xs text-blue-500">Ejecutable Windows</div>
                                </div>
                                <i class="ri-download-line ml-auto text-blue-400 group-hover:text-blue-600"></i>
                            </a>
                        @else
                            <div
                                class="flex items-center gap-3 p-4 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 opacity-60 cursor-not-allowed">
                                <div
                                    class="w-10 h-10 rounded-lg bg-gray-300 flex items-center justify-center text-white shrink-0">
                                    <i class="ri-windows-line text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-500">Descargar .exe</div>
                                    <div class="text-xs text-gray-400">No disponible aún</div>
                                </div>
                            </div>
                        @endif

                        {{-- Descargar .py --}}
                        @if (file_exists(public_path('downloads/backup-tool.py')))
                            <a href="{{ asset('downloads/backup-tool.py') }}" download
                                class="flex items-center gap-3 p-4 rounded-xl border-2 border-emerald-200 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-400 transition group">
                                <div
                                    class="w-10 h-10 rounded-lg bg-emerald-600 flex items-center justify-center text-white shrink-0">
                                    <i class="ri-code-s-slash-line text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-emerald-800 group-hover:text-emerald-900">
                                        Descargar fuente .py</div>
                                    <div class="text-xs text-emerald-500">Python 3 — código fuente</div>
                                </div>
                                <i class="ri-download-line ml-auto text-emerald-400 group-hover:text-emerald-600"></i>
                            </a>
                        @else
                            <div
                                class="flex items-center gap-3 p-4 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 opacity-60 cursor-not-allowed">
                                <div
                                    class="w-10 h-10 rounded-lg bg-gray-300 flex items-center justify-center text-white shrink-0">
                                    <i class="ri-code-s-slash-line text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-500">Descargar fuente .py</div>
                                    <div class="text-xs text-gray-400">No disponible aún</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Instrucciones para agregar archivos --}}
                    <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-xs text-gray-500">
                        <p class="font-semibold text-gray-600 mb-1">Para activar las descargas, coloca los archivos en
                            el servidor:</p>
                        <code class="block">public/downloads/backup-tool.exe</code>
                        <code class="block">public/downloads/backup-tool.py</code>
                    </div>
                </div>
            </div>

            {{-- Flujo recomendado --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <i class="ri-route-line text-gray-500"></i>
                    <span class="text-sm font-semibold text-gray-700">Flujo recomendado</span>
                </div>
                <ol class="space-y-3">
                    @foreach ([['ri-download-2-line text-blue-500', 'Descarga la herramienta de backup'], ['ri-terminal-line text-purple-500', 'Ejecuta el backup contra el switch (requiere IP + credenciales)'], ['ri-file-check-line text-emerald-500', 'Obtén el .txt generado con la estructura correcta'], ['ri-upload-2-line text-orange-500', 'Súbelo desde <strong>Subir Archivos</strong>'], ['ri-cpu-line text-gray-500', 'El sistema lo procesa y genera diagramas automáticamente']] as [$icon, $text])
                        <li class="flex items-start gap-3 text-sm text-gray-600">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center">
                                <i class="{{ $icon }} text-xs"></i>
                            </span>
                            <span class="pt-0.5">{!! $text !!}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

        </div>

        {{-- ── COLUMNA DERECHA: Plantilla + Secciones ──────────────────── --}}
        <div class="space-y-5">

            {{-- Plantilla copiable --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-gray-50">
                    <div class="flex items-center gap-2">
                        <i class="ri-code-box-line text-gray-500"></i>
                        <span class="text-sm font-semibold text-gray-700">Plantilla de archivo (.txt)</span>
                    </div>
                    <button id="btn-copy-template" onclick="copyTemplate()"
                        class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition font-medium">
                        <i class="ri-clipboard-line"></i>
                        <span id="copy-label">Copiar plantilla</span>
                    </button>
                </div>
                <pre id="template-content"
                    class="text-bae font-mono text-gray-100 bg-purple-950 p-5 overflow-x-auto leading-relaxed whitespace-pre">
! ================================================================
!   Backup de configuración
!   Vendor      : Extreme
!   Host        : &lt;direccion_ip_switch&gt;
!   Hostname    : &lt;nombre_del_host&gt;
!   Fecha       : &lt;YYYY-MM-DD HH:MM:SS&gt;
!   Generado    : Multi-Vendor Backup Tool v1.5.1
! ================================================================

================================================================
! show configuration
================================================================

&lt;Pegar aquí la salida de: show configuration&gt;

================================================================
! show version detail
================================================================

&lt;Pegar aquí la salida de: show version detail&gt;

================================================================
! show switch detail
================================================================

&lt;Pegar aquí la salida de: show switch detail&gt;

================================================================
! show vlan
================================================================

&lt;Pegar aquí la salida de: show vlan&gt;

================================================================
! show iproute
================================================================

&lt;Pegar aquí la salida de: show iproute&gt;

================================================================
! show edp ports all
================================================================

&lt;Pegar aquí la salida de: show edp ports all&gt;

================================================================
! show ports no-refresh
================================================================

&lt;Pegar aquí la salida de: show ports no-refresh&gt;

================================================================
! show stacking
================================================================

&lt;Pegar aquí la salida de: show stacking&gt;
                </pre>
            </div>

            {{-- Tabla de secciones --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                    <i class="ri-list-check-2 text-gray-500"></i>
                    <span class="text-sm font-semibold text-gray-700">Descripción de secciones</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Comando / Sección</th>
                            <th class="px-5 py-2.5 text-left">Contenido esperado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($sections as $cmd => $desc)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-5 py-2.5 font-mono text-xs text-blue-700 whitespace-nowrap">
                                    {{ $cmd }}</td>
                                <td class="px-5 py-2.5 text-xs text-gray-600">{{ $desc }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>


    </div>

    {{-- Modal de imagen a pantalla completa --}}
    <div id="img-modal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
        onclick="this.classList.add('hidden')">
        <img src="{{ asset('img/guia-backup.png') }}" alt="Guía ampliada"
            class="max-h-full max-w-full rounded-lg shadow-2xl">
        <button class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300"
            onclick="document.getElementById('img-modal').classList.add('hidden')">&times;</button>
    </div>

    @push('js')
        <script>
            function copyTemplate() {
                const pre = document.getElementById('template-content');
                const text = pre.innerText;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text)
                        .then(showCopied)
                        .catch(() => fallbackCopy(text));
                } else {
                    fallbackCopy(text);
                }
            }

            function fallbackCopy(text) {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try {
                    document.execCommand('copy');
                    showCopied();
                } catch {
                    alert('No se pudo copiar automáticamente. Selecciona el texto manualmente.');
                }
                document.body.removeChild(ta);
            }

            function showCopied() {
                const label = document.getElementById('copy-label');
                label.textContent = '¡Copiado!';
                setTimeout(() => {
                    label.textContent = 'Copiar plantilla';
                }, 2000);
            }
        </script>
    @endpush

</x-admin-layout>
