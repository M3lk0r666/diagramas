<?php

namespace App\Console\Commands;

use App\Models\UploadBatch;
use App\Services\TopologyBuilderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateTopologyCommand extends Command
{
    protected $signature   = 'topology:generate {batch : ID del UploadBatch}
                                                {--dpi=150 : Resolución del PNG}
                                                {--json-only : Solo genera el JSON, sin PNG}
                                                {--no-clusters : No generar imágenes de clúster}';
    protected $description = 'Genera el JSON de topología y el PNG para un batch dado';

    public function handle(TopologyBuilderService $builder): int
    {
        $batchId = (int) $this->argument('batch');
        $batch   = UploadBatch::find($batchId);

        if (!$batch) {
            $this->error("Batch #{$batchId} no encontrado.");
            return self::FAILURE;
        }

        $this->info("── Generando topología para: {$batch->name} (#{$batch->id})");

        // ── 1. Construir JSON ────────────────────────────────────────
        $this->line('  → Construyendo JSON de topología…');
        $topology = $builder->buildForBatch($batch);

        $nodeCount = count($topology['nodes']);
        $edgeCount = count($topology['edges']);
        $this->line("     {$nodeCount} equipos  ·  {$edgeCount} enlaces");

        // Guardar JSON en storage/app/topology/{id}/topology.json
        $jsonPath = "topology/{$batch->id}/topology.json";
        Storage::put($jsonPath, json_encode($topology, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $batch->update(['topology_json' => $topology]);
        $this->line("  ✓ JSON guardado en storage/app/{$jsonPath}");

        if ($this->option('json-only')) {
            $this->info('Hecho (solo JSON).');
            return self::SUCCESS;
        }

        // ── 2. Generar PNG con Python ────────────────────────────────
        $this->line('  → Ejecutando script Python…');

        $jsonAbsPath = Storage::path($jsonPath);
        $pngPath     = "topology/{$batch->id}/topology.png";
        $pngAbsPath  = Storage::path($pngPath);
        $scriptPath  = base_path('scripts/topology_generator.py');
        $dpi         = (int) $this->option('dpi');

        if (!file_exists($scriptPath)) {
            $this->error("Script no encontrado: {$scriptPath}");
            return self::FAILURE;
        }

        // Detectar el ejecutable de Python disponible
        $python = $this->detectPython();
        if (!$python) {
            $this->error('No se encontró Python 3 en el sistema. Instálalo o ajusta $PATH.');
            return self::FAILURE;
        }

        $withClusters = !$this->option('no-clusters');
        $clustersRelDir = "topology/{$batch->id}/clusters";
        $clustersAbsDir = Storage::path($clustersRelDir);

        $cmd    = escapeshellcmd("{$python} {$scriptPath}") .
                  ' ' . escapeshellarg($jsonAbsPath) .
                  ' ' . escapeshellarg($pngAbsPath) .
                  " --dpi {$dpi}";

        if ($withClusters) {
            $cmd .= ' --clusters ' . escapeshellarg($clustersAbsDir);
        }

        $output = [];
        $code   = 0;
        exec($cmd . ' 2>&1', $output, $code);

        foreach ($output as $line) {
            $this->line("     {$line}");
        }

        if ($code !== 0) {
            $this->error("El script Python terminó con código {$code}.");
            return self::FAILURE;
        }

        $batch->update(['topology_image_path' => $pngPath]);
        $this->info("  ✓ PNG generado en storage/app/{$pngPath}");

        if ($withClusters) {
            // Parse cluster JSON from last line starting with '['
            $jsonLine = collect($output)->last(fn($l) => str_starts_with(trim($l), '['));
            if ($jsonLine) {
                $clusters = json_decode($jsonLine, true);
                if (is_array($clusters)) {
                    $storageBase = Storage::path('');
                    $clusters = array_map(function ($c) use ($storageBase, $clustersRelDir) {
                        $safe = str_replace('\\', '/', $c['image_path'] ?? '');
                        $base = str_replace('\\', '/', $storageBase);
                        $rel  = ltrim(str_replace($base, '', $safe), '/\\');
                        $c['image_path'] = $rel ?: ($clustersRelDir . '/' . basename($safe));
                        return $c;
                    }, $clusters);
                    $batch->update(['topology_clusters' => $clusters]);
                    $this->info("  ✓ " . count($clusters) . " clústeres generados en storage/app/{$clustersRelDir}");
                }
            } else {
                $this->warn('  ⚠ No se encontró JSON de clústeres en la salida del script.');
            }
        }

        $this->info('Topología generada correctamente.');

        return self::SUCCESS;
    }

    private function detectPython(): ?string
    {
        foreach (['python', 'python3', 'py'] as $candidate) {
            $out  = [];
            $code = 0;
            // Simplemente intentar ejecutarlo: funciona igual en Windows y Linux
            exec("{$candidate} --version 2>&1", $out, $code);
            if ($code === 0) {
                return $candidate;
            }
        }
        return null;
    }
}
