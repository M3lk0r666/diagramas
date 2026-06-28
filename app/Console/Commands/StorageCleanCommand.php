<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class StorageCleanCommand extends Command
{
    protected $signature   = 'app:storage-clean
                                {--force : Omitir confirmación interactiva}';
    protected $description = 'Elimina todos los archivos generados en storage (configs, uploads, topologías). Úsalo junto con migrate:fresh.';

    /** Directorios relativos al disco "local" (storage/app/) que se limpiarán */
    private array $localDirs = [
        'configs'          => 'Configs de switches procesados (storage/app/configs/)',
        'topology/custom'  => 'Topologías personalizadas PNG+JSON (storage/app/topology/custom/)',
        'topology/gojs'    => 'Topologías GoJS exportadas PNG (storage/app/topology/gojs/)',
    ];

    /** Directorios relativos al disco "public" (storage/app/public/) */
    private array $publicDirs = [
        'batches' => 'Archivos subidos originales (storage/app/public/batches/)',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=yellow;options=bold>app:storage-clean</>  —  Limpieza de archivos generados');
        $this->newLine();

        // ── Calcular tamaños y contar archivos ────────────────────────────────────
        $summary = [];

        foreach ($this->localDirs as $dir => $desc) {
            [$count, $size] = $this->dirStats('local', $dir);
            $summary[] = ['disk' => 'local', 'dir' => $dir, 'desc' => $desc, 'count' => $count, 'size' => $size];
        }

        // Carpetas numeradas por batch_id dentro de topology/ (diagramas por switch)
        $batchDirs = collect(Storage::disk('local')->directories('topology'))
            ->filter(fn ($d) => preg_match('#topology/\d+$#', $d));

        foreach ($batchDirs as $dir) {
            [$count, $size] = $this->dirStats('local', $dir);
            $summary[] = [
                'disk'  => 'local',
                'dir'   => $dir,
                'desc'  => "Diagramas por switch del batch (storage/app/{$dir}/)",
                'count' => $count,
                'size'  => $size,
            ];
        }

        foreach ($this->publicDirs as $dir => $desc) {
            [$count, $size] = $this->dirStats('public', $dir);
            $summary[] = ['disk' => 'public', 'dir' => $dir, 'desc' => $desc, 'count' => $count, 'size' => $size];
        }

        // ── Mostrar tabla resumen ─────────────────────────────────────────────────
        $totalFiles = array_sum(array_column($summary, 'count'));
        $totalBytes = array_sum(array_column($summary, 'size'));

        if ($totalFiles === 0) {
            $this->info('  ✓ El storage ya está limpio. No hay nada que eliminar.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->table(
            ['Descripción', 'Archivos', 'Tamaño'],
            array_map(fn ($r) => [
                $r['desc'],
                number_format($r['count']),
                $this->humanSize($r['size']),
            ], $summary)
        );

        $this->newLine();
        $this->line(sprintf(
            '  Total: <fg=yellow>%s archivos</> · <fg=yellow>%s</>',
            number_format($totalFiles),
            $this->humanSize($totalBytes)
        ));
        $this->newLine();

        // ── Confirmación ──────────────────────────────────────────────────────────
        if (!$this->option('force')) {
            $confirmed = $this->confirm(
                '  ¿Confirmas la eliminación permanente de estos archivos?',
                false
            );

            if (!$confirmed) {
                $this->warn('  Operación cancelada.');
                $this->newLine();
                return self::FAILURE;
            }
        }

        $this->newLine();

        // ── Eliminar ──────────────────────────────────────────────────────────────
        $deleted = 0;
        $errors  = 0;

        foreach ($summary as $item) {
            if ($item['count'] === 0) continue;

            $this->output->write("  Eliminando {$item['dir']}… ");

            try {
                Storage::disk($item['disk'])->deleteDirectory($item['dir']);
                $deleted += $item['count'];
                $this->line('<fg=green>✓</>');
            } catch (\Throwable $e) {
                $errors++;
                $this->line('<fg=red>✗ ' . $e->getMessage() . '</>');
            }
        }

        $this->newLine();

        if ($errors === 0) {
            $this->info("  ✓ Limpieza completada — {$deleted} archivos eliminados.");
        } else {
            $this->warn("  Completado con {$errors} error(es). Revisa los mensajes anteriores.");
        }

        $this->newLine();
        $this->line('  <fg=gray>Próximo paso sugerido:</> <fg=cyan>php artisan migrate:fresh --seed</>');
        $this->newLine();

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function dirStats(string $disk, string $dir): array
    {
        $files = Storage::disk($disk)->allFiles($dir);
        $count = count($files);
        $size  = 0;

        foreach ($files as $file) {
            try {
                $size += Storage::disk($disk)->size($file);
            } catch (\Throwable) {
                // archivo no accesible, ignorar
            }
        }

        return [$count, $size];
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) return round($bytes / 1_073_741_824, 2) . ' GB';
        if ($bytes >= 1_048_576)     return round($bytes / 1_048_576, 2) . ' MB';
        if ($bytes >= 1_024)         return round($bytes / 1_024, 2) . ' KB';
        return $bytes . ' B';
    }
}
