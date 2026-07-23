<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Switche;
use App\Services\TopologyBuilderService;

class InventarioExportController extends Controller
{
    /**
     * Descarga el inventario de un cliente como CSV compatible con Excel.
     */
    public function export(Client $client): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $switches = Switche::with(['batch'])
            ->whereHas('batch', fn ($q) => $q->where('client_id', $client->id))
            ->where('parse_status', 'ok')
            ->orderBy('sys_name')
            ->get([
                'id', 'sys_name', 'system_type', 'serial_number', 'system_mac',
                'management_ip', 'firmware_version', 'is_stacked', 'ip_routes',
                'stack_members', 'upload_batch_id',
            ]);

        $filename = 'inventario-' . str($client->name)->slug() . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($switches, $client) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8: necesario para que Excel abra correctamente con acentos
            fwrite($handle, "\xEF\xBB\xBF");

            // Encabezado
            fputcsv($handle, [
                'Cliente',
                'Área',
                'Hostname',
                'IP Gestión',
                'Modelo',
                'Serie',
                'MAC',
                'Firmware',
                'Default Route',
                'Arreglo',
            ]);

            foreach ($switches as $s) {
                $defaultRoute = collect($s->ip_routes ?? [])
                    ->first(fn ($r) => ($r['destination'] ?? '') === 'Default Route');

                $role = $s->is_stacked
                    ? 'Stack'
                    : ucfirst(TopologyBuilderService::detectRoleStatic($s->sys_name ?? ''));

                $serial = ($s->is_stacked && !empty($s->stack_members))
                    ? collect($s->stack_members)
                        ->map(fn ($m) => 'S'.($m['slot'] ?? '?').': '.($m['serial_number'] ?? '—'))
                        ->join(' | ')
                    : ($s->serial_number ?? '—');

                $mac = ($s->is_stacked && !empty($s->stack_members))
                    ? collect($s->stack_members)
                        ->map(fn ($m) => 'S'.($m['slot'] ?? '?').': '.($m['mac'] ?? '—'))
                        ->join(' | ')
                    : ($s->system_mac ?? '—');

                fputcsv($handle, [
                    $client->name,
                    $s->batch?->name         ?? '—',
                    $s->sys_name             ?? '—',
                    $s->management_ip        ?? '—',
                    $s->system_type          ?? '—',
                    $serial,
                    $mac,
                    $s->firmware_version     ?? '—',
                    $defaultRoute['gateway'] ?? '—',
                    $role,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
