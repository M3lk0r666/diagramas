<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Switche;
use App\Models\SwitcheConnection;
use App\Services\TopologyBuilderService;
use Illuminate\Http\Request;

class GoJsTopologyController extends Controller
{
    // GET /topology/gojs/create
    public function create()
    {
        $switches = Switche::where('parse_status', 'ok')
            ->with('batch:id,name')
            ->orderBy('sys_name')
            ->get(['id', 'sys_name', 'system_type', 'management_ip', 'upload_batch_id', 'is_stacked']);

        $switchesData = $switches->map(fn ($s) => [
            'id'         => $s->id,
            'sys_name'   => $s->sys_name    ?? '—',
            'model'      => $s->system_type ?? '—',
            'ip'         => $s->management_ip ?? '—',
            'batch_name' => $s->batch?->name ?? '—',
            'is_stacked' => (bool) $s->is_stacked,
        ])->values();

        return view('admin.gojs-topology.create', compact('switchesData'));
    }

    // GET /topology/gojs/blank — editor vacío sin switches pre-cargados
    public function blank()
    {
        return view('admin.gojs-topology.editor', [
            'topologyName' => 'Nuevo diagrama',
            'switchIds'    => [],
            'nodesData'    => collect(),
            'linksData'    => collect(),
            'hasImage'     => false,
            'pngKey'       => md5('gojs_blank_' . now()->timestamp),
        ]);
    }

    // POST /topology/gojs/build
    public function build(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'switch_ids'   => 'required|array|min:1',
            'switch_ids.*' => 'integer',
        ]);

        session(['gojs_topology' => [
            'name'       => trim($request->input('name')),
            'switch_ids' => array_map('intval', $request->input('switch_ids')),
        ]]);

        return redirect()->route('admin.topology.gojs.show');
    }

    // GET /topology/gojs
    public function show()
    {
        $topology = session('gojs_topology');

        if (!$topology) {
            return redirect()->route('admin.topology.gojs.create')
                ->with('info', 'Selecciona los switches para tu topología GoJS.');
        }

        $switchIds = $topology['switch_ids'];
        $name      = $topology['name'];

        $switches = Switche::whereIn('id', $switchIds)
            ->where('parse_status', 'ok')
            ->get(['id', 'sys_name', 'system_mac', 'system_type', 'upload_batch_id',
                   'is_stacked', 'management_ip', 'serial_number']);

        // Build GoJS nodeDataArray
        $switchIndex = [];
        $nodesData   = $switches->map(function ($s) use (&$switchIndex) {
            $key               = 'sw' . $s->id;
            $switchIndex[$s->id] = $key;
            return [
                'key'       => $key,
                'text'      => $s->sys_name ?? "Switch-{$s->id}",
                'category'  => TopologyBuilderService::detectRoleStatic($s->sys_name ?? ''),
                'ip'        => $s->management_ip ?? '',
                'model'     => $s->system_type ?? '',
                'switch_id' => $s->id,
            ];
        })->values();

        // Build GoJS linkDataArray (deduplicated)
        $connections = SwitcheConnection::whereIn('src_switch_id', $switchIds)
            ->whereIn('dst_switch_id', $switchIds)
            ->get();

        $seen      = [];
        $linksData = collect();
        $linkIdx   = 0;

        foreach ($connections as $c) {
            $from = $switchIndex[$c->src_switch_id] ?? null;
            $to   = $switchIndex[$c->dst_switch_id] ?? null;
            if (!$from || !$to || $from === $to) continue;

            $key = $from < $to ? "{$from}|{$to}" : "{$to}|{$from}";
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $linksData->push([
                'key'  => 'link' . $linkIdx++,
                'from' => $from,
                'to'   => $to,
                'text' => trim("{$c->src_port} ↔ {$c->dst_port}"),
            ]);
        }

        $pngKey  = $this->pngKey($switchIds);
        $pngPath = storage_path("app/topology/gojs/{$pngKey}.png");

        return view('admin.gojs-topology.editor', [
            'topologyName' => $name,
            'switchIds'    => $switchIds,
            'nodesData'    => $nodesData,
            'linksData'    => $linksData->values(),
            'hasImage'     => file_exists($pngPath),
            'pngKey'       => $pngKey,
        ]);
    }

    // POST /topology/gojs/export  (AJAX — recibe base64 PNG generado por GoJS)
    public function export(Request $request)
    {
        $topology = session('gojs_topology');
        if (!$topology) {
            return response()->json(['error' => 'No hay topología en sesión.'], 422);
        }

        $imageData = $request->input('image');
        if (!$imageData) {
            return response()->json(['error' => 'No se recibió imagen.'], 422);
        }

        // Strip data URL prefix (data:image/png;base64,...)
        $base64  = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
        $decoded = base64_decode($base64);

        if (!$decoded) {
            return response()->json(['error' => 'Imagen inválida (base64 incorrecto).'], 422);
        }

        $pngKey = $this->pngKey($topology['switch_ids']);
        $dir    = storage_path('app/topology/gojs');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents("{$dir}/{$pngKey}.png", $decoded);

        return response()->json([
            'ok'  => true,
            'key' => $pngKey,
            'url' => route('admin.topology.gojs.image', $pngKey),
        ]);
    }

    // GET /topology/gojs/image/{key}
    public function image(string $key)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            abort(404);
        }

        $path = storage_path("app/topology/gojs/{$key}.png");
        abort_unless(file_exists($path), 404, 'Imagen no generada todavía.');

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-cache']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function pngKey(array $switchIds): string
    {
        $ids = $switchIds;
        sort($ids);
        return md5('gojs_' . implode(',', $ids));
    }
}
