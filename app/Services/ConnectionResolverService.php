<?php
namespace App\Services;

use App\Models\Client;
use App\Models\Switche;
use App\Models\SwitcheConnection;

class ConnectionResolverService
{
    /**
     * Barrido global de un cliente: rellena dst_switch_id NULL
     * cruzando MACs entre todos los batches del cliente.
     * Retorna estadísticas: [total_pending, newly_resolved].
     */
    public function resolveForClient(int $clientId): array
    {
        // Todos los switches OK del cliente, indexados por MAC
        $allSwitches = Switche::whereHas('batch', fn ($q) => $q->where('client_id', $clientId))
            ->where('parse_status', 'ok')
            ->get(['id', 'system_mac', 'upload_batch_id'])
            ->keyBy('system_mac');

        $allIds = $allSwitches->pluck('id')->toArray();

        // Conexiones con dst_switch_id NULL cuyo origen pertenece al cliente
        $pending = SwitcheConnection::whereIn('src_switch_id', $allIds)
            ->whereNull('dst_switch_id')
            ->get();

        $totalPending  = $pending->count();
        $newlyResolved = 0;

        foreach ($pending as $conn) {
            $dstSwitch = $allSwitches->get($conn->dst_mac);
            if ($dstSwitch) {
                $conn->update(['dst_switch_id' => $dstSwitch->id]);
                $newlyResolved++;
            }
        }

        return [
            'total_pending'   => $totalPending,
            'newly_resolved'  => $newlyResolved,
            'still_unresolved'=> $totalPending - $newlyResolved,
        ];
    }

    /**
     * Después de parsear todos los switches del batch,
     * construye la tabla switch_connections cruzando MACs.
     */
    public function resolveForBatch(int $batchId): void
    {
        $switches = Switche::where('upload_batch_id', $batchId)
                          ->where('parse_status', 'ok')
                          ->get()
                          ->keyBy('system_mac');

        // Primero elimina conexiones previas del batch para re-calcular
        SwitcheConnection::whereIn('src_switch_id', $switches->pluck('id'))->delete();

        foreach ($switches as $srcSwitch) {
            $edpPorts = $srcSwitch->edp_ports ?? [];

            foreach ($edpPorts as $edp) {
                // Convierte neighbor_id de 8 bytes a 6 bytes MAC estándar
                $dstMacRaw = $edp['neighbor_id']; // 00:00:dc:e6:50:b1:8c:ce
                $dstMac    = $this->normalizeMac($dstMacRaw);

                // Busca si el switch destino está en nuestra BD
                $dstSwitch = $switches->get($dstMac);

                SwitcheConnection::create([
                    'src_switch_id' => $srcSwitch->id,
                    'src_mac'       => $srcSwitch->system_mac,
                    'src_port'      => $edp['port'],
                    'dst_switch_id' => $dstSwitch?->id,
                    'dst_mac'       => $dstMac,
                    'dst_port'      => $edp['remote_port'],
                    'neighbor_name' => $edp['neighbor'],
                    'age'           => $edp['age'] ?? null,
                    'num_vlans'     => $edp['num_vlans'] ?? null,
                ]);
            }
        }
    }

    /**
     * Convierte 00:00:dc:e6:50:b1:8c:ce → DC:E6:50:B1:8C:CE (últimos 6 bytes)
     */
    private function normalizeMac(string $mac): string
    {
        $parts = explode(':', $mac);
        // Si tiene 8 bytes (EDP format), toma los últimos 6
        if (count($parts) === 8) {
            $parts = array_slice($parts, 2);
        }
        return strtoupper(implode(':', $parts));
    }
}