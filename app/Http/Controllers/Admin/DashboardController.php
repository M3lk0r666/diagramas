<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DiagramProject;
use App\Models\Switche;
use App\Models\SwitcheConnection;
use App\Models\UploadBatch;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ── Totales globales ──────────────────────────────────────────────
        $totalClients     = Client::count();
        $totalBatches     = UploadBatch::count();
        $totalSwitches    = Switche::count();
        $totalConnections = SwitcheConnection::count();
        $totalProjects    = DiagramProject::count();

        // ── Batches por estado ────────────────────────────────────────────
        $batchesByStatus = UploadBatch::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $batchCompleted  = $batchesByStatus['completed']  ?? 0;
        $batchFailed     = $batchesByStatus['failed']     ?? 0;
        $batchPending    = $batchesByStatus['pending']    ?? 0;
        $batchProcessing = $batchesByStatus['processing'] ?? 0;

        // ── Diagramas por tipo ────────────────────────────────────────────
        $projectsByType = DiagramProject::select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        $projectsPng      = $projectsByType['png']      ?? 0;
        $projectsVect     = $projectsByType['vectorial'] ?? 0;

        // ── Switches: stacked vs standalone ──────────────────────────────
        $stackedSwitches    = Switche::where('is_stacked', true)->count();
        $standaloneSwitches = $totalSwitches - $stackedSwitches;

        // ── Clientes con más actividad ────────────────────────────────────
        $topClients = Client::withCount(['batches'])
            ->with(['batches' => function ($q) {
                $q->withCount('switches');
            }])
            ->orderByDesc('batches_count')
            ->limit(6)
            ->get()
            ->map(function ($c) {
                $c->switch_total = $c->batches->sum('switches_count');
                return $c;
            });

        // ── Últimas áreas procesadas ──────────────────────────────────────
        $recentBatches = UploadBatch::with('client')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        // ── Últimos diagramas ─────────────────────────────────────────────
        $recentProjects = DiagramProject::with('client')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        // ── Totales por cliente (para tabla resumen) ──────────────────────
        $clientSummary = Client::withCount('batches')
            ->with(['batches' => function ($q) {
                $q->select('client_id', 'id', 'status')->withCount('switches');
            }])
            ->having('batches_count', '>', 0)
            ->orderByDesc('batches_count')
            ->limit(8)
            ->get()
            ->map(function ($c) {
                $c->completed_batches = $c->batches->where('status', 'completed')->count();
                $c->failed_batches    = $c->batches->where('status', 'failed')->count();
                $c->switch_total      = $c->batches->sum('switches_count');
                return $c;
            });

        // ── Archivos de configuración en storage ──────────────────────────
        $storageBase  = storage_path('app/public/switch-files');
        $totalFiles   = 0;
        if (is_dir($storageBase)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storageBase));
            foreach ($it as $f) { if ($f->isFile()) $totalFiles++; }
        }

        return view('admin.dashboard', compact(
            'totalClients', 'totalBatches', 'totalSwitches',
            'totalConnections', 'totalProjects', 'totalFiles',
            'batchCompleted', 'batchFailed', 'batchPending', 'batchProcessing',
            'projectsPng', 'projectsVect',
            'stackedSwitches', 'standaloneSwitches',
            'topClients', 'recentBatches', 'recentProjects', 'clientSummary'
        ));
    }
}
