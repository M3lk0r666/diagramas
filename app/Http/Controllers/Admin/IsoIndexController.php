<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;

class IsoIndexController extends Controller
{
    /**
     * GET /admin/iso
     * Selector de cliente para la Vista Isométrica 3D.
     */
    public function index()
    {
        $clients = Client::with([
            'batches:id,client_id,name,created_at',
            'batches.switches' => fn ($q) => $q
                ->where('parse_status', 'ok')
                ->select(['id', 'upload_batch_id']),
        ])
        ->whereHas('batches.switches', fn ($q) => $q->where('parse_status', 'ok'))
        ->orderBy('name')
        ->get(['id', 'name', 'updated_at']);

        return view('admin.iso.index', compact('clients'));
    }
}
