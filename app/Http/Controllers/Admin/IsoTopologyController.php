<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SwitcheConnection;
use App\Models\UploadBatch;
use App\Services\TopologyBuilderService;

class IsoTopologyController extends Controller
{
    private const BATCH_COLORS = [
        '#3B82F6', '#10B981', '#8B5CF6', '#F59E0B',
        '#EC4899', '#06B6D4', '#F97316', '#22C55E',
    ];

    private const ICON_MAP = [
        'core'         => 'core_switch.png',
        'backbone'     => 'backbone_switch.png',
        'distribution' => 'dist_switch.png',
        'access'       => 'access_switch.png',
    ];

    /**
     * GET /admin/iso/{client}
     * Vista isométrica global: todos los batches del cliente.
     */
    public function global(Client $client)
    {
        $batches = $client->batches()
            ->with(['switches' => fn ($q) => $q
                ->where('parse_status', 'ok')
                ->select(['id', 'sys_name', 'system_type', 'management_ip',
                          'system_mac', 'is_stacked', 'upload_batch_id'])])
            ->get(['id', 'name']);

        $data = $this->buildData($batches, isGlobal: true);

        return view('admin.iso.global', compact('client', 'data'));
    }

    /**
     * GET /admin/iso/{client}/{batch}
     * Vista isométrica de un área específica.
     */
    public function area(Client $client, UploadBatch $batch)
    {
        abort_if($batch->client_id !== $client->id, 404);

        $batch->load(['switches' => fn ($q) => $q
            ->where('parse_status', 'ok')
            ->select(['id', 'sys_name', 'system_type', 'management_ip',
                      'system_mac', 'is_stacked', 'upload_batch_id'])]);

        $data = $this->buildData(collect([$batch]), isGlobal: false);

        return view('admin.iso.area', compact('client', 'batch', 'data'));
    }

    // ── Shared data builder ──────────────────────────────────────────────────

    private function buildData($batches, bool $isGlobal): array
    {
        $batchesData    = [];
        $allSwitchIds   = [];
        $switchBatchMap = [];   // switchId → batchId

        foreach ($batches as $i => $batch) {
            $color     = self::BATCH_COLORS[$i % count(self::BATCH_COLORS)];
            $switchIds = $batch->switches->pluck('id')->toArray();

            $allSwitchIds = array_merge($allSwitchIds, $switchIds);
            foreach ($switchIds as $sid) {
                $switchBatchMap[$sid] = $batch->id;
            }

            // Intra-area connections (deduplicated)
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
                        'src_id'       => $c->src_switch_id,
                        'dst_id'       => $c->dst_switch_id,
                        'src_port'     => $c->src_port,
                        'dst_port'     => $c->dst_port,
                        'is_inter_area'=> false,
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
                        'icon'          => self::ICON_MAP[$role] ?? 'access_switch.png',
                    ];
                })->values()->all(),
                'connections' => $connections,
            ];
        }

        // Inter-area connections (global view only)
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
