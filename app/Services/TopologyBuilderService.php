<?php

namespace App\Services;

use App\Models\SwitcheConnection;
use App\Models\UploadBatch;
use Illuminate\Support\Collection;

/**
 * Construye el JSON de topología para un batch dado.
 *
 * Estructura de salida:
 * {
 *   "meta":  { batch_id, batch_name, generated_at },
 *   "nodes": [ { id, switch_id, label, role, ip, model, is_stacked, slot_count, stack_topology } ],
 *   "edges": [ { id, from, to, src_port, dst_port, label } ]
 * }
 */
class TopologyBuilderService
{
    // Nivel jerárquico por rol (0 = más alto / core, 3 = más bajo / acceso)
    private const ROLE_LEVEL = [
        'core'         => 0,
        'backbone'     => 1,
        'distribution' => 2,
        'access'       => 3,
    ];

    // Patrones para detección de rol por nombre del equipo
    private const ROLE_PATTERNS = [
        'core'         => '/\bCORE?\b|^CR[-_]|[-_]CR[-_]|^CORE/i',
        'backbone'     => '/\bBACKBONE\b|\bBACKB\b|^BB[-_]|[-_]BB[-_]/i',
        'distribution' => '/\bDIST\b|\bDS\b|\bDISTRIBUCION\b|\bDISTRIBUTION\b/i',
    ];

    // Colores asociados a cada rol (usados en el script Python)
    public const ROLE_META = [
        'core'         => ['color' => '#1E3A5F', 'label' => 'Core'],
        'backbone'     => ['color' => '#1D4ED8', 'label' => 'Backbone'],
        'distribution' => ['color' => '#0891B2', 'label' => 'Distribución'],
        'access'       => ['color' => '#16A34A', 'label' => 'Acceso'],
    ];

    public function buildForBatch(UploadBatch $batch): array
    {
        $switches = $batch->switches()
            ->where('parse_status', 'ok')
            ->get();

        // Construir nodos indexados por switch_id
        $nodes      = [];
        $switchIndex = [];   // switch_id → node id string

        foreach ($switches as $sw) {
            $nodeId           = "sw_{$sw->id}";
            $switchIndex[$sw->id] = $nodeId;

            $role  = $this->detectRole($sw->sys_name ?? '');
            $nodes[] = [
                'id'                => $nodeId,
                'switch_id'         => $sw->id,
                'label'             => $sw->sys_name ?? "Switch-{$sw->id}",
                'role'              => $role,
                'level'             => self::ROLE_LEVEL[$role] ?? 3,
                'ip'                => $sw->management_ip,
                'model'             => $sw->system_type,
                'mac'               => $sw->system_mac,
                'serial'            => $sw->serial_number,
                'active_port_count' => count($sw->active_ports ?? []),
                'is_stacked'        => (bool) $sw->is_stacked,
                'slot_count'        => $sw->is_stacked ? count($sw->stack_members ?? []) : 1,
                'stack_topology'    => $sw->is_stacked
                    ? $this->extractTopologyValue($sw->stack_topology)
                    : null,
                'slots'             => $sw->is_stacked
                    ? collect($sw->stack_members ?? [])->map(fn($m) => [
                        'slot'   => $m['slot'],
                        'role'   => $m['role']          ?? null,
                        'state'  => $m['stack_state']   ?? null,
                        'serial' => $m['serial_number'] ?? null,
                        'mac'    => $m['mac']            ?? null,
                    ])->values()->all()
                    : [],
            ];
        }

        // Construir aristas (deduplicadas: solo A→B, no B→A también)
        $switchIds = $switches->pluck('id')->toArray();

        $connections = SwitcheConnection::whereIn('src_switch_id', $switchIds)
            ->whereIn('dst_switch_id', $switchIds)
            ->get();

        $edges    = [];
        $seen     = [];   // para evitar duplicados bidireccionales

        foreach ($connections as $conn) {
            $fromId = $switchIndex[$conn->src_switch_id] ?? null;
            $toId   = $switchIndex[$conn->dst_switch_id] ?? null;

            if (!$fromId || !$toId || $fromId === $toId) continue;

            // Clave canónica para deduplicación
            $key = implode('|', [min($fromId, $toId), max($fromId, $toId)]);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $edges[] = [
                'id'       => "conn_{$conn->id}",
                'from'     => $fromId,
                'to'       => $toId,
                'src_port' => $conn->src_port,
                'dst_port' => $conn->dst_port,
                'label'    => trim("{$conn->src_port} ↔ {$conn->dst_port}"),
                'num_vlans'=> $conn->num_vlans ?? 0,
            ];
        }

        return [
            'meta' => [
                'batch_id'     => $batch->id,
                'batch_name'   => $batch->name,
                'generated_at' => now()->toIso8601String(),
            ],
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Construye el JSON de topología para un subconjunto personalizado de switches.
     * No requiere UploadBatch — usado por CustomTopologyController.
     *
     * @param  Collection $switches  Colección de modelos Switche ya filtrados
     * @param  string     $name      Nombre de la topología personalizada
     */
    public function buildForCustom(Collection $switches, string $name): array
    {
        $nodes       = [];
        $switchIndex = [];

        foreach ($switches as $sw) {
            $nodeId                   = "sw_{$sw->id}";
            $switchIndex[$sw->id]     = $nodeId;
            $role                     = $this->detectRole($sw->sys_name ?? '');

            $nodes[] = [
                'id'                => $nodeId,
                'switch_id'         => $sw->id,
                'label'             => $sw->sys_name ?? "Switch-{$sw->id}",
                'role'              => $role,
                'level'             => self::ROLE_LEVEL[$role] ?? 3,
                'ip'                => $sw->management_ip,
                'model'             => $sw->system_type,
                'mac'               => $sw->system_mac,
                'serial'            => $sw->serial_number,
                'active_port_count' => count($sw->active_ports ?? []),
                'is_stacked'        => (bool) $sw->is_stacked,
                'slot_count'        => $sw->is_stacked ? count($sw->stack_members ?? []) : 1,
                'stack_topology'    => $sw->is_stacked
                    ? $this->extractTopologyValue($sw->stack_topology)
                    : null,
                'slots'             => $sw->is_stacked
                    ? collect($sw->stack_members ?? [])->map(fn($m) => [
                        'slot'   => $m['slot'],
                        'role'   => $m['role']          ?? null,
                        'state'  => $m['stack_state']   ?? null,
                        'serial' => $m['serial_number'] ?? null,
                        'mac'    => $m['mac']            ?? null,
                    ])->values()->all()
                    : [],
            ];
        }

        $switchIds   = $switches->pluck('id')->toArray();
        $connections = SwitcheConnection::whereIn('src_switch_id', $switchIds)
            ->whereIn('dst_switch_id', $switchIds)
            ->get();

        $edges = [];
        $seen  = [];

        foreach ($connections as $conn) {
            $fromId = $switchIndex[$conn->src_switch_id] ?? null;
            $toId   = $switchIndex[$conn->dst_switch_id] ?? null;
            if (!$fromId || !$toId || $fromId === $toId) continue;

            $key = implode('|', [min($fromId, $toId), max($fromId, $toId)]);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $edges[] = [
                'id'       => "conn_{$conn->id}",
                'from'     => $fromId,
                'to'       => $toId,
                'src_port' => $conn->src_port,
                'dst_port' => $conn->dst_port,
                'label'    => trim("{$conn->src_port} ↔ {$conn->dst_port}"),
                'num_vlans'=> $conn->num_vlans ?? 0,
            ];
        }

        return [
            'meta'  => [
                'batch_id'     => null,
                'batch_name'   => $name,
                'generated_at' => now()->toIso8601String(),
            ],
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public static function detectRoleStatic(string $name): string
    {
        foreach (self::ROLE_PATTERNS as $role => $pattern) {
            if (preg_match($pattern, $name)) {
                return $role;
            }
        }
        return 'access';
    }

    private function detectRole(string $name): string
    {
        return self::detectRoleStatic($name);
    }

    private function extractTopologyValue(?string $raw): ?string
    {
        if (!$raw) return null;
        if (preg_match('/\b(Ring|Daisy[-\s]?Chain)\b/i', $raw, $m)) {
            return ucwords(strtolower($m[1]));
        }
        return null;
    }
}
