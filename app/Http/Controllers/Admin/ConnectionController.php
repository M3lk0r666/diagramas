<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SwitcheConnection;
use App\Models\Switche;
use App\Services\TopologyBuilderService;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    // GET /topology/icons/{filename} → sirve iconos PNG desde scripts/icons/
    public function serveIcon(string $filename)
    {
        $filename = basename($filename);
        $path = base_path("scripts/icons/{$filename}");
        abort_if(!file_exists($path) || !str_ends_with($filename, '.png'), 404);
        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    // GET /topology → vista del grafo de conexiones + tabla
    public function index(Request $request)
    {
        $clientId = $request->query('client');
        $batchId  = $request->query('batch');
        $data     = $this->buildGraphData($clientId, $batchId);

        return view('admin.topology.index', $data);
    }

    // GET /topology/full → solo el grafo a pantalla completa, con filtros
    public function full(Request $request)
    {
        $clientId = $request->query('client');
        $batchId  = $request->query('batch');
        $data     = $this->buildGraphData($clientId, $batchId);

        return view('admin.topology.full', $data);
    }

    /**
     * Construye nodos/aristas para vis-network y filas para la tabla.
     *
     * Para switches en stack, se genera un nodo por cada miembro físico
     * (slot) agrupado visualmente (mismo color + aristas internas que
     * representan la unión física del stack). Las conexiones EDP se
     * "anclan" al miembro correspondiente cuando el puerto trae notación
     * de stack ("slot:puerto"); si no, se usan en el miembro maestro.
     */
    private function buildGraphData(?string $clientId = null, ?string $batchId = null): array
    {
        $switches = Switche::with('batch:id,name,client_id')
            ->where('parse_status', 'ok')
            ->when($clientId, fn($q) => $q->whereHas('batch', fn($b) => $b->where('client_id', $clientId)))
            ->when($batchId,  fn($q) => $q->where('upload_batch_id', $batchId))
            ->get(['id','sys_name','system_mac','system_type','upload_batch_id',
                   'is_stacked','stack_topology','stack_members','management_ip','serial_number']);

        $connections = SwitcheConnection::with([
            'srcSwitch:id,sys_name,upload_batch_id',
            'srcSwitch.batch:id,name',
            'dstSwitch:id,sys_name',
        ])->get();

        // Paleta de colores por lote (hasta 10 lotes distintos)
        $palette = [
            '#DBEAFE','#D1FAE5','#FEF3C7','#FCE7F3','#EDE9FE',
            '#FFEDD5','#CFFAFE','#F0FDF4','#FDF4FF','#F0F9FF',
        ];
        $batchColors = [];
        $colorIdx    = 0;
        foreach ($switches->pluck('upload_batch_id')->unique() as $batchId) {
            $batchColors[$batchId] = $palette[$colorIdx % count($palette)];
            $colorIdx++;
        }

        $nodes        = collect();
        $stackEdges   = collect(); // aristas internas que unen los miembros de un stack
        $primaryNode  = [];        // switchId => nodeId del nodo "principal" (master o el switch mismo)
        $memberNodeOf = [];        // switchId => [slot => nodeId]

        foreach ($switches as $s) {
            $color = [
                'background' => $batchColors[$s->upload_batch_id] ?? '#EFF6FF',
                'border'     => '#3B82F6',
            ];
            $members = $s->is_stacked ? ($s->stack_members ?? []) : [];

            $swRole = TopologyBuilderService::detectRoleStatic($s->sys_name ?? '');

            if ($s->is_stacked && count($members) > 1) {
                $memberNodeOf[$s->id] = [];
                $masterNodeId = null;

                foreach ($members as $member) {
                    $slot      = $member['slot'] ?? null;
                    $nodeId    = "sw{$s->id}_slot{$slot}";
                    $slotRole  = $member['role'] ?? null;
                    $label     = ($s->sys_name ?? $s->system_mac) . "\nSlot-{$slot}" . ($slotRole ? " ({$slotRole})" : '');

                    $nodes->push([
                        'id'         => $nodeId,
                        'label'      => $label,
                        'title'      => $this->buildNodeTitle($s, $member['serial_number'] ?? null),
                        'batch_id'   => $s->upload_batch_id,
                        'group'      => "stack-{$s->id}",
                        'color'      => $color,
                        'role'       => $swRole,
                        'is_stacked' => true,
                        'sys_name'   => $s->sys_name ?? $s->system_mac,
                        'model'      => $s->system_type,
                        'slot'       => $slot,
                    ]);

                    if ($slot !== null) {
                        $memberNodeOf[$s->id][$slot] = $nodeId;
                    }

                    if ($slotRole === 'Master') {
                        $masterNodeId = $nodeId;
                    }
                }

                // Nodo principal: el master, o si no se identificó, el primero
                $primaryNode[$s->id] = $masterNodeId ?? (reset($memberNodeOf[$s->id]) ?: null);

                // Aristas internas que representan la unión física del stack
                $slotIds = array_values($memberNodeOf[$s->id]);
                for ($i = 0; $i < count($slotIds) - 1; $i++) {
                    $stackEdges->push([
                        'from'   => $slotIds[$i],
                        'to'     => $slotIds[$i + 1],
                        'dashes' => true,
                        'color'  => ['color' => '#CBD5E1'],
                        'arrows' => '',
                        'length' => 90,
                    ]);
                }
                // Si la topología es anillo, cerrar el ciclo
                if (count($slotIds) > 2 && stripos($s->stack_topology ?? '', 'ring') !== false) {
                    $stackEdges->push([
                        'from'   => $slotIds[count($slotIds) - 1],
                        'to'     => $slotIds[0],
                        'dashes' => true,
                        'color'  => ['color' => '#CBD5E1'],
                        'arrows' => '',
                        'length' => 90,
                    ]);
                }

            } else {
                // Switch standalone (o stack sin info de miembros): un solo nodo
                $nodeId = "sw{$s->id}";
                $nodes->push([
                    'id'         => $nodeId,
                    'label'      => $s->sys_name ?? $s->system_mac,
                    'title'      => $this->buildNodeTitle($s, $s->serial_number),
                    'batch_id'   => $s->upload_batch_id,
                    'color'      => $color,
                    'role'       => $swRole,
                    'is_stacked' => false,
                    'sys_name'   => $s->sys_name ?? $s->system_mac,
                    'model'      => $s->system_type,
                    'slot'       => null,
                ]);
                $primaryNode[$s->id] = $nodeId;
            }
        }

        // ── Aristas de conexión EDP, ancladas al miembro correspondiente ──
        // Deduplicar pares bidireccionales: A→B y B→A se fusionan en una sola
        // arista sin dirección con ambas etiquetas de puerto.
        $rawEdges = $connections->map(function ($c) use ($primaryNode, $memberNodeOf) {
            return [
                'from'  => $this->resolveNodeId($c->src_switch_id, $c->src_port, $primaryNode, $memberNodeOf),
                'to'    => $this->resolveNodeId($c->dst_switch_id, $c->dst_port, $primaryNode, $memberNodeOf),
                'label' => "{$c->src_port}↔{$c->dst_port}",
            ];
        })->filter(fn($e) => $e['from'] && $e['to'])->values();

        $seen       = [];
        $dedupArr   = [];
        foreach ($rawEdges as $e) {
            [$a, $b] = [$e['from'], $e['to']];
            $key = $a < $b ? "{$a}|{$b}" : "{$b}|{$a}";
            if (!array_key_exists($key, $seen)) {
                $seen[$key] = count($dedupArr);
                $dedupArr[] = $e;
            } else {
                // Agregar el puerto del sentido inverso si no está ya en la etiqueta
                $idx = $seen[$key];
                if (!str_contains($dedupArr[$idx]['label'], $e['label'])) {
                    $dedupArr[$idx]['label'] .= "\n" . $e['label'];
                }
            }
        }

        $graphEdges = collect($dedupArr)->merge($stackEdges);

        // Filas para la tabla
        $edges = $connections->map(fn($c) => [
            'src_name'   => $c->srcSwitch?->sys_name,
            'src_mac'    => $c->src_mac,
            'src_port'   => $c->src_port,
            'dst_name'   => $c->dstSwitch?->sys_name,
            'dst_mac'    => $c->dst_mac,
            'dst_port'   => $c->dst_port,
            'num_vlans'  => $c->num_vlans,
            'batch_name' => $c->srcSwitch?->batch?->name,
            'batch_id'   => $c->srcSwitch?->upload_batch_id,
        ]);

        // Lista de lotes únicos para el filtro
        $batches = $switches->map(fn($s) => [
            'id'   => $s->upload_batch_id,
            'name' => $s->batch?->name ?? 'Sin nombre',
        ])->unique('id')->sortBy('name')->values();

        // Todos los clientes para el filtro jerárquico
        $clients = Client::orderBy('name')->get(['id', 'name']);

        return [
            'nodes'       => $nodes->values(),
            'graphEdges'  => $graphEdges->values(),
            'edges'       => $edges,
            'batches'     => $batches,
            'batchColors' => $batchColors,
            'clients'     => $clients,
            'activeClient'=> $clientId,
            'activeBatch' => $batchId,
        ];
    }

    /**
     * Resuelve a qué nodo (miembro físico o principal) pertenece un extremo
     * de la conexión, según la notación del puerto ("slot:puerto" o simple).
     */
    private function resolveNodeId(?int $switchId, ?string $port, array $primaryNode, array $memberNodeOf): ?string
    {
        if (!$switchId) {
            return null;
        }

        if ($port && preg_match('/^(\d+):\d+/', trim($port), $m)) {
            $slot = (int) $m[1];
            if (isset($memberNodeOf[$switchId][$slot])) {
                return $memberNodeOf[$switchId][$slot];
            }
        }

        return $primaryNode[$switchId] ?? null;
    }

    /**
     * Construye el texto del tooltip (title) de un nodo:
     *   1. Modelo del switch (+ topología si está en stack)
     *   2. Nombre del diagrama/lote
     *   3. IP de gestión
     *   4. Número de serie
     */
    private function buildNodeTitle(Switche $s, ?string $serial): string
    {
        $lines = [];

        $modelLine = $s->system_type ?? '—';
        if ($s->is_stacked && ($topo = $this->extractTopologyValue($s->stack_topology))) {
            $modelLine .= " (Stack — Topología: {$topo})";
        }
        $lines[] = $modelLine;

        $lines[] = 'Diagrama: ' . ($s->batch?->name ?? '—');
        $lines[] = 'IP gestión: ' . ($s->management_ip ?? '—');
        $lines[] = 'Serie: ' . ($serial ?? '—');

        return implode("\n", $lines);
    }

    /**
     * Extrae el valor de topología (Ring / Daisy-Chain) preferentemente
     * de la línea "Active Topology is a ...", del texto crudo guardado
     * en stack_topology ("Stack Topology is a Ring\nActive Topology is a Ring").
     */
    private function extractTopologyValue(?string $rawTopology): ?string
    {
        if (!$rawTopology) {
            return null;
        }

        if (preg_match('/Active\s+Topology\s+is\s+a\s+([A-Za-z][A-Za-z\-]*)/i', $rawTopology, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/Stack\s+Topology\s+is\s+a\s+([A-Za-z][A-Za-z\-]*)/i', $rawTopology, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
