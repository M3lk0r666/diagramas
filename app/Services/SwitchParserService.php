<?php
namespace App\Services;

class SwitchParserService
{
    /**
     * Punto de entrada. Soporta dos formatos:
     *
     * NUEVO (herramienta de backup):
     *   ======================================================================
     *   !  show switch detail
     *   ======================================================================
     *
     * ANTIGUO (prompt del switch):
     *   SW-02.1 # show switch
     */
    public function parse(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $header   = $this->parseFileHeader($content);
        $sections = $this->splitSections($content);

        $switchInfo = $this->parseSwitchInfo(
            $sections['show switch detail'] ?? $sections['show switch'] ?? ''
        );

        // Si show switch detail/switch no tiene sys_name, usar el hostname del header
        if (empty($switchInfo['sys_name']) && !empty($header['hostname'])) {
            $switchInfo['sys_name'] = $header['hostname'];
        }

        $versionText = $sections['show version detail'] ?? '';

        return [
            'header'       => $header,
            'switch_info'  => $switchInfo,
            'version'      => $this->parseVersion($versionText),
            'vlans'        => $this->parseVlan($sections['show vlan'] ?? ''),
            'ip_routes'    => $this->parseIpRoute($sections['show iproute'] ?? ''),
            'edp_ports'    => $this->parseEdpPorts($sections['show edp ports all'] ?? ''),
            'active_ports' => $this->parsePorts($sections['show ports no-refresh'] ?? ''),
            'stacking'     => $this->parseStackInfo($sections['show stacking'] ?? '', $versionText),
            'raw_sections' => $sections,
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // HEADER DEL ARCHIVO (generado por la herramienta de backup)
    // ════════════════════════════════════════════════════════════════

    /**
     * Extrae metadatos del bloque de encabezado:
     *   !  Host      : 192.168.254.253
     *   !  Hostname  : NETjerCore
     *   !  Fecha     : 2026-06-04 16:57:06
     */
    private function parseFileHeader(string $content): array
    {
        $header = [];

        $fields = [
            'host_ip'  => '/^!\s+Host\s*:\s*(.+)$/m',
            'hostname' => '/^!\s+Hostname\s*:\s*(.+)$/m',
            'fecha'    => '/^!\s+Fecha\s*:\s*(.+)$/m',
            'vendor'   => '/^!\s+Vendor\s*:\s*(.+)$/m',
        ];

        foreach ($fields as $key => $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $header[$key] = trim($m[1]);
            }
        }

        return $header;
    }

    // ════════════════════════════════════════════════════════════════
    // SPLIT DE SECCIONES — compatible con ambos formatos
    // ════════════════════════════════════════════════════════════════

    private function splitSections(string $content): array
    {
        $commands = [
            'show configuration',
            'show version detail',
            'show switch detail',
            'show switch',
            'show vlan',
            'show iproute',
            'show edp ports all',
            'show ports no-refresh',
            'show stacking',
        ];

        $sections = [];
        $lines    = explode("\n", $content);
        $current  = null;
        $buffer   = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // ── Nuevo formato: "!  show comando" ──────────────────────────
            if (preg_match('/^!\s+(show\s+\S.+?)\s*$/i', $trimmed, $nm)) {
                $cmd = strtolower(trim($nm[1]));
                if (in_array($cmd, $commands)) {
                    if ($current !== null) {
                        $sections[$current] = implode("\n", $buffer);
                    }
                    $current = $cmd;
                    $buffer  = [];
                    continue;
                }
            }

            // ── Formato antiguo: "hostname # show comando" ────────────────
            $matchedOld = false;
            foreach ($commands as $cmd) {
                if (preg_match(
                    '/^[\w\-\.]+\s*#\s*' . preg_quote($cmd, '/') . '\s*$/i',
                    $trimmed
                )) {
                    if ($current !== null) {
                        $sections[$current] = implode("\n", $buffer);
                    }
                    $current    = $cmd;
                    $buffer     = [];
                    $matchedOld = true;
                    break;
                }
            }
            if ($matchedOld) continue;

            // ── Líneas de decoración — se descartan ───────────────────────
            if (preg_match('/^={10,}/', $trimmed)) continue;
            // Líneas del bloque de encabezado del backup tool
            if (preg_match('/^!\s*(={3,}|Backup|Vendor|Host\s*:|Hostname\s*:|Fecha|Generado)/i', $trimmed)) continue;

            // ── Contenido de la sección actual ────────────────────────────
            if ($current !== null) {
                $buffer[] = $line;
            }
        }

        if ($current !== null) {
            $sections[$current] = implode("\n", $buffer);
        }

        return $sections;
    }

    // ════════════════════════════════════════════════════════════════
    // show switch / show switch detail
    // ════════════════════════════════════════════════════════════════

    public function parseSwitchInfo(string $text): array
    {
        $info = [];
        $map  = [
            'SysName'     => 'sys_name',
            'SysLocation' => 'sys_location',
            'SysContact'  => 'sys_contact',
            'System MAC'  => 'system_mac',
            'System Type' => 'system_type',
        ];

        foreach ($map as $label => $key) {
            if (preg_match('/^' . preg_quote($label, '/') . '\s*:\s*(.*)$/m', $text, $m)) {
                $value = trim($m[1]);
                if ($value !== '') {
                    $info[$key] = $value;
                }
            }
        }

        return $info;
    }

    // ════════════════════════════════════════════════════════════════
    // show version detail
    // ════════════════════════════════════════════════════════════════

    public function parseVersion(string $text): array
    {
        $result = [];

        // ── Número de serie ─────────────────────────────────────────
        // Nuevo formato: "Switch : 800324-00-07 1152G-80136 Rev 7.0"
        //                                      ^^^^^^^^^ serial
        if (preg_match('/^Switch\s*:\s*\S+\s+(\S+)\s+Rev\b/im', $text, $m)) {
            $result['serial_number'] = trim($m[1]);
        }
        // Formato antiguo: "SB012427G-00181" o similar
        if (empty($result['serial_number'])) {
            if (preg_match('/\b([A-Z]{2}\d{6}[A-Z]-\d{5})\b/', $text, $m)) {
                $result['serial_number'] = $m[1];
            }
        }

        // ── Versión de firmware ──────────────────────────────────────
        // Nuevo: "Image   : ExtremeXOS version 16.2.4.5 16.2.4.5-patch1-6"
        if (preg_match('/^Image\s*:\s*ExtremeXOS\s+version\s+([\d.]+)/im', $text, $m)) {
            $result['firmware_version'] = trim($m[1]);
        }
        // Ambos formatos: "IMG: 16.2.4.5"
        if (empty($result['firmware_version'])) {
            if (preg_match('/IMG:\s*([\d.]+(?:-patch[\w-]+)?)/i', $text, $m)) {
                $result['firmware_version'] = trim($m[1]);
            }
        }

        return $result;
    }

    // ════════════════════════════════════════════════════════════════
    // show vlan
    // ════════════════════════════════════════════════════════════════

    public function parseVlan(string $text): array
    {
        $vlans  = [];
        $lines  = explode("\n", $text);
        $inData = false;

        foreach ($lines as $line) {

            if (preg_match('/^-{10,}/', $line)) {
                $inData = true;
                continue;
            }
            if (preg_match('/^Flags\s*:/i', $line) || preg_match('/^Total number/i', $line)) {
                break;
            }
            if (!$inData || trim($line) === '') continue;
            if (!preg_match('/^\S/', $line)) continue;
            if (preg_match('/^Name\s+VID/i', trim($line))) continue;

            // Name — hasta primer bloque de 2+ espacios
            if (!preg_match('/^(\S+)\s{2,}(.*)$/', $line, $m)) continue;
            $name = trim($m[1]);
            $rest = $m[2];

            // VID
            if (!preg_match('/^(\d+)\s+(.*)$/', trim($rest), $m)) continue;
            $vid  = (int)$m[1];
            $rest = $m[2];

            // IP/máscara (opcional)
            $ip = null;
            if (preg_match('/^([\d.]+)\s*\/(\d+)\s+(.*)$/', trim($rest), $m)) {
                $ip   = $m[1] . '/' . $m[2];
                $rest = $m[3];
            } elseif (preg_match('/^([\d.]+)\s+\/(\d+)\s+(.*)$/', trim($rest), $m)) {
                $ip   = $m[1] . '/' . $m[2];
                $rest = $m[3];
            }

            // Flags
            $flagsRaw    = null;
            $flagsActive = [];
            if (preg_match('/^([A-Za-z\-]+)\s+(ANY|IP\b|IPv4|IPv6)\s+(.*)$/i', trim($rest), $m)) {
                $flagsRaw = $m[1];
                $rest     = $m[2] . ' ' . $m[3];
                preg_match_all('/[A-Za-z]/', $flagsRaw, $found);
                $flagsActive = $found[0];
            }

            // Proto, Ports, VR
            $proto = $portsActive = $virtualRouter = null;
            if (preg_match('/(ANY|IP\b|IPv4|IPv6)\s+(\d+\s*\/\s*\d+)\s+(VR-\S+)/i', $rest, $m)) {
                $proto         = $m[1];
                $portsActive   = trim($m[2]);
                $virtualRouter = $m[3];
            }

            $vlans[] = [
                'name'           => $name,
                'vid'            => $vid,
                'protocol_addr'  => $ip,
                'flags'          => $flagsRaw,
                'flags_active'   => $flagsActive,
                'proto'          => $proto,
                'ports_active'   => $portsActive,
                'virtual_router' => $virtualRouter,
            ];
        }

        return $vlans;
    }

    // ════════════════════════════════════════════════════════════════
    // show iproute — solo rutas con Ori = #s o #d
    // ════════════════════════════════════════════════════════════════

    public function parseIpRoute(string $text): array
    {
        $routes = [];
        $lines  = explode("\n", $text);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') continue;
            if (preg_match('/^Ori\s+Destination/i', $trimmed)) continue;

            if (!preg_match('/^(#[a-zA-Z\*])\s+(.+)$/', $trimmed, $m)) continue;

            $ori  = $m[1];
            $rest = trim($m[2]);

            if (!in_array(strtolower($ori), ['#s', '#d'])) continue;

            // Destination
            $destination = null;
            if (preg_match('/^(Default\s+Route)\s{2,}(.*)$/i', $rest, $d)) {
                $destination = 'Default Route';
                $rest        = trim($d[2]);
            } elseif (preg_match('/^(\S+)\s+(.*)$/', $rest, $d)) {
                $destination = $d[1];
                $rest        = trim($d[2]);
            } else {
                continue;
            }

            if (!preg_match('/^(\S+)\s+(.*)$/', $rest, $g)) continue;
            $gateway = $g[1];
            $rest    = trim($g[2]);

            if (!preg_match('/^(\d+)\s+(.*)$/', $rest, $mt)) continue;
            $mtr  = (int)$mt[1];
            $rest = trim($mt[2]);

            if (!preg_match('/^(\S+)\s+(.*)$/', $rest, $fl)) continue;
            $flags = $fl[1];
            $rest  = trim($fl[2]);

            if (!preg_match('/^(\S+)\s*(.*)$/', $rest, $vl)) continue;
            $vlan     = $vl[1];
            $duration = trim($vl[2]);

            $routes[] = [
                'ori'         => $ori,
                'destination' => $destination,
                'gateway'     => $gateway,
                'mtr'         => $mtr,
                'flags'       => $flags,
                'vlan'        => $vlan,
                'duration'    => $duration,
            ];
        }

        return $routes;
    }

    // ════════════════════════════════════════════════════════════════
    // show edp ports all
    // Puerto puede ser simple (47) o de stack (2:1, 3:12)
    // Neighbor-ID siempre 8 bytes: 00:00:dc:e6:50:...
    // ════════════════════════════════════════════════════════════════

    public function parseEdpPorts(string $text): array
    {
        $ports = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            // Puerto: número simple o stack (1:2)
            // Neighbor-ID: 8 grupos hex separados por ':'
            if (preg_match(
                '/^\s*(\d+(?::\d+)?)\s+(\S+)\s+((?:[0-9a-fA-F]{2}:){7}[0-9a-fA-F]{2})\s+(\S+)\s+(\d+)\s+(\d+)/',
                $line, $m
            )) {
                $ports[] = [
                    'port'        => $m[1],
                    'neighbor'    => $m[2],
                    'neighbor_id' => $m[3],
                    'remote_port' => $m[4],
                    'age'         => (int)$m[5],
                    'num_vlans'   => (int)$m[6],
                ];
            }
        }

        return $ports;
    }

    // ════════════════════════════════════════════════════════════════
    // show ports no-refresh — solo puertos E (Enabled) + A (Active)
    // ════════════════════════════════════════════════════════════════

    public function parsePorts(string $text): array
    {
        $ports = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            if (preg_match(
                '/^\s*(\d+(?::\d+)?)\s+(.*?)\s{2,}(\S.*?)\s{2,}(E)\s+(A)\s+(\d+)\s+(\w+)/',
                $line, $m
            )) {
                $ports[] = [
                    'port'          => $m[1],
                    'display_string'=> trim($m[2]),
                    'vlan_name'     => trim($m[3]),
                    'port_state'    => $m[4],
                    'link_state'    => $m[5],
                    'speed_actual'  => $m[6],
                    'duplex_actual' => $m[7],
                ];
            }
        }

        return $ports;
    }

    // ════════════════════════════════════════════════════════════════
    // Stacking — combina "show stacking" (mac, slot, rol, estado) con
    // "show version detail" (número de serie por slot)
    //
    //   show stacking:
    //     Stack Topology is a Ring
    //     Node MAC Address      Slot  Stack State   Role        Flags
    //     *40:b2:15:10:0c:00    1     Active        Master      CA-
    //
    //   show version detail:
    //     Slot-1   : 800996-00-AN SB072434G-00101 Rev AN BootROM: ...
    // ════════════════════════════════════════════════════════════════

    public function parseStackInfo(string $stackingText, string $versionText): array
    {
        $serials = $this->parseSlotSerials($versionText);

        // Cada línea de topología se captura por separado (anclada a fin de línea)
        // para no arrastrar el resto del texto de la sección:
        //   Stack Topology is a Ring
        //   Active Topology is a Ring | Daisy-Chain
        $topologyLines = [];
        $stackTopology  = null;
        if (preg_match('/^Stack\s+Topology\s+is\s+a\s+([A-Za-z][A-Za-z\-]*)\s*$/mi', $stackingText, $m)) {
            $stackTopology   = trim($m[1]);
            $topologyLines[] = 'Stack Topology is a ' . $stackTopology;
        }
        if (preg_match('/^Active\s+Topology\s+is\s+a\s+([A-Za-z][A-Za-z\-]*)\s*$/mi', $stackingText, $m)) {
            $topologyLines[] = 'Active Topology is a ' . trim($m[1]);
        }

        $topology = !empty($topologyLines) ? implode("\n", $topologyLines) : null;

        $members = [];
        foreach (explode("\n", $stackingText) as $line) {
            if (preg_match(
                '/^(\*?)\s*([0-9a-fA-F]{2}(?::[0-9a-fA-F]{2}){5})\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)/',
                $line, $m
            )) {
                $slot = (int) $m[3];
                $members[] = [
                    'slot'          => $slot,
                    'mac'           => strtoupper($m[2]),
                    'stack_state'   => $m[4],
                    'role'          => $m[5],
                    'flags'         => $m[6],
                    'is_current'    => $m[1] === '*',
                    'part_number'   => $serials[$slot]['part_number']   ?? null,
                    'serial_number' => $serials[$slot]['serial_number'] ?? null,
                ];
            }
        }

        $isStacked = count($members) > 1 || (bool) $topology;

        return [
            'is_stacked' => $isStacked,
            'topology'   => $topology,
            'members'    => $members,
        ];
    }

    /**
     * Extrae número de parte y serie por slot desde "show version detail":
     *   Slot-1   : 800996-00-AN SB072434G-00101 Rev AN ...
     *   Slot-4   :
     * Devuelve [slot => ['part_number' => ..., 'serial_number' => ...]]
     */
    private function parseSlotSerials(string $text): array
    {
        $serials = [];
        if (preg_match_all(
            '/^Slot-(\d+)\s*:\s*(?:(\S+)\s+(\S+)\s+Rev\s+\S+)?/m',
            $text, $matches, PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                if (empty($m[2]) || empty($m[3])) continue;
                $serials[(int) $m[1]] = [
                    'part_number'   => trim($m[2]),
                    'serial_number' => trim($m[3]),
                ];
            }
        }

        return $serials;
    }
}
