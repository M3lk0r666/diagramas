<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DiagramProject;
use App\Models\Switche;
use App\Models\SwitcheConnection;
use App\Models\UploadBatch;
use App\Services\TopologyBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DiagramAssemblerController extends Controller
{
    // ── GET /admin/assembler ──────────────────────────────────────────────────
    public function index()
    {
        $projects = DiagramProject::with('client')
            ->latest()
            ->paginate(24);

        return view('admin.assembler.index', compact('projects'));
    }

    // ── GET /admin/assembler/create ───────────────────────────────────────────
    public function create()
    {
        $clients = Client::orderBy('name')->get(['id', 'name']);
        return view('admin.assembler.create', compact('clients'));
    }

    // ── POST /admin/assembler ─────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:120',
            'client_id' => 'required|exists:clients,id',
            'type'      => 'nullable|in:png,vectorial',
        ]);

        $project = DiagramProject::create([
            'name'        => $validated['name'],
            'client_id'   => $validated['client_id'],
            'type'        => $validated['type'] ?? 'png',
            'canvas_json' => null,
        ]);

        if ($project->type === 'vectorial') {
            return redirect()->route('admin.assembler.vectorial', $project)
                ->with('success', 'Diagrama vectorial creado.');
        }

        return redirect()->route('admin.assembler.edit', $project)
            ->with('success', 'Proyecto creado. ¡Empieza a ensamblar!');
    }

    // ── GET /admin/assembler/{project} (PNG editor) ───────────────────────────
    public function edit(DiagramProject $project)
    {
        $project->load('client');
        return view('admin.assembler.editor', compact('project'));
    }

    // ── GET /admin/assembler/{project}/vectorial ──────────────────────────────
    public function vectorialEditor(DiagramProject $project)
    {
        $project->load('client');
        return view('admin.assembler.vectorial', compact('project'));
    }

    // ── PUT /admin/assembler/{project} ────────────────────────────────────────
    public function update(Request $request, DiagramProject $project)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:120',
            'canvas_json' => 'nullable|string',
        ]);

        if (isset($validated['name'])) {
            $project->name = $validated['name'];
        }

        if (array_key_exists('canvas_json', $validated)) {
            // Decode JSON string → array (the model cast serialises it back on save)
            $project->canvas_json = $validated['canvas_json'] !== null
                ? json_decode($validated['canvas_json'], true)
                : null;
        }

        $project->save();

        return response()->json(['ok' => true, 'saved_at' => now()->toIso8601String()]);
    }

    // ── DELETE /admin/assembler/{project} ─────────────────────────────────────
    public function destroy(DiagramProject $project)
    {
        $project->delete();
        return redirect()->route('admin.assembler.index')
            ->with('success', 'Proyecto eliminado.');
    }

    // ── POST /admin/assembler/{project}/export ────────────────────────────────
    public function export(Request $request, DiagramProject $project)
    {
        $format = $request->input('format', 'png'); // png | svg | pdf

        if ($format === 'png') {
            return $this->exportPng($project);
        }

        // SVG and PDF are handled client-side (Fabric.js / jsPDF)
        return response()->json(['ok' => false, 'error' => 'Format not supported server-side'], 400);
    }

    // ── GET /admin/assembler/{project}/library ────────────────────────────────
    public function library(DiagramProject $project)
    {
        // Images are on the DEFAULT disk (storage/app/), served via controller routes.
        // topology_clusters is an array of OBJECTS: [{image_path, label, nodes, ...}]
        $batches = UploadBatch::where('client_id', $project->client_id)
            ->orderBy('name')
            ->get(['id', 'name', 'topology_image_path', 'topology_clusters', 'created_at']);

        $images = [];

        foreach ($batches as $batch) {
            // ── Topology PNG (main) ──
            if ($batch->topology_image_path && Storage::exists($batch->topology_image_path)) {
                $images[] = [
                    'id'         => "img_batch_{$batch->id}_topology",
                    'src'        => $batch->topology_image_path,
                    'url'        => route('admin.batches.diagram.image', $batch),
                    'label'      => $batch->name . ' — Topología global',
                    'batch_id'   => $batch->id,
                    'batch_name' => $batch->name,
                    'client_id'  => $project->client_id,
                    'categoria'  => 'topology',
                ];
            }

            // ── Cluster PNGs ──
            $clusters = is_array($batch->topology_clusters) ? $batch->topology_clusters : [];
            foreach ($clusters as $i => $clusterEntry) {
                $clusterPath  = is_array($clusterEntry)
                    ? ($clusterEntry['image_path'] ?? null)
                    : $clusterEntry;
                $clusterLabel = is_array($clusterEntry)
                    ? ($clusterEntry['label'] ?? "Cluster {$i}")
                    : "Cluster {$i}";

                if (!$clusterPath) continue;

                if (Storage::exists($clusterPath)) {
                    $filename = basename($clusterPath);
                    $images[] = [
                        'id'            => "img_batch_{$batch->id}_cluster_{$i}",
                        'src'           => $clusterPath,
                        'url'           => route('admin.batches.diagram.cluster.image', [$batch, $filename]),
                        'label'         => $batch->name . ' — ' . $clusterLabel,
                        'batch_id'      => $batch->id,
                        'batch_name'    => $batch->name,
                        'client_id'     => $project->client_id,
                        'categoria'     => 'cluster',
                        'cluster_index' => $i,
                    ];
                }
            }
        }

        return response()->json([
            'images'  => $images,
            'batches' => $batches->map(fn ($b) => [
                'id'   => $b->id,
                'name' => $b->name,
            ])->values(),
        ]);
    }

    // ── POST /admin/assembler/{project}/autolayout ────────────────────────────
    public function autolayout(DiagramProject $project)
    {
        $batches = UploadBatch::where('client_id', $project->client_id)
            ->get(['id', 'name', 'topology_image_path', 'topology_clusters']);

        if ($batches->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'No hay áreas para este cliente.'], 422);
        }

        $batchIds    = $batches->pluck('id')->toArray();
        $switchBatch = DB::table('switches')
            ->whereIn('upload_batch_id', $batchIds)
            ->pluck('upload_batch_id', 'id')
            ->toArray();

        $switchIds      = array_keys($switchBatch);
        $interAreaEdges = [];

        if (!empty($switchIds)) {
            $connections = SwitcheConnection::whereIn('src_switch_id', $switchIds)
                ->whereIn('dst_switch_id', $switchIds)
                ->get(['src_switch_id', 'dst_switch_id']);

            foreach ($connections as $conn) {
                $srcBatch = $switchBatch[$conn->src_switch_id] ?? null;
                $dstBatch = $switchBatch[$conn->dst_switch_id] ?? null;
                if ($srcBatch && $dstBatch && $srcBatch !== $dstBatch) {
                    $key = min($srcBatch, $dstBatch) . '_' . max($srcBatch, $dstBatch);
                    $interAreaEdges[$key] = ($interAreaEdges[$key] ?? 0) + 1;
                }
            }
        }

        $layout     = $this->computeForceLayout($batches->toArray(), $interAreaEdges);
        $objects    = [];
        $connectors = [];
        $placedIds  = [];

        foreach ($batches as $batch) {
            $pos      = $layout[$batch->id] ?? ['x' => 100, 'y' => 100];
            $hasTopo  = $batch->topology_image_path && Storage::exists($batch->topology_image_path);
            $clusters = is_array($batch->topology_clusters) ? $batch->topology_clusters : [];

            if ($hasTopo) {
                $src = $batch->topology_image_path;
                $url = route('admin.batches.diagram.image', $batch);
            } elseif (!empty($clusters)) {
                $first = $clusters[0];
                $src   = is_array($first) ? ($first['image_path'] ?? null) : $first;
                $url   = $src ? route('admin.batches.diagram.cluster.image', [$batch, basename($src)]) : null;
            } else {
                continue;
            }

            if (!$src || !$url) continue;

            $objId       = "img_batch_{$batch->id}_topology";
            $objects[]   = [
                'id' => $objId, 'type' => 'image', 'src' => $src, 'url' => $url,
                'x' => $pos['x'], 'y' => $pos['y'],
                'width' => 800, 'height' => 600, 'scaleX' => 1.0, 'scaleY' => 1.0, 'angle' => 0,
                'metadata' => [
                    'batch_id' => $batch->id, 'batch_name' => $batch->name,
                    'client_id' => $project->client_id, 'categoria' => 'topology', 'label' => $batch->name,
                ],
            ];
            $placedIds[$batch->id] = $objId;
        }

        $connIdx = 1;
        foreach ($interAreaEdges as $key => $count) {
            [$bA, $bB] = explode('_', $key);
            if (isset($placedIds[$bA]) && isset($placedIds[$bB])) {
                $connectors[] = [
                    'id' => "conn_{$connIdx}", 'from' => $placedIds[$bA], 'to' => $placedIds[$bB],
                    'type' => 'straight', 'label' => "{$count} enlace" . ($count > 1 ? 's' : ''),
                    'color' => '#F97316', 'strokeWidth' => 2,
                ];
                $connIdx++;
            }
        }

        return response()->json(['ok' => true, 'objects' => $objects, 'connectors' => $connectors]);
    }

    // ── GET /admin/assembler/{project}/graph ──────────────────────────────────
    // Returns the full switch graph for the project's client.
    // Used by the Vectorial editor to populate sidebar and draw connections.
    public function graph(DiagramProject $project)
    {
        $rolePatterns = [
            'core'         => '/\bCORE?\b|^CR[-_]|[-_]CR[-_]|^CORE/i',
            'backbone'     => '/\bBACKBONE\b|\bBACKB\b|^BB[-_]|[-_]BB[-_]/i',
            'distribution' => '/\bDIST\b|\bDS\b|\bDISTRIBUCION\b|\bDISTRIBUTION\b/i',
        ];
        $roleColors = [
            'core'         => '#1E3A5F',
            'backbone'     => '#1D4ED8',
            'distribution' => '#0891B2',
            'access'       => '#16A34A',
        ];
        $roleLevel = ['core' => 0, 'backbone' => 1, 'distribution' => 2, 'access' => 3];

        // All batches for this client
        $batches = UploadBatch::where('client_id', $project->client_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($batches->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'No hay áreas para este cliente.'], 422);
        }

        $batchIds = $batches->pluck('id')->toArray();

        // All switches for these batches
        $switches = Switche::whereIn('upload_batch_id', $batchIds)
            ->where('parse_status', 'ok')
            ->get(['id', 'upload_batch_id', 'sys_name', 'management_ip', 'system_type',
                   'system_mac', 'is_stacked', 'active_ports']);

        // Build node list
        $nodes      = [];
        $nodeIndex  = [];  // switch_id → node_id

        foreach ($switches as $sw) {
            $name   = $sw->sys_name ?? "Switch-{$sw->id}";
            $role   = 'access';
            foreach ($rolePatterns as $r => $pattern) {
                if (preg_match($pattern, $name)) { $role = $r; break; }
            }

            $nodeId          = "sw_{$sw->id}";
            $nodeIndex[$sw->id] = $nodeId;

            $nodes[] = [
                'id'           => $nodeId,
                'switch_id'    => $sw->id,
                'batch_id'     => $sw->upload_batch_id,
                'label'        => $name,
                'role'         => $role,
                'level'        => $roleLevel[$role] ?? 3,
                'color'        => $roleColors[$role] ?? '#16A34A',
                'ip'           => $sw->management_ip ?? '',
                'model'        => $sw->system_type ?? '',
                'mac'          => $sw->system_mac ?? '',
                'is_stacked'   => (bool) $sw->is_stacked,
                'port_count'   => count($sw->active_ports ?? []),
                'icon'         => $sw->is_stacked ? 'stack_switch' : match($role) {
                    'core'         => 'core_switch',
                    'backbone'     => 'backbone_switch',
                    'distribution' => 'dist_switch',
                    default        => 'access_switch',
                },
            ];
        }

        // Build edge list from SwitcheConnection
        $switchIds = array_keys($nodeIndex);
        $edges     = [];
        $edgeSeen  = [];

        if (!empty($switchIds)) {
            $connections = SwitcheConnection::whereIn('src_switch_id', $switchIds)
                ->whereIn('dst_switch_id', $switchIds)
                ->whereNotNull('dst_switch_id')
                ->get(['id', 'src_switch_id', 'dst_switch_id', 'src_port', 'dst_port', 'num_vlans']);

            foreach ($connections as $conn) {
                $fromNode = $nodeIndex[$conn->src_switch_id] ?? null;
                $toNode   = $nodeIndex[$conn->dst_switch_id] ?? null;
                if (!$fromNode || !$toNode || $fromNode === $toNode) continue;

                // Deduplicate bidirectional edges
                $dedupeKey = implode('|', [min($fromNode, $toNode), max($fromNode, $toNode)]);
                if (isset($edgeSeen[$dedupeKey])) continue;
                $edgeSeen[$dedupeKey] = true;

                $srcBatch = $switches->firstWhere('id', $conn->src_switch_id)?->upload_batch_id;
                $dstBatch = $switches->firstWhere('id', $conn->dst_switch_id)?->upload_batch_id;

                $edges[] = [
                    'id'         => "e_{$conn->id}",
                    'from'       => $fromNode,
                    'to'         => $toNode,
                    'src_port'   => $conn->src_port ?? '',
                    'dst_port'   => $conn->dst_port ?? '',
                    'num_vlans'  => $conn->num_vlans ?? 0,
                    'inter_area' => ($srcBatch !== $dstBatch),
                ];
            }
        }

        return response()->json([
            'ok'      => true,
            'batches' => $batches->map(fn ($b) => [
                'id'           => $b->id,
                'name'         => $b->name,
                'switch_count' => collect($nodes)->where('batch_id', $b->id)->count(),
            ])->values(),
            'nodes'   => $nodes,
            'edges'   => $edges,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function exportPng(DiagramProject $project)
    {
        if (!$project->canvas_json) {
            return response()->json(['ok' => false, 'error' => 'El canvas está vacío.'], 422);
        }

        $tmpJson = storage_path("app/tmp/diagram_project_{$project->id}.json");
        $outPath = storage_path("app/tmp/diagram_project_{$project->id}_export.png");

        if (!is_dir(dirname($tmpJson))) {
            mkdir(dirname($tmpJson), 0755, true);
        }

        // Enrich canvas JSON with absolute file paths so Python doesn't need
        // to guess the storage base. Adds 'abs_path' to every image object.
        $canvasData = $project->canvas_json;
        if (isset($canvasData['objects']) && is_array($canvasData['objects'])) {
            foreach ($canvasData['objects'] as &$obj) {
                if (($obj['type'] ?? '') === 'image' && !empty($obj['src'])) {
                    $absPath = Storage::path($obj['src']);
                    $obj['abs_path'] = str_replace('\\', '/', $absPath);
                }
            }
            unset($obj);
        }

        file_put_contents($tmpJson, json_encode($canvasData));

        $pythonBin   = env('PYTHON_BIN', 'python');
        $script      = base_path('scripts/diagram_composer.py');
        $storagePath = storage_path('app');

        $cmd = escapeshellcmd($pythonBin) . ' '
             . escapeshellarg($script)
             . ' --project-json ' . escapeshellarg($tmpJson)
             . ' --output '       . escapeshellarg($outPath)
             . ' --storage-path ' . escapeshellarg($storagePath)
             . ' --scale 2.0'
             . ' 2>&1';

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($outPath)) {
            $rawDetail  = implode("\n", $output);
            $safeDetail = mb_convert_encoding($rawDetail, 'UTF-8',
                mb_detect_encoding($rawDetail, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true) ?: 'Windows-1252'
            );

            return response()->json([
                'ok'     => false,
                'error'  => 'Error al generar PNG.',
                'detail' => $safeDetail,
            ], 500);
        }

        return response()->download($outPath, "{$project->name}.png", [
            'Content-Type' => 'image/png',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Simple force-directed layout: grid init + repulsion/attraction iterations.
     * Returns [batch_id => ['x' => int, 'y' => int]].
     */
    private function computeForceLayout(array $batches, array $edges): array
    {
        $n       = count($batches);
        $padding = 950;
        $canvasW = 5000;
        $canvasH = 4000;

        // Grid initialisation
        $cols = max(1, (int) ceil(sqrt($n)));
        $pos  = [];
        foreach ($batches as $i => $batch) {
            $col        = $i % $cols;
            $row        = (int) floor($i / $cols);
            $pos[$batch['id']] = [
                'x' => 100 + $col * $padding,
                'y' => 100 + $row * $padding,
            ];
        }

        // 60 iterations of force simulation
        for ($iter = 0; $iter < 60; $iter++) {
            $forces = [];
            foreach ($batches as $b) {
                $forces[$b['id']] = ['x' => 0, 'y' => 0];
            }

            // Repulsion between all pairs
            $batchList = array_column($batches, 'id');
            for ($i = 0; $i < count($batchList); $i++) {
                for ($j = $i + 1; $j < count($batchList); $j++) {
                    $a  = $batchList[$i];
                    $b  = $batchList[$j];
                    $dx = $pos[$a]['x'] - $pos[$b]['x'];
                    $dy = $pos[$a]['y'] - $pos[$b]['y'];
                    $d  = max(1, sqrt($dx * $dx + $dy * $dy));
                    $f  = ($padding * $padding) / $d;
                    $forces[$a]['x'] += $f * $dx / $d;
                    $forces[$a]['y'] += $f * $dy / $d;
                    $forces[$b]['x'] -= $f * $dx / $d;
                    $forces[$b]['y'] -= $f * $dy / $d;
                }
            }

            // Attraction for connected batches
            foreach ($edges as $key => $_) {
                [$a, $b] = explode('_', $key);
                if (!isset($pos[$a]) || !isset($pos[$b])) continue;
                $dx = $pos[$b]['x'] - $pos[$a]['x'];
                $dy = $pos[$b]['y'] - $pos[$a]['y'];
                $d  = max(1, sqrt($dx * $dx + $dy * $dy));
                $f  = $d * $d / ($padding * 1.5);
                $forces[$a]['x'] += $f * $dx / $d;
                $forces[$a]['y'] += $f * $dy / $d;
                $forces[$b]['x'] -= $f * $dx / $d;
                $forces[$b]['y'] -= $f * $dy / $d;
            }

            // Apply forces with damping
            $damp = max(0.1, 1 - $iter / 60);
            foreach ($batches as $b) {
                $id        = $b['id'];
                $pos[$id]['x'] = (int) max(50, min($canvasW - 900, $pos[$id]['x'] + $forces[$id]['x'] * $damp));
                $pos[$id]['y'] = (int) max(50, min($canvasH - 700, $pos[$id]['y'] + $forces[$id]['y'] * $damp));
            }
        }

        return $pos;
    }
}
