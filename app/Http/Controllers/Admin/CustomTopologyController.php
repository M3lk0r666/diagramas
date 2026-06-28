<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Switche;
use App\Models\SwitcheConnection;
use App\Services\TopologyBuilderService;
use Illuminate\Http\Request;

class CustomTopologyController extends Controller
{
    // GET /topology/custom/create
    public function create()
    {
        $switches = Switche::where('parse_status', 'ok')
            ->with('batch:id,name')
            ->orderBy('sys_name')
            ->get(['id', 'sys_name', 'system_type', 'management_ip', 'upload_batch_id', 'is_stacked']);

        // Pre-build the JS-ready array to avoid multi-line fn() inside @json() in Blade
        $switchesData = $switches->map(fn ($s) => [
            'id'         => $s->id,
            'sys_name'   => $s->sys_name    ?? '—',
            'model'      => $s->system_type ?? '—',
            'ip'         => $s->management_ip ?? '—',
            'batch_name' => $s->batch?->name ?? '—',
            'is_stacked' => (bool) $s->is_stacked,
        ])->values();

        return view('admin.custom-topology.create', compact('switchesData'));
    }

    // POST /topology/custom/build
    public function build(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'switch_ids'   => 'required|array|min:1',
            'switch_ids.*' => 'integer',
        ]);

        session(['custom_topology' => [
            'name'       => trim($request->input('name')),
            'switch_ids' => array_map('intval', $request->input('switch_ids')),
        ]]);

        return redirect()->route('admin.topology.custom.show');
    }

    // GET /topology/custom
    public function show(Request $request)
    {
        $topology = session('custom_topology');

        if (!$topology) {
            return redirect()->route('admin.topology.custom.create')
                ->with('info', 'Define tu topología antes de visualizarla.');
        }

        $switchIds = $topology['switch_ids'];
        $name      = $topology['name'];

        $switches = Switche::whereIn('id', $switchIds)
            ->where('parse_status', 'ok')
            ->with('batch:id,name')
            ->get(['id', 'sys_name', 'system_mac', 'system_type', 'upload_batch_id',
                   'is_stacked', 'stack_topology', 'stack_members', 'management_ip', 'serial_number']);

        ['nodes' => $nodes, 'graphEdges' => $graphEdges] = $this->buildGraphData($switches, $switchIds);

        // PNG key and existence
        $pngKey  = $this->pngKey($switchIds);
        $pngPath = storage_path("app/topology/custom/{$pngKey}.png");

        // Pre-build JS-ready switch list to avoid multi-line fn() inside @json() in Blade
        $service      = new TopologyBuilderService();
        $switchesData = $switches->sortBy('sys_name')->values()->map(fn ($s) => [
            'id'         => $s->id,
            'node_id'    => 'sw' . $s->id,
            'sys_name'   => $s->sys_name    ?? '—',
            'model'      => $s->system_type ?? '—',
            'ip'         => $s->management_ip ?? '—',
            'role'       => TopologyBuilderService::detectRoleStatic($s->sys_name ?? ''),
            'is_stacked' => (bool) $s->is_stacked,
            'batch_name' => $s->batch?->name ?? '—',
            'show_url'   => route('admin.switches.show', $s->id),
        ])->values();

        return view('admin.custom-topology.show', [
            'topologyName' => $name,
            'switchIds'    => $switchIds,
            'switches'     => $switches,
            'switchesData' => $switchesData,
            'nodes'        => $nodes,
            'graphEdges'   => $graphEdges,
            'hasImage'     => file_exists($pngPath),
            'pngKey'       => $pngKey,
        ]);
    }

    // POST /topology/custom/generate  (AJAX)
    public function generate(Request $request)
    {
        $topology = session('custom_topology');

        if (!$topology) {
            return response()->json(['error' => 'No hay topología en sesión.'], 422);
        }

        $switchIds = $topology['switch_ids'];
        $name      = $topology['name'];

        $switches = Switche::whereIn('id', $switchIds)
            ->where('parse_status', 'ok')
            ->get();

        $service  = new TopologyBuilderService();
        $topoData = $service->buildForCustom($switches, $name);

        $pngKey = $this->pngKey($switchIds);
        $dir    = storage_path('app/topology/custom');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $jsonPath = "{$dir}/{$pngKey}.json";
        $pngPath  = "{$dir}/{$pngKey}.png";

        file_put_contents($jsonPath, json_encode($topoData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $script   = base_path('scripts/topology_generator.py');
        $iconsDir = base_path('scripts/icons');

        $cmd = 'python '
             . escapeshellarg($script)
             . ' ' . escapeshellarg($jsonPath)
             . ' ' . escapeshellarg($pngPath)
             . ' --icons ' . escapeshellarg($iconsDir)
             . ' 2>&1';

        $output = shell_exec($cmd);

        if (!file_exists($pngPath)) {
            return response()->json(['error' => 'Error al generar el diagrama.', 'output' => $output], 500);
        }

        return response()->json(['ok' => true, 'key' => $pngKey]);
    }

    // GET /topology/custom/image/{key}
    public function image(string $key)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            abort(404);
        }

        $path = storage_path("app/topology/custom/{$key}.png");
        abort_unless(file_exists($path), 404, 'Imagen no generada todavía.');

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-cache']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function pngKey(array $switchIds): string
    {
        $ids = $switchIds;
        sort($ids);
        return md5(implode(',', $ids));
    }

    private function buildGraphData($switches, array $switchIds): array
    {
        $palette = [
            '#DBEAFE', '#D1FAE5', '#FEF3C7', '#FCE7F3', '#EDE9FE',
            '#FFEDD5', '#CFFAFE', '#F0FDF4', '#FDF4FF', '#F0F9FF',
        ];
        $batchColors = [];
        $colorIdx    = 0;

        foreach ($switches->pluck('upload_batch_id')->unique() as $bId) {
            $batchColors[$bId] = $palette[$colorIdx % count($palette)];
            $colorIdx++;
        }

        $nodes        = collect();
        $stackEdges   = collect();
        $primaryNode  = [];
        $memberNodeOf = [];

        foreach ($switches as $s) {
            $color   = ['background' => $batchColors[$s->upload_batch_id] ?? '#EFF6FF', 'border' => '#3B82F6'];
            $members = $s->is_stacked ? ($s->stack_members ?? []) : [];
            $swRole  = TopologyBuilderService::detectRoleStatic($s->sys_name ?? '');

            if ($s->is_stacked && count($members) > 1) {
                $memberNodeOf[$s->id] = [];
                $masterNodeId = null;

                foreach ($members as $member) {
                    $slot    = $member['slot'] ?? null;
                    $nodeId  = "sw{$s->id}_slot{$slot}";
                    $nodes->push([
                        'id'         => $nodeId,
                        'label'      => ($s->sys_name ?? $s->system_mac) . "\nSlot-{$slot}",
                        'title'      => ($s->system_type ?? '-') . "\nIP: " . ($s->management_ip ?? '-'),
                        'batch_id'   => $s->upload_batch_id,
                        'batch_name' => $s->batch?->name ?? '-',
                        'color'      => $color,
                        'role'       => $swRole,
                        'is_stacked' => true,
                        'sys_name'   => $s->sys_name ?? $s->system_mac,
                        'model'      => $s->system_type,
                        'ip'         => $s->management_ip,
                        'slot'       => $slot,
                        'switch_id'  => $s->id,
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
                    ]);
                }
            } else {
                $nodeId = "sw{$s->id}";
                $nodes->push([
                    'id'         => $nodeId,
                    'label'      => $s->sys_name ?? $s->system_mac,
                    'title'      => ($s->system_type ?? '-') . "\nIP: " . ($s->management_ip ?? '-'),
                    'batch_id'   => $s->upload_batch_id,
                    'batch_name' => $s->batch?->name ?? '-',
                    'color'      => $color,
                    'role'       => $swRole,
                    'is_stacked' => false,
                    'sys_name'   => $s->sys_name ?? $s->system_mac,
                    'model'      => $s->system_type,
                    'ip'         => $s->management_ip,
                    'slot'       => null,
                    'switch_id'  => $s->id,
                ]);
                $primaryNode[$s->id] = $nodeId;
            }
        }

        // Edges between selected switches only
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
            'nodes'      => $nodes->values(),
            'graphEdges' => $graphEdges->values(),
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
