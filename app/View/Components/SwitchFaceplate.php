<?php

namespace App\View\Components;

use App\Enums\PortStatus;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-switch-faceplate :device="$device" :ports="$ports" :update-url="..." />
 *
 * $device : ['ip','mac','software','model','serial','make']
 * $ports  : colección/array de puertos con:
 *           number (int, número dentro del slot), slot (int|null),
 *           type ('RJ45'|'SFP+'), status (PortStatus|string),
 *           vlan (string), description (string), speed (string), duplex (string)
 *
 * La clase agrupa por slot, separa RJ45 / SFP+, arma columnas
 * impar-arriba / par-abajo y bloques de 8 columnas (16 puertos).
 */
class SwitchFaceplate extends Component
{
    public const BLOCK_COLS = 8;

    public array $device;
    public ?string $updateUrl;

    /** @var array<int,array{slot:int|null,label:?string,blocks:array,sfp:array}> */
    public array $slots = [];

    /** @var array<string,int> conteo por estado (value del enum => n) */
    public array $counts = [];

    /** @var array<int|string, array> puertos normalizados agrupados por slot key */
    public array $portsBySlot = [];

    public function __construct(array $device, Collection|array $ports, ?string $updateUrl = null)
    {
        $this->device    = $device;
        $this->updateUrl = $updateUrl;

        $ports = collect($ports)->map(fn ($p) => $this->normalize((array) $p));

        // Conteo global por estado (para el resumen)
        foreach (PortStatus::cases() as $status) {
            $n = $ports->where('status', $status)->count();
            if ($n > 0) {
                $this->counts[$status->value] = $n;
            }
        }

        // Puertos por slot para la tabla lateral de activos
        $this->portsBySlot = $ports
            ->groupBy(fn ($p) => $p['slot'] ?? 0)
            ->map(fn ($sp) => $sp->sortBy('number')->values()->all())
            ->all();

        // Un faceplate por slot (null = standalone)
        $this->slots = $ports
            ->groupBy(fn ($p) => $p['slot'] ?? 0)
            ->sortKeys()
            ->map(function (Collection $slotPorts, $slotKey) {
                $slotPorts = $slotPorts->sortBy('number')->values();

                $rj45 = $slotPorts->where('type', 'RJ45')->values();
                $sfp  = $slotPorts->where('type', '!=', 'RJ45')->values();

                return [
                    'slot'   => $slotKey ?: null,
                    'label'  => $slotKey ? "Slot {$slotKey}" : null,
                    'blocks' => array_chunk($this->toColumns($rj45), self::BLOCK_COLS),
                    'sfp'    => $this->toColumns($sfp),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Convierte una lista ordenada de puertos en columnas
     * [impar (fila superior), par (fila inferior)].
     */
    private function toColumns(Collection $ports): array
    {
        $columns = [];

        foreach ($ports->chunk(2) as $pair) {
            $pair = $pair->values();
            $columns[] = [
                'top'    => $pair->get(0),
                'bottom' => $pair->get(1), // null si el total es impar
            ];
        }

        return $columns;
    }

    private function normalize(array $p): array
    {
        $status = $p['status'] ?? PortStatus::NoLink;

        if (is_string($status)) {
            $status = PortStatus::tryFrom($status) ?? PortStatus::NoLink;
        }

        $slot   = isset($p['slot']) && $p['slot'] !== null ? (int) $p['slot'] : null;
        $number = (int) ($p['number'] ?? 0);

        return [
            'number'      => $number,
            'slot'        => $slot,
            // Identificador real del puerto ("14" o "2:14") — se usa para guardar la descripción
            'id'          => $p['id'] ?? ($slot ? "{$slot}:{$number}" : (string) $number),
            'type'        => $p['type'] ?? 'RJ45',
            'status'      => $status,
            'vlan'        => (string) ($p['vlan'] ?? ''),
            'description' => (string) ($p['description'] ?? ''),
            'speed'       => (string) ($p['speed'] ?? ''),
            'duplex'      => (string) ($p['duplex'] ?? ''),
        ];
    }

    public function render(): View
    {
        return view('components.switch-faceplate');
    }
}
