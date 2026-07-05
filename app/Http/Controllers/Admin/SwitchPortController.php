<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Switche;
use Illuminate\Http\Request;

class SwitchPortController extends Controller
{
    /**
     * PATCH /switches/{switch}/ports/description
     * Updates display_string for a specific port entry in the active_ports JSON array.
     */
    public function updateDescription(Switche $switch, Request $request)
    {
        $portNum = (string) $request->input('port');
        $desc    = trim((string) $request->input('display_string', ''));

        $ports = $switch->active_ports ?? [];
        $found = false;

        foreach ($ports as &$p) {
            if ((string) ($p['port'] ?? '') === $portNum) {
                $p['display_string'] = $desc;
                $found = true;
                break;
            }
        }
        unset($p);

        if (! $found) {
            return response()->json(['ok' => false, 'error' => 'Puerto no encontrado'], 404);
        }

        $switch->active_ports = $ports;
        $switch->save();

        return response()->json(['ok' => true, 'display_string' => $desc]);
    }
}
