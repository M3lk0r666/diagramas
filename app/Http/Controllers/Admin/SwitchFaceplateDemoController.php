<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PortStatus;
use App\Http\Controllers\Controller;

/**
 * GET /switches-demo/faceplate
 * Ejemplo de uso de <x-switch-faceplate> con datos de prueba
 * (48 RJ45 + 4 SFP+), sin tocar la base de datos.
 */
class SwitchFaceplateDemoController extends Controller
{
    public function __invoke()
    {
        $device = [
            'ip'       => '192.168.1.61',
            'mac'      => 'DCE650AEC4E8',
            'software' => '33.2.1.12',
            'model'    => 'X440-G2-48p-10G4',
            'serial'   => '2236G-01026',
            'make'     => 'EXOS',
        ];

        $activos = [1, 2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 14, 20, 22, 25, 29, 30, 37, 38, 42, 47];
        $descs   = [
            2 => 'beto2', 3 => 'beto6', 5 => 'casa-1233', 6 => 'AP-GYM-1P-6.72',
            20 => 'ejemplo-clic', 22 => 'eso-es-todo-1223.65', 25 => 'to-algo-29',
        ];

        $ports = collect();

        // 48 puertos RJ45
        foreach (range(1, 48) as $n) {
            $status = match (true) {
                $n === 46            => PortStatus::Reassigned,
                $n === 48            => PortStatus::Disabled,
                in_array($n, $activos) => PortStatus::Active,
                default              => PortStatus::NoLink,
            };

            $ports->push([
                'number'      => $n,
                'slot'        => null,
                'type'        => 'RJ45',
                'status'      => $status,
                'vlan'        => in_array($n, [20, 23, 24, 25]) ? '5' : '4',
                'description' => $descs[$n] ?? '',
                'speed'       => $status === PortStatus::Active ? '1000' : '',
                'duplex'      => $status === PortStatus::Active ? 'FULL' : '',
            ]);
        }

        // 4 puertos SFP+ (49-52)
        foreach (range(49, 52) as $n) {
            $ports->push([
                'number'      => $n,
                'slot'        => null,
                'type'        => 'SFP+',
                'status'      => $n === 49 ? PortStatus::Active : PortStatus::NoLink,
                'vlan'        => '1',
                'description' => $n === 49 ? 'uplink-core' : '',
                'speed'       => $n === 49 ? '10G' : '',
                'duplex'      => $n === 49 ? 'FULL' : '',
            ]);
        }

        return view('admin.switches.faceplate-demo', [
            'device' => $device,
            'ports'  => $ports,
        ]);
    }
}
