<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\UploadBatch;
use App\Models\SwitcheConnection;
use App\Services\ConnectionResolverService;
use App\Services\TopologyBuilderService;
use Illuminate\Support\Collection;

class AreaTopologyController extends Controller
{
    /**
     * GET /areas
     * Lista todos los clientes con sus áreas disponibles.
     */
    public function index()
    {
        $clients = Client::withCount('batches')
            ->with(['batches' => fn ($q) => $q->withCount([
                'switches',
                'switches as ok_count' => fn ($q) => $q->where('parse_status', 'ok'),
            ])->latest()])
            ->orderBy('name')
            ->paginate(24);

        return view('admin.area-topology.index', compact('clients'));
    }

    /**
     * GET /areas/clients/{client}
     * Lista las áreas (batches) de un cliente específico.
     */
    public function areas(Client $client)
    {
        $batches = $client->batches()
            ->withCount([
                'switches',
                'switches as ok_count'   => fn ($q) => $q->where('parse_status', 'ok'),
                'switches as fail_count' => fn ($q) => $q->where('parse_status', 'failed'),
            ])
            ->latest()
            ->get();

        return view('admin.area-topology.areas', compact('client', 'batches'));
    }

    /**
     * GET /areas/clients/{client}/batches/{batch}
     * Switches del área con enlace a la topología.
     */
    public function show(Client $client, UploadBatch $batch)
    {
        abort_if($batch->client_id !== $client->id, 404);

        $switches = $batch->switches()
            ->orderBy('sys_name')
            ->get(['id', 'sys_name', 'system_type', 'management_ip', 'is_stacked', 'parse_status', 'serial_number']);

        $okCount   = $switches->where('parse_status', 'ok')->count();
        $failCount = $switches->where('parse_status', 'failed')->count();

        return view('admin.area-topology.show', compact('client', 'batch', 'switches', 'okCount', 'failCount'));
    }

    /**
     * GET /areas/clients/{client}/batches/{batch}/topology
     * Diagrama vis-network interactivo del área.
     */
    public function topology(Client $client, UploadBatch $batch)
    {
        abort_if($batch->client_id !== $client->id, 404);

        $switches = $batch->switches()
            ->where('parse_status', 'ok')
            ->with('batch:id,name')
            ->get(['id', 'sys_name', 'system_mac', 'system_type', 'management_ip',
                   'is_stacked', 'stack_topology', 'stack_members', 'upload_batch_id']);

        $switchIds = $switches->pluck('id')->toArray();

        ['nodes' => $nodes, 'graphEdges' => $graphEdges,
         'primaryNode' => $primaryNode, 'memberNodeOf' => $memberNodeOf] = $this->buildGraphData($switches, $switchIds);

        // Ghost nodes: switches externos conectados a este área
        ['ghostNodes' => $ghostNodes, 'ghostEdges' => $ghostEdges] =
            $this->buildGhostData($switchIds, $primaryNode, $memberNodeOf, $client->id);

        $nodes      = $nodes->merge($ghostNodes)->values();
        $graphEdges = $graphEdges->merge($ghostEdges)->values();

        // Pre-build switch list for the sidebar table
        $switchesData = $switches->sortBy('sys_name')->values()->map(fn ($s) => [
            'id'        => $s->id,
            'node_id'   => 'sw' . $s->id,
            'sys_name'  => $s->sys_name  ?? '—',
            'model'     => $s->system_type ?? '—',
            'ip'        => $s->management_ip ?? '—',
            'role'      => TopologyBuilderService::detectRoleStatic($s->sys_name ?? ''),
            'is_stacked'=> (bool) $s->is_stacked,
            'show_url'  => route('admin.switches.show', $s->id),
        ])->values();

        return view('admin.area-topology.topology',
            compact('client', 'batch', 'nodes', 'graphEdges', 'switchesData'));
    }

    /**
     * POST /areas/clients/{client}/rebuild-connections
     * Re-analiza conexiones cross-área del cliente y rellena dst_switch_id nulos.
     */
    public function rebuildConnections(Client $client, ConnectionResolverService $resolver)
    {
        $stats = $resolver->resolveForClient($client->id);
        return response()->json([
            'ok'   => true,
            'stats'=> $stats,
            'msg'  => "Análisis completado: {$stats['newly_resolved']} conexiones cross-área resueltas"
                    . ($stats['still_unresolved'] > 0 ? ", {$stats['still_unresolved']} sin match en el sistema." : "."),
        ]);
    }

    /**
     * GET /areas/clients/{client}/global
     * Diagrama global con un cluster colapsable por área + conexiones inter-área.
     */
    public function global(Client $client)
    {
        // Cargar todos los batches del cliente con sus switches (ok)
        $batches = $client->batches()
            ->with(['switches' => fn ($q) => $q->where('parse_status', 'ok')
                ->select(['id', 'sys_name', 'system_mac', 'system_type', 'management_ip',
                          'is_stacked', 'stack_topology', 'stack_members', 'upload_batch_id'])])
            ->get(['id', 'name', 'created_at']);

        $allNodes      = collect();
        $intraEdges    = collect();   // dentro del mismo área
        $primaryNode   = [];
        $memberNodeOf  = [];
        $batchNodeMap  = [];          // batch_id → [node_ids]
        $batchColors   = [];          // batch_id → color (índice consistente)

        $batchColorPalette = [
            ['bg' => '#DBEAFE', 'border' => '#3B82F6'],
            ['bg' => '#D1FAE5', 'border' => '#10B981'],
            ['bg' => '#EDE9FE', 'border' => '#8B5CF6'],
            ['bg' => '#FEF3C7', 'border' => '#F59E0B'],
            ['bg' => '#FCE7F3', 'border' => '#EC4899'],
            ['bg' => '#CFFAFE', 'border' => '#06B6D4'],
            ['bg' => '#FED7AA', 'border' => '#F97316'],
            ['bg' => '#F0FDF4', 'border' => '#22C55E'],
        ];
        $ci = 0;

        foreach ($batches as $batch) {
            $col = $batchColorPalette[$ci % count($batchColorPalette)];
            $batchColors[$batch->id] = $col;
            $ci++;
            $batchNodeMap[$batch->id] = [];

            foreach ($batch->switches as $s) {
                $swRole  = TopologyBuilderService::detectRoleStatic($s->sys_name ?? '');
                $members = $s->is_stacked ? ($s->stack_members ?? []) : [];

                if ($s->is_stacked && count($members) > 1) {
                    $memberNodeOf[$s->id] = [];
                    $masterNodeId = null;

                    foreach ($members as $member) {
                        $slot   = $member['slot'] ?? null;
                        $nodeId = "sw{$s->id}_slot{$slot}";

                        $allNodes->push([
                            'id'        => $nodeId,
                            'label'     => ($s->sys_name ?? $s->system_mac) . "\nSlot-{$slot}",
                            'title'     => ($s->system_type ?? '-') . "\nIP: " . ($s->management_ip ?? '-'),
                            'color'     => ['background' => $col['bg'], 'border' => $col['border'],
                                            'highlight' => ['background' => $col['bg'], 'border' => $col['border']]],
                            'role'      => $swRole,
                            'is_stacked'=> true,
                            'switch_id' => $s->id,
                            'batch_id'  => $batch->id,
                            'batch_name'=> $batch->name,
                        ]);

                        $batchNodeMap[$batch->id][] = $nodeId;
                        if ($slot !== null) $memberNodeOf[$s->id][$slot] = $nodeId;
                        if (($member['role'] ?? null) === 'Master') $masterNodeId = $nodeId;
                    }

                    $primaryNode[$s->id] = $masterNodeId ?? (reset($memberNodeOf[$s->id]) ?: null);

                    $slotIds = array_values($memberNodeOf[$s->id]);
                    for ($i = 0; $i < count($slotIds) - 1; $i++) {
                        $intraEdges->push([
                            'from' => $slotIds[$i], 'to' => $slotIds[$i + 1],
                            'dashes' => true, 'label' => '', 'batch_id' => $batch->id,
                            'color' => ['color' => '#CBD5E1'], 'arrows' => '', 'length' => 90,
                        ]);
                    }
                } else {
                    $nodeId = "sw{$s->id}";
                    $allNodes->push([
                        'id'        => $nodeId,
                        'label'     => $s->sys_name ?? $s->system_mac ?? "Switch-{$s->id}",
                        'title'     => ($s->system_type ?? '-') . "\nIP: " . ($s->management_ip ?? '-'),
                        'color'     => ['background' => $col['bg'], 'border' => $col['border'],
                                        'highlight' => ['background' => $col['bg'], 'border' => $col['border']]],
                        'role'      => $swRole,
                        'is_stacked'=> false,
                        'switch_id' => $s->id,
                        'batch_id'  => $batch->id,
                        'batch_name'=> $batch->name,
                    ]);
                    $batchNodeMap[$batch->id][] = $nodeId;
                    $primaryNode[$s->id] = $nodeId;
                }
            }
        }

        // Todas las conexiones entre switches del cliente
        $allSwitchIds = $allNodes->pluck('switch_id')->unique()->filter()->values()->toArray();
        $connections  = SwitcheConnection::whereIn('src_switch_id', $allSwitchIds)
            ->whereIn('dst_switch_id', $allSwitchIds)
            ->get();

        // Mapa switch_id → batch_id
        $switchBatch = [];
        foreach ($batches as $batch) {
            foreach ($batch->switches as $s) {
                $switchBatch[$s->id] = $batch->id;
            }
        }

        $seen      = [];
        $allEdges  = $intraEdges->values()->toArray();

        foreach ($connections as $c) {
            $from = $this->resolveNodeId($c->src_switch_id, $c->src_port, $primaryNode, $memberNodeOf);
            $to   = $this->resolveNodeId($c->dst_switch_id, $c->dst_port, $primaryNode, $memberNodeOf);
            if (!$from || !$to) continue;

            $key = $from < $to ? "{$from}|{$to}" : "{$to}|{$from}";
            $srcBatch = $switchBatch[$c->src_switch_id] ?? null;
            $dstBatch = $switchBatch[$c->dst_switch_id] ?? null;
            $interArea = $srcBatch !== $dstBatch;

            if (!array_key_exists($key, $seen)) {
                $seen[$key] = count($allEdges);
                $allEdges[] = [
                    'from'      => $from,
                    'to'        => $to,
                    'label'     => "{$c->src_port}↔{$c->dst_port}",
                    'inter_area'=> $interArea,
                    'color'     => $interArea
                        ? ['color' => '#F97316', 'highlight' => '#EA580C', 'hover' => '#EA580C']
                        : ['color' => '#94A3B8', 'highlight' => '#3B82F6', 'hover' => '#3B82F6'],
                    'width'     => $interArea ? 2.5 : 1.5,
                    'dashes'    => $interArea ? [8, 4] : false,
                ];
            } else {
                $label = "{$c->src_port}↔{$c->dst_port}";
                if (!str_contains($allEdges[$seen[$key]]['label'], $label)) {
                    $allEdges[$seen[$key]]['label'] .= "\n" . $label;
                }
            }
        }

        // ── Resolución on-the-fly: conexiones no resueltas (dst_switch_id = null) ──────────
        // El global view usa whereIn(dst_switch_id) que excluye NULLs.
        // Aquí las emparejamos por neighbor_name o dst_mac contra los switches del cliente.
        $nameMap = [];
        $macMap  = [];
        foreach ($batches as $batch) {
            foreach ($batch->switches as $s) {
                if ($s->sys_name) {
                    $nameMap[strtolower(trim($s->sys_name))] = $s->id;
                }
                if ($s->system_mac) {
                    $normalKey = strtolower(preg_replace('/[^a-f0-9]/i', '', $s->system_mac));
                    if ($normalKey) $macMap[$normalKey] = $s->id;
                }
            }
        }

        $unresolvedConns = SwitcheConnection::whereIn('src_switch_id', $allSwitchIds)
            ->whereNull('dst_switch_id')
            ->get(['src_switch_id', 'src_port', 'dst_mac', 'dst_port', 'neighbor_name']);

        foreach ($unresolvedConns as $c) {
            // Intentar match por nombre, luego por MAC
            $resolvedDstId = null;
            if ($c->neighbor_name) {
                $resolvedDstId = $nameMap[strtolower(trim($c->neighbor_name))] ?? null;
            }
            if (!$resolvedDstId && $c->dst_mac) {
                $normalMac     = strtolower(preg_replace('/[^a-f0-9]/i', '', $c->dst_mac));
                $resolvedDstId = $macMap[$normalMac] ?? null;
            }

            // Ignorar si no se resolvió o es el mismo switch origen
            if (!$resolvedDstId || $resolvedDstId === $c->src_switch_id) continue;

            $from = $this->resolveNodeId($c->src_switch_id, $c->src_port, $primaryNode, $memberNodeOf);
            $to   = $this->resolveNodeId($resolvedDstId,    $c->dst_port, $primaryNode, $memberNodeOf);
            if (!$from || !$to) continue;

            $key       = $from < $to ? "{$from}|{$to}" : "{$to}|{$from}";
            $srcBatch  = $switchBatch[$c->src_switch_id] ?? null;
            $dstBatch  = $switchBatch[$resolvedDstId]    ?? null;
            $interArea = $srcBatch !== $dstBatch;

            if (!array_key_exists($key, $seen)) {
                $seen[$key] = count($allEdges);
                $allEdges[] = [
                    'from'       => $from,
                    'to'         => $to,
                    'label'      => "{$c->src_port}↔{$c->dst_port}",
                    'inter_area' => $interArea,
                    'color'      => $interArea
                        ? ['color' => '#F97316', 'highlight' => '#EA580C', 'hover' => '#EA580C']
                        : ['color' => '#94A3B8', 'highlight' => '#3B82F6', 'hover' => '#3B82F6'],
                    'width'      => $interArea ? 2.5 : 1.5,
                    'dashes'     => $interArea ? [8, 4] : false,
                ];
            } else {
                $label = "{$c->src_port}↔{$c->dst_port}";
                if (!str_contains($allEdges[$seen[$key]]['label'], $label)) {
                    $allEdges[$seen[$key]]['label'] .= "\n" . $label;
                }
            }
        }

        // Metadata de batches para JS (mismo índice de color que los nodos)
        $batchesMeta = $batches->map(fn ($b) => [
            'id'         => $b->id,
            'name'       => $b->name,
            'node_ids'   => $batchNodeMap[$b->id] ?? [],
            'sw_count'   => count($batchNodeMap[$b->id] ?? []),
            'color'      => $batchColors[$b->id],
        ])->values();

        return view('admin.area-topology.global', [
            'client'     => $client,
            'batches'    => $batches,
            'nodes'      => $allNodes->values(),
            'graphEdges' => collect($allEdges)->values(),
            'batchesMeta'=> $batchesMeta,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function buildGraphData(Collection $switches, array $switchIds): array
    {
        $nodes        = collect();
        $stackEdges   = collect();
        $primaryNode  = [];
        $memberNodeOf = [];

        $color = ['background' => '#EFF6FF', 'border' => '#3B82F6'];

        foreach ($switches as $s) {
            $members = $s->is_stacked ? ($s->stack_members ?? []) : [];
            $swRole  = TopologyBuilderService::detectRoleStatic($s->sys_name ?? '');

            if ($s->is_stacked && count($members) > 1) {
                $memberNodeOf[$s->id] = [];
                $masterNodeId = null;

                foreach ($members as $member) {
                    $slot   = $member['slot'] ?? null;
                    $nodeId = "sw{$s->id}_slot{$slot}";

                    $nodes->push([
                        'id'        => $nodeId,
                        'label'     => ($s->sys_name ?? $s->system_mac) . "\nSlot-{$slot}",
                        'title'     => ($s->system_type ?? '-') . "\nIP: " . ($s->management_ip ?? '-'),
                        'color'     => $color,
                        'role'      => $swRole,
                        'is_stacked'=> true,
                        'switch_id' => $s->id,
                        'slot'      => $slot,
                        'sys_name'  => $s->sys_name ?? $s->system_mac,
                        'model'     => $s->system_type,
                        'ip'        => $s->management_ip,
                    ]);

                    if ($slot !== null) {
                        $memberNodeOf[$s->id][$slot] = $nodeId;
                    }
                    if (($member['role'] ?? null) === 'Master') {
                        $masterNodeId = $nodeId;
                    }
                }

                $primaryNode[$s->id] = $masterNodeId ?? (reset($memberNodeOf[$s->id]) ?: null);

                $slotIds = array_values($memberNodeOf[$s->id]);
                for ($i = 0; $i < count($slotIds) - 1; $i++) {
                    $stackEdges->push([
                        'from'   => $slotIds[$i],
                        'to'     => $slotIds[$i + 1],
                        'dashes' => true,
                        'color'  => ['color' => '#CBD5E1'],
                        'arrows' => '',
                        'length' => 90,
                        'label'  => '',
                    ]);
                }
            } else {
                $nodeId = "sw{$s->id}";
                $nodes->push([
                    'id'        => $nodeId,
                    'label'     => $s->sys_name ?? $s->system_mac ?? "Switch-{$s->id}",
                    'title'     => ($s->system_type ?? '-') . "\nIP: " . ($s->management_ip ?? '-'),
                    'color'     => $color,
                    'role'      => $swRole,
                    'is_stacked'=> false,
                    'switch_id' => $s->id,
                    'slot'      => null,
                    'sys_name'  => $s->sys_name ?? $s->system_mac,
                    'model'     => $s->system_type,
                    'ip'        => $s->management_ip,
                ]);
                $primaryNode[$s->id] = $nodeId;
            }
        }

        // Intra-area connections only
        $connections = SwitcheConnection::whereIn('src_switch_id', $switchIds)
            ->whereIn('dst_switch_id', $switchIds)
            ->get();

        $seen     = [];
        $dedupArr = [];

        foreach ($connections as $c) {
            $from = $this->resolveNodeId($c->src_switch_id, $c->src_port, $primaryNode, $memberNodeOf);
            $to   = $this->resolveNodeId($c->dst_switch_id, $c->dst_port, $primaryNode, $memberNodeOf);
            if (!$from || !$to) continue;

            $key = $from < $to ? "{$from}|{$to}" : "{$to}|{$from}";
            if (!array_key_exists($key, $seen)) {
                $seen[$key] = count($dedupArr);
                $dedupArr[] = ['from' => $from, 'to' => $to, 'label' => "{$c->src_port}↔{$c->dst_port}"];
            } else {
                $label = "{$c->src_port}↔{$c->dst_port}";
                if (!str_contains($dedupArr[$seen[$key]]['label'], $label)) {
                    $dedupArr[$seen[$key]]['label'] .= "\n" . $label;
                }
            }
        }

        $graphEdges = collect($dedupArr)->merge($stackEdges);

        return [
            'nodes'       => $nodes->values(),
            'graphEdges'  => $graphEdges->values(),
            'primaryNode' => $primaryNode,
            'memberNodeOf'=> $memberNodeOf,
        ];
    }

    /**
     * Construye nodos fantasma (switches externos al área conectados a ella)
     * y las aristas hacia esos nodos.
     */
    private function buildGhostData(
        array $switchIds,
        array $primaryNode,
        array $memberNodeOf,
        int   $clientId
    ): array {
        // Conexiones cuyo origen está en el área pero el destino NO (o es null)
        $externalConns = SwitcheConnection::whereIn('src_switch_id', $switchIds)
            ->where(function ($q) use ($switchIds) {
                $q->whereNotIn('dst_switch_id', $switchIds)
                  ->orWhereNull('dst_switch_id');
            })
            ->with(['dstSwitch:id,sys_name,upload_batch_id,system_type,management_ip',
                    'dstSwitch.batch:id,name,client_id'])
            ->get();

        $ghostNodes = collect();
        $ghostEdges = collect();
        $seenGhosts = [];   // node IDs ya añadidos
        $seenEdges  = [];   // edge keys ya añadidos

        foreach ($externalConns as $conn) {
            $srcNodeId = $this->resolveNodeId(
                $conn->src_switch_id, $conn->src_port, $primaryNode, $memberNodeOf
            );
            if (!$srcNodeId) continue;

            // Determinar ghost ID y metadata
            if ($conn->dst_switch_id && $conn->dstSwitch) {
                // El switch existe en BD: verificar que pertenece al mismo cliente
                if (($conn->dstSwitch->batch?->client_id ?? null) !== $clientId) {
                    continue;  // switch de otro cliente, ignorar
                }
                $ghostId      = "ghost_sw{$conn->dst_switch_id}";
                $ghostLabel   = $conn->dstSwitch->sys_name ?? $conn->neighbor_name ?? $conn->dst_mac;
                $externalArea = $conn->dstSwitch->batch?->name ?? 'Área desconocida';
                $externalAreaId = $conn->dstSwitch->batch?->id;
            } else {
                // Switch no encontrado en BD (dst_switch_id = null)
                $slugMac  = preg_replace('/[^A-Za-z0-9]/', '_', $conn->dst_mac ?? $conn->neighbor_name ?? '');
                $ghostId  = 'ghost_mac_' . $slugMac;
                $ghostLabel   = $conn->neighbor_name ?? $conn->dst_mac ?? 'Desconocido';
                $externalArea = 'Sin match en el sistema';
                $externalAreaId = null;
            }

            // Añadir nodo fantasma (si no existe aún)
            if (!isset($seenGhosts[$ghostId])) {
                $seenGhosts[$ghostId] = true;
                $ghostNodes->push([
                    'id'              => $ghostId,
                    'label'           => $ghostLabel,
                    'title'           => "Switch externo\nÁrea: {$externalArea}",
                    'is_ghost'        => true,
                    'external_area'   => $externalArea,
                    'external_area_id'=> $externalAreaId,
                    'switch_id'       => null,
                ]);
            }

            // Arista hacia el fantasma (dedup por par src→ghost)
            $edgeKey = "{$srcNodeId}|{$ghostId}";
            if (!isset($seenEdges[$edgeKey])) {
                $seenEdges[$edgeKey] = true;
                $ghostEdges->push([
                    'from'     => $srcNodeId,
                    'to'       => $ghostId,
                    'label'    => "{$conn->src_port}↔{$conn->dst_port}",
                    'is_ghost' => true,
                ]);
            }
        }

        return [
            'ghostNodes' => $ghostNodes->values(),
            'ghostEdges' => $ghostEdges->values(),
        ];
    }

    private function resolveNodeId(?int $switchId, ?string $port, array $primaryNode, array $memberNodeOf): ?string
    {
        if (!$switchId) return null;
        if ($port && preg_match('/^(\d+):\d+/', trim($port), $m)) {
            $slot = (int) $m[1];
            if (isset($memberNodeOf[$switchId][$slot])) {
                return $memberNodeOf[$switchId][$slot];
            }
        }
        return $primaryNode[$switchId] ?? null;
    }
}
