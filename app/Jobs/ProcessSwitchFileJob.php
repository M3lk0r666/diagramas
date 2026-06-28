<?php
namespace App\Jobs;

use App\Jobs\GenerateTopologyJob;
use App\Models\Switche;
use App\Models\UploadBatch;
use App\Services\SwitchParserService;
use App\Services\ConnectionResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessSwitchFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 3;   // reintentos si el job falla inesperadamente
    public int $timeout = 90;  // segundos máximos por archivo

    public function __construct(
        public readonly int    $batchId,
        public readonly string $storagePath,
        public readonly string $originalName,
    ) {}

    public function handle(SwitchParserService $parser, ConnectionResolverService $resolver): void
    {
        $content = Storage::get($this->storagePath);
        $batch   = UploadBatch::findOrFail($this->batchId);

        try {
            $data = $parser->parse($content);

            $managementIp = $data['header']['host_ip'] ?? null;
            $stacking     = $data['stacking'] ?? [];

            // Guardar archivo de configuración (show configuration)
            $configContent = $data['raw_sections']['show configuration'] ?? null;
            $configPath    = null;
            if ($configContent && trim($configContent) !== '') {
                $switchName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $data['switch_info']['sys_name'] ?? 'switch');
                $configPath = "configs/{$this->batchId}/{$switchName}.txt";
                Storage::put($configPath, trim($configContent));
            }

            Switche::create([
                'upload_batch_id'  => $this->batchId,
                'original_filename'=> $this->originalName,
                'sys_name'         => $data['switch_info']['sys_name']        ?? null,
                'sys_location'     => $data['switch_info']['sys_location']    ?? null,
                'sys_contact'      => $data['switch_info']['sys_contact']     ?? null,
                'system_mac'       => $data['switch_info']['system_mac']      ?? null,
                'system_type'      => $data['switch_info']['system_type']     ?? null,
                'serial_number'    => $data['version']['serial_number']       ?? null,
                'management_ip'    => $managementIp,
                'config_path'      => $configPath,
                'firmware_version' => $data['version']['firmware_version']    ?? null,
                'vlans'            => $data['vlans'],
                'ip_routes'        => $data['ip_routes'],
                'edp_ports'        => $data['edp_ports'],
                'active_ports'     => $data['active_ports'],
                'is_stacked'       => $stacking['is_stacked'] ?? false,
                'stack_topology'   => $stacking['topology']   ?? null,
                'stack_members'    => $stacking['members']    ?? [],
                'raw_sections'     => $data['raw_sections'],
                'parse_status'     => 'ok',
                'parsed_at'        => now(),
            ]);

            $batch->increment('processed');

        } catch (Throwable $e) {
            $errors   = $batch->error_log ?? [];
            $errors[] = ['file' => $this->originalName, 'message' => $e->getMessage()];
            $batch->update(['error_log' => $errors]);
            $batch->increment('processed');
            $batch->increment('failed');

            Switche::create([
                'upload_batch_id'   => $this->batchId,
                'original_filename' => $this->originalName,
                'parse_status'      => 'error',
                'parse_error'       => $e->getMessage(),
            ]);
        }

        // ── Finalización atómica del lote ────────────────────────────────
        // Usamos UPDATE con WHERE para garantizar que solo un worker finalice
        // el lote, aunque varios jobs terminen casi al mismo tiempo.
        $batch->refresh();
        if ($batch->processed >= $batch->total_files) {
            $finalStatus = $batch->failed > 0 ? 'failed' : 'completed';

            $affected = DB::table('upload_batches')
                ->where('id', $this->batchId)
                ->where('status', 'processing')   // solo si todavía no fue cerrado
                ->update(['status' => $finalStatus, 'updated_at' => now()]);

            if ($affected > 0) {
                // Solo el worker que ganó la carrera ejecuta estas acciones
                $resolver->resolveForBatch($this->batchId);
                GenerateTopologyJob::dispatch($this->batchId);
            }
        }
    }
}
