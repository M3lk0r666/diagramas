<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Switche;
use Illuminate\Http\Request;

class SwitchPortController extends Controller
{
    /**
     * PATCH /switches/{switch}/ports/description
     *
     * Guarda la descripción de un puerto:
     *  - Si el puerto está en active_ports, actualiza su display_string
     *    (compatibilidad con diagramas y vistas existentes).
     *  - Siempre la persiste además en port_overrides, lo que permite
     *    documentar también puertos sin link o deshabilitados.
     */
    public function updateDescription(Switche $switch, Request $request)
    {
        $portNum = (string) $request->input('port');
        $desc    = trim((string) $request->input('display_string', ''));

        if ($portNum === '') {
            return response()->json(['ok' => false, 'error' => 'Puerto no especificado'], 422);
        }

        // 1) Actualizar en active_ports si existe
        $ports = $switch->active_ports ?? [];
        foreach ($ports as &$p) {
            if ((string) ($p['port'] ?? '') === $portNum) {
                $p['display_string'] = $desc;
                break;
            }
        }
        unset($p);
        $switch->active_ports = $ports;

        // 2) Persistir override (cubre puertos no activos)
        $overrides = $switch->port_overrides ?? [];
        $overrides[$portNum] = array_merge($overrides[$portNum] ?? [], ['description' => $desc]);
        $switch->port_overrides = $overrides;

        $switch->save();

        return response()->json(['ok' => true, 'display_string' => $desc]);
    }
}
