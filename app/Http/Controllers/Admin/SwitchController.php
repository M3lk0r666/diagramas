<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Switche;
use App\Models\UploadBatch;
use App\Services\SwitchFaceplateService;
use App\Services\TopologyBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SwitchController extends Controller
{
    // GET /switches → listado
    public function index(Request $request)
    {
        $clientId = $request->query('client');
        $batchId  = $request->query('batch');
        $search   = trim($request->query('search', ''));

        $switches = Switche::with('batch.client')
                          ->where('parse_status', 'ok')
                          ->when($clientId, fn($q) => $q->whereHas('batch', fn($b) => $b->where('client_id', $clientId)))
                          ->when($batchId,  fn($q) => $q->where('upload_batch_id', $batchId))
                          ->when($search,   fn($q) => $q->where(fn($w) =>
                              $w->where('sys_name',      'like', "%{$search}%")
                                ->orWhere('management_ip', 'like', "%{$search}%")
                                ->orWhere('system_type',   'like', "%{$search}%")
                          ))
                          ->latest()
                          ->paginate(20)
                          ->withQueryString();

        $clients = Client::orderBy('name')->get(['id', 'name']);
        $batches = UploadBatch::when($clientId, fn($q) => $q->where('client_id', $clientId))
                              ->orderBy('name')->get(['id', 'name', 'client_id']);

        return view('admin.switches.index', compact('switches', 'clients', 'batches', 'clientId', 'batchId', 'search'));
    }

    // GET /switches/{switch} → detalle con todas las secciones
    public function show(Switche $switch, SwitchFaceplateService $faceplate)
    {
        $switch->load('batch', 'outgoingConnections.dstSwitch', 'incomingConnections.srcSwitch');

        // Datos para <x-switch-faceplate> (todos los puertos con su estado real)
        $faceplateDevice = $faceplate->device($switch);
        $faceplatePorts  = $faceplate->ports($switch);

        return view('admin.switches.show', compact('switch', 'faceplateDevice', 'faceplatePorts'));
    }

    // GET /switches/{switch}/ports-diagram → diagrama de puertos activos
    public function portsDiagram(Switche $switch)
    {
        $activePorts = $switch->active_ports ?? [];
        $isStacked   = $switch->is_stacked && !empty($switch->stack_members);

        // Detectar rol e icono del switch central
        $role = TopologyBuilderService::detectRoleStatic($switch->sys_name ?? '');
        $iconFileMap = [
            'core'         => 'core_switch.png',
            'backbone'     => 'backbone_switch.png',
            'distribution' => 'dist_switch.png',
            'access'       => 'access_switch.png',
        ];
        $centerIconUrl = route('admin.topology.icon',
            $isStacked ? 'stack_switch.png' : ($iconFileMap[$role] ?? 'access_switch.png'));

        // Mapa de nombre de switch conocido → [icon_url, ip, model]
        // (para mostrar icono correcto en nodos vecinos documentados)
        $knownSwitches = Switche::where('parse_status', 'ok')
            ->get(['sys_name', 'system_type', 'management_ip', 'is_stacked'])
            ->keyBy('sys_name');

        // Helper: determina el icono de dispositivo según palabras clave en display_string.
        // Para vecinos no registrados en BD (endpoints, servidores, APs, etc.)
        $deviceIcon = function (string $desc): string {
            $d = strtolower($desc);
            if (str_contains($d, 'router'))                                                    return 'router.png';
            if (str_contains($d, 'firewall'))                                                  return 'firewall.png';
            if (str_contains($d, 'wireless_controler') || str_contains($d, 'wireless'))        return 'wireless_controler.png';
            if (str_contains($d, 'acces_point') || str_contains($d, 'access_point') || preg_match('/\bap\b/', $d))
                                                                                               return 'acces_point.png';
            if (str_contains($d, 'modem'))                                                     return 'modem.png';
            if (str_contains($d, 'server_torre') || str_contains($d, 'server torre'))         return 'server_torre.png';
            if (str_contains($d, 'server_rack') || str_contains($d, 'server rack') || str_contains($d, 'server'))
                                                                                               return 'server_rack.png';
            if (str_contains($d, 'storage'))                                                   return 'storage.png';
            if (str_contains($d, 'internet'))                                                  return 'internet.png';
            if (str_contains($d, 'network_cloud') || str_contains($d, 'cloud'))               return 'network_cloud.png';
            if (str_contains($d, 'pc_desktop') || str_contains($d, 'desktop') || preg_match('/\bpc\b/', $d))
                                                                                               return 'pc_desktop.png';
            if (str_contains($d, 'laptop') || str_contains($d, 'notebook'))                   return 'laptop.png';
            if (str_contains($d, 'ip_phone') || str_contains($d, 'phone') || str_contains($d, 'voip'))
                                                                                               return 'ip_phone.png';
            if (str_contains($d, 'printer') || str_contains($d, 'impresora'))                 return 'printer.png';
            if (str_contains($d, 'security_camera') || str_contains($d, 'camera') || str_contains($d, 'camara'))
                                                                                               return 'security_camera.png';
            if (str_contains($d, 'vpn_conection') || str_contains($d, 'vpn'))                 return 'vpn_conection.png';
            if (str_contains($d, 'load_balancer') || preg_match('/\blb\b/', $d))              return 'load_balancer.png';
            return 'access_switch.png';
        };

        // Helper unificado: devuelve todos los datos visuales de un vecino documentado.
        // inDb=true  → borde verde, icono según rol
        // inDb=false → borde naranja, icono según tipo de dispositivo detectado
        $neighborData = function (string $desc) use ($knownSwitches, $iconFileMap, $deviceIcon): array {
            $match = $knownSwitches->first(fn($s) =>
                $s->sys_name && str_contains(strtolower($desc), strtolower($s->sys_name))
            ) ?? $knownSwitches->get($desc);

            if ($match) {
                $r       = $match->is_stacked
                    ? 'stack'
                    : TopologyBuilderService::detectRoleStatic($match->sys_name ?? '');
                $icon    = $r === 'stack'
                    ? route('admin.topology.icon', 'stack_switch.png')
                    : route('admin.topology.icon', $iconFileMap[$r] ?? 'access_switch.png');
                return [
                    'inDb'  => true,
                    'icon'  => $icon,
                    'model' => $match->system_type,
                    'ip'    => $match->management_ip,
                    'borderColor'    => '#16A34A',   // verde  → en BD
                    'highlightColor' => '#15803D',
                    'fontColor'      => '#14532D',
                ];
            }

            return [
                'inDb'  => false,
                'icon'  => route('admin.topology.icon', $deviceIcon($desc)),
                'model' => null,
                'ip'    => null,
                'borderColor'    => '#EA580C',   // naranja → no en BD, dispositivo final
                'highlightColor' => '#C2410C',
                'fontColor'      => '#7C2D12',
            ];
        };

        // Alias para compatibilidad con código existente


        // ── Nodo central (stack o standalone) ────────────────────────
        $centerNode = [
            'id'    => 0,
            'label' => implode("\n", array_filter([
                $switch->sys_name      ?? 'Switch',
                $switch->system_type   ?? null,
                $switch->management_ip ?? null,
            ])),
            'title' => implode("\n", array_filter([
                'Nombre: '   . ($switch->sys_name        ?? '—'),
                'Modelo: '   . ($switch->system_type     ?? '—'),
                'IP: '       . ($switch->management_ip   ?? '—'),
                'MAC: '      . ($switch->system_mac      ?? '—'),
                'Serie: '    . ($switch->serial_number   ?? '—'),
                'Firmware: ' . ($switch->firmware_version ?? '—'),
            ])),
            'shape' => 'image',
            'image' => $centerIconUrl,
            'size'  => 55,
            'x' => 0, 'y' => 0,
            'fixed' => ['x' => true, 'y' => true],
            'color' => [
                'border'     => '#1E3A5F',
                'background' => 'transparent',
                'highlight'  => ['border' => '#2563EB', 'background' => '#DBEAFE'],
            ],
            'font' => ['color' => '#1e293b', 'size' => 12, 'bold' => true],
            'mass' => 5,
        ];

        $portNodes   = [];
        $portEdges   = [];
        $portsList   = [];   // filas para la tabla de puertos (nodeId + meta)
        $nodeCounter = 1;

        if ($isStacked) {
            // ── Modo STACK ─────────────────────────────────────────────
            // Agrupar puertos por slot (notación "slot:puerto")
            $portsBySlot = [];
            foreach ($activePorts as $port) {
                $portNum = $port['port'] ?? '';
                $slot = preg_match('/^(\d+):/', $portNum, $m) ? (int)$m[1] : 1;
                $portsBySlot[$slot][] = $port;
            }

            $members  = collect($switch->stack_members)->sortBy('slot');
            $slotKeys = $members->pluck('slot')->toArray();
            // Incluir slots que aparecen en los puertos pero no en stack_members
            foreach (array_keys($portsBySlot) as $s) {
                if (!in_array($s, $slotKeys)) $slotKeys[] = $s;
            }
            sort($slotKeys);
            $numSlots = count($slotKeys);

            $slotRadius = 260;  // centro → nodo slot
            $portRadius  = 200;  // nodo slot → nodo puerto

            foreach ($slotKeys as $slotIdx => $slot) {
                // Ángulo del slot distribuido uniformemente
                $slotAngleDeg = ($numSlots > 1)
                    ? ($slotIdx / $numSlots) * 360 - 90
                    : -90;
                $slotAngleRad = deg2rad($slotAngleDeg);
                $slotX = round($slotRadius * cos($slotAngleRad));
                $slotY = round($slotRadius * sin($slotAngleRad));

                $member     = $members->firstWhere('slot', $slot);
                $slotRole   = $member['role']          ?? null;
                $slotState  = $member['stack_state']   ?? null;
                $slotSerial = $member['serial_number'] ?? null;
                $slotMac    = $member['mac']            ?? null;

                $slotLabel = implode("\n", array_filter([
                    "Slot {$slot}",
                    $slotRole,
                    $slotState,
                    $slotSerial,
                    $slotMac,
                ]));
                $slotNodeId = $nodeCounter++;

                $portNodes[] = [
                    'id'    => $slotNodeId,
                    'label' => $slotLabel,
                    'title' => implode("\n", array_filter([
                        "Slot: {$slot}",
                        $member ? 'Rol: '    . ($slotRole   ?? '—') : null,
                        $member ? 'Estado: ' . ($slotState  ?? '—') : null,
                        $member ? 'Serie: '  . ($slotSerial ?? '—') : null,
                        $member ? 'MAC: '    . ($slotMac    ?? '—') : null,
                    ])),
                    'shape' => 'image',
                    'image' => route('admin.topology.icon', 'stack_switch.png'),
                    'size'  => 38,
                    'x' => $slotX, 'y' => $slotY,
                    'fixed' => ['x' => true, 'y' => true],
                    'color' => [
                        'border'     => '#1E3A8A',
                        'background' => 'transparent',
                        'highlight'  => ['border' => '#3B82F6', 'background' => '#DBEAFE'],
                    ],
                    'font' => ['color' => '#1e293b', 'size' => 11, 'bold' => true],
                    'mass' => 3,
                ];

                // Arista centro → slot (punteada)
                $portEdges[] = [
                    'from'   => 0,
                    'to'     => $slotNodeId,
                    'arrows' => '',
                    'dashes' => true,
                    'color'  => ['color' => '#60A5FA'],
                    'smooth' => false,
                ];

                // Puertos del slot en abanico alrededor del ángulo del slot
                $slotPorts = $portsBySlot[$slot] ?? [];
                $numPorts  = count($slotPorts);
                $arcSpan   = min(150, max(60, $numPorts * 14)); // grados del abanico

                foreach ($slotPorts as $portIdx => $port) {
                    $offset = ($numPorts > 1)
                        ? -$arcSpan / 2 + ($portIdx / ($numPorts - 1)) * $arcSpan
                        : 0;
                    $portAngleRad = deg2rad($slotAngleDeg + $offset);
                    $portX = $slotX + round($portRadius * cos($portAngleRad));
                    $portY = $slotY + round($portRadius * sin($portAngleRad));

                    $desc       = trim($port['display_string'] ?? '');
                    $hasDesc    = $desc !== '';
                    $portNodeId = $nodeCounter++;
                    $nd         = $hasDesc ? $neighborData($desc) : null;

                    if ($hasDesc) {
                        $portNodes[] = [
                            'id'          => $portNodeId,
                            'label'       => implode("\n", array_filter([$desc, $nd['model'], $nd['ip']])),
                            'title'       => implode("\n", array_filter([
                                'Vecino: '    . $desc,
                                $nd['inDb'] ? '✓ Registrado en BD' : '✗ No registrado en BD',
                                $nd['model'] ? 'Modelo: ' . $nd['model'] : null,
                                $nd['ip']    ? 'IP: '     . $nd['ip']    : null,
                                'Puerto: '    . $port['port'],
                                'VLAN: '      . ($port['vlan_name']   ?? '—'),
                                'Velocidad: ' . ($port['speed_actual'] ?? '—') . ' Mbps',
                            ])),
                            'shape'       => 'image',
                            'image'       => $nd['icon'],
                            'size'        => 28,
                            'borderWidth' => 3,
                            'x' => $portX, 'y' => $portY,
                            'fixed'       => ['x' => false, 'y' => false],
                            'color'       => [
                                'border'     => $nd['borderColor'],
                                'background' => 'rgba(0,0,0,0)',
                                'highlight'  => ['border' => $nd['highlightColor'], 'background' => 'rgba(0,0,0,0)'],
                            ],
                            'font'        => ['color' => $nd['fontColor'], 'size' => 11],
                        ];
                    } else {
                        $portNodes[] = [
                            'id'          => $portNodeId,
                            'label'       => 'Activo - No documentado',
                            'title'       => implode("\n", array_filter([
                                'Puerto: '    . $port['port'],
                                'Sin descripción',
                                'VLAN: '      . ($port['vlan_name']   ?? '—'),
                                'Velocidad: ' . ($port['speed_actual'] ?? '—') . ' Mbps',
                                'Dúplex: '    . ($port['duplex_actual'] ?? '—'),
                            ])),
                            'shape'       => 'image',
                            'image'       => route('admin.topology.icon', 'port-not-data.png'),
                            'size'        => 22,
                            'borderWidth' => 1,
                            'x' => $portX, 'y' => $portY,
                            'fixed'       => ['x' => false, 'y' => false],
                            'color'       => [
                                'border'     => '#D1D5DB',
                                'background' => 'rgba(0,0,0,0)',
                                'highlight'  => ['border' => '#9CA3AF', 'background' => 'rgba(0,0,0,0)'],
                            ],
                            'font'        => ['color' => '#9CA3AF', 'size' => 10],
                        ];
                    }

                    $portEdges[] = [
                        'from'   => $slotNodeId,
                        'to'     => $portNodeId,
                        'label'  => $port['port'],
                        'font'   => ['size' => 10, 'align' => 'middle', 'color' => '#374151'],
                        'color'  => ['color' => $nd ? $nd['borderColor'] : '#D1D5DB'],
                        'arrows' => '',
                        'dashes' => false,
                        'smooth' => false,
                    ];
                    $portsList[] = [
                        'nodeId' => $portNodeId,
                        'port'   => $port['port'],
                        'slot'   => $slot,
                        'desc'   => $desc ?: null,
                        'speed'  => $port['speed_actual']  ?? null,
                        'duplex' => $port['duplex_actual'] ?? null,
                        'vlan'   => $port['vlan_name']     ?? null,
                        'inDb'   => $nd ? $nd['inDb'] : null,
                        'color'  => $nd ? $nd['borderColor'] : '#9CA3AF',
                    ];
                }
            }

        } else {
            // ── Modo STANDALONE: distribución radial simple ────────────
            $total  = count($activePorts);
            $radius = max(320, $total * 14);

            foreach ($activePorts as $idx => $port) {
                $desc    = trim($port['display_string'] ?? '');
                $hasDesc = $desc !== '';
                $nodeId  = $nodeCounter++;
                $nd      = $hasDesc ? $neighborData($desc) : null;

                $angleDeg = ($total > 0) ? ($idx / $total) * 360 - 90 : 0;
                $angleRad = deg2rad($angleDeg);
                $x = round($radius * cos($angleRad));
                $y = round($radius * sin($angleRad));

                if ($hasDesc) {
                    $portNodes[] = [
                        'id'          => $nodeId,
                        'label'       => implode("\n", array_filter([$desc, $nd['model'], $nd['ip']])),
                        'title'       => implode("\n", array_filter([
                            'Vecino: '    . $desc,
                            $nd['inDb'] ? '✓ Registrado en BD' : '✗ No registrado en BD',
                            $nd['model'] ? 'Modelo: ' . $nd['model'] : null,
                            $nd['ip']    ? 'IP: '     . $nd['ip']    : null,
                            'Puerto: '    . $port['port'],
                            'VLAN: '      . ($port['vlan_name']   ?? '—'),
                            'Velocidad: ' . ($port['speed_actual'] ?? '—') . ' Mbps',
                        ])),
                        'shape'       => 'image',
                        'image'       => $nd['icon'],
                        'size'        => 32,
                        'borderWidth' => 3,
                        'x' => $x, 'y' => $y,
                        'fixed'       => ['x' => false, 'y' => false],
                        'color'       => [
                            'border'     => $nd['borderColor'],
                            'background' => 'rgba(0,0,0,0)',
                            'highlight'  => ['border' => $nd['highlightColor'], 'background' => 'rgba(0,0,0,0)'],
                        ],
                        'font'        => ['color' => $nd['fontColor'], 'size' => 11],
                    ];
                } else {
                    $portNodes[] = [
                        'id'          => $nodeId,
                        'label'       => 'Activo - No documentado',
                        'title'       => implode("\n", array_filter([
                            'Puerto: '    . $port['port'],
                            'Sin descripción',
                            'VLAN: '      . ($port['vlan_name']   ?? '—'),
                            'Velocidad: ' . ($port['speed_actual'] ?? '—') . ' Mbps',
                            'Dúplex: '    . ($port['duplex_actual'] ?? '—'),
                        ])),
                        'shape'       => 'image',
                        'image'       => route('admin.topology.icon', 'port-not-data.png'),
                        'size'        => 22,
                        'borderWidth' => 1,
                        'x' => $x, 'y' => $y,
                        'fixed'       => ['x' => false, 'y' => false],
                        'color'       => [
                            'border'     => '#D1D5DB',
                            'background' => 'rgba(0,0,0,0)',
                            'highlight'  => ['border' => '#9CA3AF', 'background' => 'rgba(0,0,0,0)'],
                        ],
                        'font'        => ['color' => '#9CA3AF', 'size' => 10],
                    ];
                }

                $portEdges[] = [
                    'from'   => 0,
                    'to'     => $nodeId,
                    'label'  => $port['port'],
                    'font'   => ['size' => 10, 'align' => 'middle', 'color' => '#374151'],
                    'color'  => ['color' => $nd ? $nd['borderColor'] : '#D1D5DB'],
                    'arrows' => '',
                    'dashes' => false,
                    'smooth' => false,
                ];
                $portsList[] = [
                    'nodeId' => $nodeId,
                    'port'   => $port['port'],
                    'slot'   => null,
                    'desc'   => $desc ?: null,
                    'speed'  => $port['speed_actual']  ?? null,
                    'duplex' => $port['duplex_actual'] ?? null,
                    'vlan'   => $port['vlan_name']     ?? null,
                    'inDb'   => $nd ? $nd['inDb'] : null,
                    'color'  => $nd ? $nd['borderColor'] : '#9CA3AF',
                ];
            }
        }

        $vlans = collect($switch->vlans ?? [])->sortBy('vid')->values();

        return view('admin.switches.ports-diagram', compact(
            'switch', 'centerNode', 'portNodes', 'portEdges', 'portsList', 'vlans', 'isStacked'
        ));
    }

    // GET /switches/{switch}/config → descarga el archivo de configuración
    public function downloadConfig(Switche $switch)
    {
        abort_if(!$switch->config_path, 404, 'No hay archivo de configuración disponible.');
        abort_unless(Storage::exists($switch->config_path), 404, 'Archivo no encontrado en el servidor.');

        $filename = ($switch->sys_name ?? 'switch') . '_config.txt';

        return Storage::download($switch->config_path, $filename);
    }

    // DELETE /switches/{switch} → elimina el switch y su config
    public function destroy(Switche $switch)
    {
        $batchId = $switch->upload_batch_id;

        // Eliminar archivo de configuración si existe
        if ($switch->config_path && Storage::exists($switch->config_path)) {
            Storage::delete($switch->config_path);
        }

        $switch->delete();

        return redirect()
            ->route('admin.switches.index', array_filter(['batch' => $batchId]))
            ->with('success', 'Switch eliminado correctamente.');
    }

    // POST /switches/{switch}/diagram/generate?with_vlans=1
    public function generateSwitchDiagram(Switche $switch, Request $request)
    {
        $withVlans = (bool) $request->query('with_vlans', false);

        // Mapa de switches conocidos para detectar si un vecino está en BD
        $knownSwitches = Switche::where('parse_status', 'ok')
            ->get(['id', 'sys_name', 'system_type', 'management_ip'])
            ->keyBy('sys_name');

        $findMatch = fn(?string $desc) => $desc
            ? ($knownSwitches->first(fn($s) =>
                $s->sys_name && str_contains(strtolower($desc), strtolower($s->sys_name))
               ) ?? $knownSwitches->get($desc))
            : null;

        // ── Puertos activos ───────────────────────────────────────
        $ports = [];
        foreach ($switch->active_ports ?? [] as $p) {
            $desc  = trim($p['display_string'] ?? '');
            $match = $desc ? $findMatch($desc) : null;
            $ports[] = [
                'port'  => $p['port'],
                'desc'  => $desc ?: null,
                'in_db' => (bool) $match,
                'model' => $match?->system_type,
                'ip'    => $match?->management_ip,
            ];
        }

        // ── VLANs (solo si se solicitan) ─────────────────────────
        $vlans = $withVlans
            ? collect($switch->vlans ?? [])->sortBy('vid')->map(fn($v) => [
                'vid'  => $v['vid']  ?? '',
                'name' => $v['name'] ?? '',
                'ip'   => $v['protocol_addr'] ?? $v['ip'] ?? '',
              ])->values()->all()
            : [];

        $role = TopologyBuilderService::detectRoleStatic($switch->sys_name ?? '');

        $payload = [
            'switch' => [
                'sys_name'         => $switch->sys_name,
                'system_type'      => $switch->system_type,
                'serial_number'    => $switch->serial_number,
                'system_mac'       => $switch->system_mac,
                'management_ip'    => $switch->management_ip,
                'firmware_version' => $switch->firmware_version,
                'is_stacked'       => (bool) $switch->is_stacked,
                'role'             => $role,
                'gateway'          => $switch->gateway ?? null,
            ],
            'ports' => $ports,
            'vlans' => $vlans,
        ];

        // Paths — sufijo distinto según modo
        $batchId    = $switch->upload_batch_id ?? 0;
        $storageDir = storage_path("app/topology/{$batchId}/switches");
        @mkdir($storageDir, 0775, true);
        $suffix   = $withVlans ? '_vlans' : '';
        $jsonPath = $storageDir . "/{$switch->id}_input{$suffix}.json";
        $pngPath  = $storageDir . "/{$switch->id}_diagram{$suffix}.png";

        file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $scriptPath = base_path('scripts/switch_diagram_generator.py');
        $cmd = sprintf('python %s %s %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($jsonPath),
            escapeshellarg($pngPath)
        );
        $output = shell_exec($cmd) ?? '';

        if (!file_exists($pngPath)) {
            return response()->json(['error' => 'Error al generar diagrama', 'detail' => $output], 500);
        }

        $imageRoute = $withVlans
            ? route('admin.switches.diagram.image', [$switch, 'with_vlans' => 1])
            : route('admin.switches.diagram.image', $switch);

        return response()->json(['ok' => true, 'url' => $imageRoute]);
    }

    // GET /switches/{switch}/diagram/image?with_vlans=1
    public function switchDiagramImage(Switche $switch, Request $request)
    {
        $withVlans = (bool) $request->query('with_vlans', false);
        $batchId   = $switch->upload_batch_id ?? 0;
        $suffix    = $withVlans ? '_vlans' : '';
        $pngPath   = storage_path("app/topology/{$batchId}/switches/{$switch->id}_diagram{$suffix}.png");

        abort_unless(file_exists($pngPath), 404, 'Diagrama no generado aun.');

        return response()->file($pngPath, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'no-cache',
        ]);
    }
}