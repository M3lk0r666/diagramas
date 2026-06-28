<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTopologyJob;
use App\Jobs\ProcessSwitchFileJob;
use App\Models\Client;
use App\Models\UploadBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    // GET / → pantalla de carga
    public function index()
    {
        $recentBatches = UploadBatch::with('client')->latest()->take(5)->get();
        $clients       = Client::orderBy('name')->get(['id', 'name']);
        $allBatches    = UploadBatch::with('client')->orderBy('name')->get(['id', 'name', 'client_id']);
        return view('admin.upload.index', compact('recentBatches', 'clients', 'allBatches'));
    }

    // POST /upload → sube archivos y despacha jobs
    public function store(Request $request)
    {
        $request->validate([
            'mode'            => 'required|in:new,existing',
            'existing_batch'  => 'required_if:mode,existing|nullable|exists:upload_batches,id',
            'client_id'       => 'nullable|exists:clients,id',
            'name'            => 'nullable|string|max:120',
            'files'           => 'required|array|min:1',
            'files.*'         => 'required|file|mimes:txt|max:5120',
        ]);

        $files = $request->file('files');

        if ($request->input('mode') === 'existing') {
            // Agregar archivos a un lote existente
            $batch = UploadBatch::findOrFail($request->input('existing_batch'));
            $batch->increment('total_files', count($files));
            // Si estaba completado, volver a processing
            if (in_array($batch->status, ['completed', 'failed'])) {
                $batch->update(['status' => 'processing']);
            }
        } else {
            // Crear un nuevo lote
            $batchName = filled($request->input('name'))
                ? trim($request->input('name'))
                : 'Diagrama ' . now()->format('Y-m-d H:i');

            $batch = UploadBatch::create([
                'client_id'   => $request->input('client_id') ?: null,
                'name'        => $batchName,
                'status'      => 'processing',
                'total_files' => count($files),
            ]);
        }

        foreach ($files as $file) {
            $path = $file->store("batches/{$batch->id}");
            ProcessSwitchFileJob::dispatch($batch->id, $path, $file->getClientOriginalName());
        }

        return redirect()->route('admin.batches.show', $batch)
                         ->with('success', 'Procesando ' . count($files) . ' archivo(s)...');
    }

    // GET /batches/{batch} → progreso en tiempo real
    public function show(UploadBatch $batch, Request $request)
    {
        $switches = $batch->switches()
            ->orderBy('sys_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.upload.show', compact('batch', 'switches'));
    }

    // GET /batches/{batch}/status → JSON para polling/SSE
    public function status(UploadBatch $batch)
    {
        return response()->json([
            'status'        => $batch->status,
            'progress'      => $batch->progress_percent,
            'processed'     => $batch->processed,
            'total'         => $batch->total_files,
            'failed'        => $batch->failed,
            'errors'        => $batch->error_log,
            'has_diagram'   => !empty($batch->topology_image_path) &&
                               Storage::exists($batch->topology_image_path),
        ]);
    }

    // GET /batches/{batch}/diagram → vista del diagrama PNG
    public function diagram(UploadBatch $batch)
    {
        $batch->load('switches');
        $hasImage = !empty($batch->topology_image_path) &&
                    Storage::exists($batch->topology_image_path);

        return view('admin.upload.diagram', compact('batch', 'hasImage'));
    }

    // GET /batches/{batch}/diagram/image → sirve el PNG
    public function diagramImage(UploadBatch $batch)
    {
        abort_if(
            empty($batch->topology_image_path) || !Storage::exists($batch->topology_image_path),
            404,
            'Diagrama no generado todavía.'
        );

        return response()->file(
            Storage::path($batch->topology_image_path),
            ['Content-Type' => 'image/png', 'Cache-Control' => 'no-cache']
        );
    }

    // POST /batches/{batch}/diagram/regenerate → regenera el diagrama
    public function regenerateDiagram(UploadBatch $batch)
    {
        GenerateTopologyJob::dispatch($batch->id);

        return back()->with('success', 'Regenerando diagrama… actualiza la página en unos segundos.');
    }

    // GET /batches/{batch}/diagram/clusters/{filename} → sirve imagen de clúster
    public function clusterImage(UploadBatch $batch, string $filename)
    {
        // Sanitize to prevent path traversal
        $filename = basename($filename);
        $path = "topology/{$batch->id}/clusters/{$filename}";

        abort_if(!Storage::exists($path), 404, 'Imagen de clúster no encontrada.');

        return response()->file(
            Storage::path($path),
            ['Content-Type' => 'image/png', 'Cache-Control' => 'no-cache']
        );
    }
}