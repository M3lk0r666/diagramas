<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Switche;
use App\Models\SwitcheConnection;
use App\Models\UploadBatch;
use App\Services\TopologyBuilderService;

class InventarioController extends Controller
{
    public function index()
    {
        // ── Stats globales ────────────────────────────────────────────────────
        $totalSwitches   = Switche::where('parse_status', 'ok')->count();
        $totalClients    = Client::count();
        $totalAreas      = UploadBatch::where('status', 'completed')->count();
        $totalConnections= SwitcheConnection::whereNotNull('dst_switch_id')->count();
        $unresolvedConns = SwitcheConnection::whereNull('dst_switch_id')->count();

        // ── Switches con batch y cliente ──────────────────────────────────────
        $switches = Switche::with(['batch.client'])
            ->where('parse_status', 'ok')
            ->orderBy('sys_name')
            ->get([
                'id','sys_name','system_type','serial_number','system_mac',
                'management_ip','ip_routes','is_stacked','stack_members',
                'firmware_version','upload_batch_id','parse_status',
            ]);

        // ── Clientes y batches para filtros ──────────────────────────────────
        $clients = Client::orderBy('name')->get(['id', 'name']);
        $batches = UploadBatch::orderBy('name')
            ->with('client:id,name')
            ->get(['id', 'name', 'client_id']);

        // ── Transformar para la vista ─────────────────────────────────────────
        $rows = $switches->map(function ($s) {
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
                'client_id'     => $s->batch?->client?->id,
                'client_name'   => $s->batch?->client?->name ?? '—',
                'show_url'      => route('admin.switches.show', $s->id),
            ];
        })->values();

        return view('admin.inventario.index', compact(
            'rows', 'clients', 'batches',
            'totalSwitches', 'totalClients', 'totalAreas',
            'totalConnections', 'unresolvedConns'
        ));
    }
}
