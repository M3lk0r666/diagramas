<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Switche;
use App\Models\UploadBatch;
use App\Models\SwitcheConnection;
use App\Services\TopologyBuilderService;

class ClientHubController extends Controller
{
    /**
     * Hub: cards de todos los clientes con stats y accesos rápidos.
     */
    public function index()
    {
        $clients = Client::with([
            'batches' => fn ($q) => $q->withCount('switches')->select('id', 'client_id', 'name', 'status'),
        ])
        ->orderBy('name')
        ->get();

        // Pre-calcular totales por cliente para las cards
        $clients = $clients->map(function ($client) {
            $client->total_switches = $client->batches->sum('switches_count');
            $client->total_areas    = $client->batches->count();
            $client->ok_areas       = $client->batches->where('status', 'completed')->count();
            return $client;
        });

        return view('admin.client-hub.index', compact('clients'));
    }

    /**
     * Inventario filtrado por cliente.
     */
    public function inventario(Client $client)
    {
        $switches = Switche::with(['batch'])
            ->whereHas('batch', fn ($q) => $q->where('client_id', $client->id))
            ->where('parse_status', 'ok')
            ->orderBy('sys_name')
            ->get([
                'id','sys_name','system_type','serial_number','system_mac',
                'management_ip','ip_routes','is_stacked','stack_members',
                'firmware_version','upload_batch_id','parse_status',
            ]);

        $batches = UploadBatch::where('client_id', $client->id)
            ->orderBy('name')
            ->get(['id', 'name', 'client_id', 'status']);

        $totalSwitches   = $switches->count();
        $totalAreas      = $batches->count();
        $totalConnections = SwitcheConnection::whereHas(
            'srcSwitch.batch', fn ($q) => $q->where('client_id', $client->id)
        )->whereNotNull('dst_switch_id')->count();

        $rows = $switches->map(function ($s) use ($client) {
            $defaultRoute = collect($s->ip_routes ?? [])
                ->first(fn ($r) => ($r['destination'] ?? '') === 'Default Route');

            return [
                'id'            => $s->id,
                'sys_name'      => $s->sys_name      ?? '—',
                'system_type'   => $s->system_type   ?? '—',
                'serial_number' => $s->serial_number ?? '—',
                'system_mac'    => $s->system_mac    ?? '—',
                'management_ip' => $s->management_ip ?? '—',
                'firmware'      => $s->firmware_version ?? '—',
                'default_route' => $defaultRoute['gateway'] ?? '—',
                'is_stacked'    => (bool) $s->is_stacked,
                'role'          => TopologyBuilderService::detectRoleStatic($s->sys_name ?? ''),
                'batch_id'      => $s->upload_batch_id,
                'batch_name'    => $s->batch?->name ?? '—',
                'client_id'     => $client->id,
                'client_name'   => $client->name,
                'show_url'      => route('admin.switches.show', $s->id) . '?from_client=' . $client->id,
            ];
        })->values();

        $grouped = $rows->groupBy('batch_id');

        return view('admin.client-hub.inventario', compact(
            'client', 'rows', 'grouped', 'batches',
            'totalSwitches', 'totalAreas', 'totalConnections'
        ));
    }
}
