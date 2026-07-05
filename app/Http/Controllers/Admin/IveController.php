<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SwitcheConnection;
use App\Models\UploadBatch;
use App\Services\TopologyBuilderService;

/**
 * IVE — Infrastructure Visualization Engine
 *
 * Responsabilidades:
 *   - global()      → Blade shell que monta la app React
 *   - dataGlobal()  → JSON de topología para el TopologyAdapter del frontend
 *
 * RESTRICCIÓN: No modifica IsoTopologyController ni ningún servicio existente.
 * buildTopologyData() es una copia controlada; cuando se retire el POC ISO,
 * este método quedará como única fuente de datos.
 */
class IveController extends Controller
{
    private const BATCH_COLORS = [
        '#3B82F6', '#10B981', '#8B5CF6', '#F59E0B',
        '#EC4899', '#06B6D4', '#F97316', '#22C55E',
    ];

    // ── Views ───────────────────────────────────────────────────────────────

    /**
     * GET /admin/ive
     * Lista de clientes con topología procesada → selección de cliente.
     */
    public function index()
    {
        $clients = Client::with([
            'batches:id,client_id,name,created_at',
            'batches.switches' => fn ($q) => $q
                ->where('parse_status', 'ok')
                ->select(['id', 'upload_batch_id']),
        ])
        ->whereHas('batches.switches', fn ($q) => $q->where('parse_status', 'ok'))
        ->orderBy('name')
        ->get(['id', 'name', 'updated_at']);

        return view('admin.ive.index', compact('clients'));
    }

    /**
     * GET /admin/ive/{client}
     * Shell HTML autónomo (sin sidebar/topbar) que monta la app React IVE.
     * Diseñado para abrirse en una ventana nueva (target="_blank").
     */
    public function global(Client $client)
    {
        return view('admin.ive.global', compact('client'));
    }

    // ── JSON API (Topology Adapter) ─────────────────────────────────────────

    /**
     * GET /admin/ive/{client}/data
     * Retorna el JSON de topología para que el TopologyAdapter lo consuma.
     * El renderer NUNCA llama a este endpoint directamente — solo el Adapter.
     */
    public function dataGlobal(Client $client)
    {
        $batches = $client->batches()
            ->with(['switches' => fn ($q) => $q
                ->where('parse_status', 'ok')
                ->select(['id', 'sys_name', 'system_type', 'management_ip',
                          'system_mac', 'is_stacked', 'upload_batch_id'])])
            ->get(['id', 'name']);

        return response()->json(
            $this->buildTopologyData($batches, isGlobal: true)
        );
    }

    // ── Data builder ────────────────────────────────────────────────────────

    /**
     * Construye el objeto de topología que consume el TopologyAdapter.
     *
     * Formato de salida:
     * {
     *   batches: [{ id, name, color, switches: [...], connections: [...] }],
     *   inter_area_connections: [...]
     * }
     */
    private function buildTopologyData($batches, bool $isGlobal): array
    {
        $batchesData    = [];
        $allSwitchIds   = [];
        $switchBatchMap = [];

        foreach ($batches as $i => $batch) {
            $color     = self::BATCH_COLORS[$i % count(self::BATCH_COLORS)];
            $switchIds = $batch->switches->pluck('id')->toArray();

            $allSwitchIds = array_merge($allSwitchIds, $switchIds);
            foreach ($switchIds as $sid) {
                $switchBatchMap[$sid] = $batch->id;
            }

            // Conexiones internas del área (deduplicadas)
            $intraConns  = SwitcheConnection::whereIn('src_switch_id', $switchIds)
                ->whereIn('dst_switch_id', $switchIds)
                ->get(['src_switch_id', 'dst_switch_id', 'src_port', 'dst_port']);

            $connections = [];
            $seen        = [];
            foreach ($intraConns as $c) {
                $key = min($c->src_switch_id, $c->dst_switch_id)
                     . '_' . max($c->src_switch_id, $c->dst_switch_id);
                if (!isset($seen[$key])) {
                    $seen[$key]    = true;
                    $connections[] = [
                        'src_id'   => $c->src_switch_id,
                        'dst_id'   => $c->dst_switch_id,
                        'src_port' => $c->src_port,
                        'dst_port' => $c->dst_port,
                    ];
                }
            }

            $batchesData[] = [
                'id'          => $batch->id,
                'name'        => $batch->name,
                'color'       => $color,
                'switches'    => $batch->switches->map(function ($sw) {
                    $role = TopologyBuilderService::detectRoleStatic($sw->sys_name ?? '');
                    return [
                        'id'            => $sw->id,
                        'sys_name'      => $sw->sys_name,
                        'role'          => $role,
                        'system_type'   => $sw->system_type,
                        'management_ip' => $sw->management_ip,
                        'system_mac'    => $sw->system_mac,
                        'is_stacked'    => (bool) $sw->is_stacked,
                    ];
                })->values()->all(),
                'connections' => $connections,
            ];
        }

        // Conexiones inter-área (solo vista global)
        $interAreaConns = [];
        if ($isGlobal && count($allSwitchIds) > 1) {
            $crossConns = SwitcheConnection::whereIn('src_switch_id', $allSwitchIds)
                ->whereIn('dst_switch_id', $allSwitchIds)
                ->get(['src_switch_id', 'dst_switch_id', 'src_port', 'dst_port']);

            $seen = [];
            foreach ($crossConns as $c) {
                $srcBatch = $switchBatchMap[$c->src_switch_id] ?? null;
                $dstBatch = $switchBatchMap[$c->dst_switch_id] ?? null;
                if (!$srcBatch || !$dstBatch || $srcBatch === $dstBatch) continue;

                $key = min($c->src_switch_id, $c->dst_switch_id)
                     . '_' . max($c->src_switch_id, $c->dst_switch_id);
                if (!isset($seen[$key])) {
                    $seen[$key]       = true;
                    $interAreaConns[] = [
                        'src_id'    => $c->src_switch_id,
                        'dst_id'    => $c->dst_switch_id,
                        'src_port'  => $c->src_port,
                        'dst_port'  => $c->dst_port,
                        'src_batch' => $srcBatch,
                        'dst_batch' => $dstBatch,
                    ];
                }
            }
        }

        return [
            'batches'                => $batchesData,
            'inter_area_connections' => $interAreaConns,
        ];
    }
}
