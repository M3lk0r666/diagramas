<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Switche;
use App\Models\SwitcheConnection;
use App\Models\UploadBatch;
use Illuminate\Support\Facades\Storage;

class ClientManagerController extends Controller
{
    // ─── GET /clientes-manager → listado de clientes ─────────────────────────
    public function index()
    {
        $clients = Client::withCount('batches')
            ->orderBy('name')
            ->get();

        return view('admin.client-manager.index', compact('clients'));
    }

    // ─── GET /clientes-manager/{client} → áreas del cliente ──────────────────
    public function show(Client $client)
    {
        $batches = $client->batches()
            ->withCount(['switches', 'switches as ok_count' => fn ($q) => $q->where('parse_status', 'ok')])
            ->latest()
            ->get();

        $totalSwitches = $batches->sum('switches_count');
        $okSwitches    = $batches->sum('ok_count');

        return view('admin.client-manager.show', compact('client', 'batches', 'totalSwitches', 'okSwitches'));
    }

    // ─── GET /clientes-manager/{client}/batches/{batch} → detalle ────────────
    public function batchShow(Client $client, UploadBatch $batch)
    {
        abort_if($batch->client_id !== $client->id, 404);

        $switches = $batch->switches()
            ->orderBy('sys_name')
            ->get(['id', 'sys_name', 'system_type', 'management_ip', 'system_mac',
                   'parse_status', 'config_path', 'original_filename', 'created_at', 'upload_batch_id']);

        return view('admin.client-manager.batch', compact('client', 'batch', 'switches'));
    }

    // ─── DELETE /clientes-manager/{client}/batches/{batch} ───────────────────
    public function destroyBatch(Client $client, UploadBatch $batch)
    {
        abort_if($batch->client_id !== $client->id, 404);

        $switchIds = $batch->switches()->pluck('id');

        // Borrar conexiones que involucran switches del batch
        SwitcheConnection::whereIn('src_switch_id', $switchIds)
            ->orWhereIn('dst_switch_id', $switchIds)
            ->delete();

        // Borrar archivos de config de cada switch
        foreach ($batch->switches()->get(['id', 'config_path']) as $sw) {
            if ($sw->config_path && Storage::exists($sw->config_path)) {
                Storage::delete($sw->config_path);
            }
        }

        // Borrar switches
        $batch->switches()->delete();

        // Borrar archivos de topología
        Storage::disk('public')->deleteDirectory("batches/{$batch->id}");

        // Borrar batch
        $batch->delete();

        return redirect()
            ->route('admin.clients.manage.show', $client)
            ->with('success', "Área \"{$batch->name}\" eliminada correctamente.");
    }

    // ─── DELETE /clientes-manager/{client}/batches/{batch}/switches/{switch} ──
    public function destroySwitch(Client $client, UploadBatch $batch, Switche $switch)
    {
        abort_if($batch->client_id !== $client->id, 404);
        abort_if($switch->upload_batch_id !== $batch->id, 404);

        // Borrar conexiones
        SwitcheConnection::where('src_switch_id', $switch->id)
            ->orWhere('dst_switch_id', $switch->id)
            ->delete();

        // Borrar config
        if ($switch->config_path && Storage::exists($switch->config_path)) {
            Storage::delete($switch->config_path);
        }

        $switch->delete();

        // Actualizar contadores del batch
        $batch->decrement('total_files');

        return redirect()
            ->route('admin.clients.manage.batch', [$client, $batch])
            ->with('success', "Switch \"{$switch->sys_name}\" eliminado.");
    }
}
