<?php

namespace App\Jobs;

use App\Models\UploadBatch;
use App\Services\TopologyBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateTopologyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public readonly int $batchId) {}

    public function handle(TopologyBuilderService $builder): void
    {
        $batch = UploadBatch::find($this->batchId);
        if (!$batch) return;

        try {
            // 1. Construir y persitir JSON
            $topology = $builder->buildForBatch($batch);
            $jsonPath  = "topology/{$batch->id}/topology.json";
            Storage::put($jsonPath, json_encode($topology, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $batch->update(['topology_json' => $topology]);

            // 2. Llamar script Python si existe
            $scriptPath = base_path('scripts/topology_generator.py');
            if (!file_exists($scriptPath)) return;

            $python = $this->detectPython();
            if (!$python) {
                Log::warning("GenerateTopologyJob: Python no encontrado para batch #{$batch->id}");
                return;
            }

            $pngPath    = "topology/{$batch->id}/topology.png";
            $pngAbsPath = Storage::path($pngPath);
            $jsonAbsPath = Storage::path($jsonPath);

            // Directorio para imágenes de clúster
            $clustersRelDir = "topology/{$batch->id}/clusters";
            $clustersAbsDir = Storage::path($clustersRelDir);

            $cmd    = escapeshellcmd("{$python} {$scriptPath}") .
                      ' ' . escapeshellarg($jsonAbsPath) .
                      ' ' . escapeshellarg($pngAbsPath) .
                      ' --clusters ' . escapeshellarg($clustersAbsDir);
            $output = [];
            $code   = 0;
            exec($cmd . ' 2>&1', $output, $code);

            if ($code === 0) {
                $batch->update(['topology_image_path' => $pngPath]);
                Log::info("GenerateTopologyJob: PNG generado para batch #{$batch->id}");

                // Parsear JSON de clústeres emitido por el script (última línea JSON)
                $jsonLine = collect($output)->last(fn($l) => str_starts_with(trim($l), '['));
                if ($jsonLine) {
                    $clusters = json_decode($jsonLine, true);
                    if (is_array($clusters)) {
                        // Convertir rutas absolutas → relativas a storage/app/
                        $storageBase = Storage::path('');
                        $clusters = array_map(function ($c) use ($storageBase, $clustersRelDir) {
                            $safe = str_replace('\\', '/', $c['image_path'] ?? '');
                            $base = str_replace('\\', '/', $storageBase);
                            $rel  = ltrim(str_replace($base, '', $safe), '/\\');
                            $c['image_path'] = $rel ?: ($clustersRelDir . '/' . basename($safe));
                            return $c;
                        }, $clusters);
                        $batch->update(['topology_clusters' => $clusters]);
                        Log::info("GenerateTopologyJob: " . count($clusters) . " clústeres generados para batch #{$batch->id}");
                    }
                }
            } else {
                Log::error("GenerateTopologyJob: Error Python batch #{$batch->id}", ['output' => $output]);
            }

        } catch (\Throwable $e) {
            Log::error("GenerateTopologyJob: " . $e->getMessage());
        }
    }

    private function detectPython(): ?string
    {
        foreach (['python', 'python3', 'py'] as $candidate) {
            $out  = [];
            $code = 0;
            exec("{$candidate} --version 2>&1", $out, $code);
            if ($code === 0) return $candidate;
        }
        return null;
    }
}
