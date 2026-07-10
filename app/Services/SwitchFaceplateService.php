<?php

namespace App\Services;

use App\Enums\PortStatus;
use App\Models\Switche;
use Illuminate\Support\Collection;

/**
 * Construye los datos que consume <x-switch-faceplate>.
 *
 * A diferencia de active_ports (solo E+A), aquí se parsea el
 * "Port Summary" completo de raw_sections para obtener TODOS los
 * puertos con su estado real (E/D + A/R/NP), y encima se aplican:
 *   1. display_string editados guardados en active_ports
 *   2. port_overrides (descripción editada / estado re-asignado)
 */
class SwitchFaceplateService
{
    public function device(Switche $switch): array
    {
        return [
            'ip'       => $switch->management_ip ?? '—',
            'mac'      => $switch->system_mac ?? '—',
            'software' => $switch->firmware_version ?? '—',
            'model'    => $switch->system_type ?? '—',
            'serial'   => $switch->serial_number ?? '—',
            'make'     => 'EXOS',
        ];
    }

    public function ports(Switche $switch): Collection
    {
        $raw    = $switch->raw_sections['show ports no-refresh'] ?? '';
        $parsed = $this->parsePortSummary($raw);

        // Fallback: sin sección raw, usar solo los puertos activos guardados
        if ($parsed->isEmpty()) {
            $parsed = collect($switch->active_ports ?? [])->map(fn ($p) => [
                'id'     => (string) $p['port'],
                'desc'   => trim($p['display_string'] ?? ''),
                'vlan'   => trim($p['vlan_name'] ?? ''),
                'status' => PortStatus::Active,
                'speed'  => (string) ($p['speed_actual'] ?? ''),
                'duplex' => (string) ($p['duplex_actual'] ?? ''),
            ]);
        }

        // ── Mezclar ediciones guardadas en active_ports (descripción, vlan, velocidad) ──
        $active = collect($switch->active_ports ?? [])->keyBy(fn ($p) => (string) $p['port']);

        $parsed = $parsed->map(function ($p) use ($active) {
            if ($a = $active->get($p['id'])) {
                $p['desc']   = trim($a['display_string'] ?? $p['desc']);
                $p['vlan']   = trim($a['vlan_name'] ?? $p['vlan']);
                $p['speed']  = (string) ($a['speed_actual'] ?? $p['speed']);
                $p['duplex'] = (string) ($a['duplex_actual'] ?? $p['duplex']);
            }
            return $p;
        });

        // ── Overrides propios del sistema (descripciones editadas, re-asignados) ──
        $overrides = $switch->port_overrides ?? [];

        $parsed = $parsed->map(function ($p) use ($overrides) {
            $o = $overrides[$p['id']] ?? null;
            if ($o) {
                if (array_key_exists('description', $o)) {
                    $p['desc'] = (string) $o['description'];
                }
                if (!empty($o['status']) && ($s = PortStatus::tryFrom($o['status']))) {
                    $p['status'] = $s;
                }
            }
            return $p;
        });

        // ── Slot / número / tipo (RJ45 vs SFP+) ──
        $bySlot = $parsed->groupBy(function ($p) {
            return str_contains($p['id'], ':') ? (int) explode(':', $p['id'])[0] : 0;
        });

        $modelCopper = $this->copperCountFromModel($switch->system_type ?? '');

        $result = collect();

        foreach ($bySlot as $slotKey => $slotPorts) {
            $numbers = $slotPorts->map(fn ($p) => $this->portNumber($p['id']));
            $copper  = $this->copperCountForSlot($numbers->max() ?? 0, $bySlot->count() > 1 ? null : $modelCopper);

            foreach ($slotPorts as $p) {
                $n = $this->portNumber($p['id']);
                $result->push([
                    'id'          => $p['id'],
                    'number'      => $n,
                    'slot'        => $slotKey ?: null,
                    'type'        => $n > $copper ? 'SFP+' : 'RJ45',
                    'status'      => $p['status'],
                    'vlan'        => $p['vlan'],
                    'description' => $p['desc'],
                    'speed'       => $p['speed'],
                    'duplex'      => $p['duplex'],
                ]);
            }
        }

        return $result;
    }

    /**
     * Parseo por posiciones de columna (el Display String puede estar vacío
     * o pegado al VLAN Name, p.ej. "TO-ADMIN-105.243(0016)").
     */
    private function parsePortSummary(string $raw): Collection
    {
        $lines  = preg_split('/\r?\n/', $raw) ?: [];
        $header = collect($lines)->first(fn ($l) => preg_match('/^Port\s+Display\s+VLAN/', $l));

        if (!$header) {
            return collect();
        }

        $posDisplay = strpos($header, 'Display');
        $posVlan    = strpos($header, 'VLAN');
        $posState   = strpos($header, 'Port', $posVlan);

        $ports = collect();

        foreach ($lines as $line) {
            if (!preg_match('/^\s*\d+(:\d+)?\s/', $line)) {
                continue;
            }

            $rest = preg_split('/\s+/', trim((string) substr($line, $posState))) ?: [];
            [$portState, $linkState, $speed, $duplex] = array_pad($rest, 4, '');

            if (!in_array(strtoupper($portState), ['E', 'D'], true)) {
                continue; // línea desalineada o de otro tipo
            }

            $ports->push([
                'id'     => trim((string) substr($line, 0, $posDisplay)),
                'desc'   => trim((string) substr($line, $posDisplay, $posVlan - $posDisplay)),
                'vlan'   => trim((string) substr($line, $posVlan, $posState - $posVlan), " \t()"),
                'status' => PortStatus::fromPortSummary($portState, $linkState),
                'speed'  => (string) $speed,
                'duplex' => (string) $duplex,
            ]);
        }

        return $ports;
    }

    private function portNumber(string $id): int
    {
        return (int) (str_contains($id, ':') ? explode(':', $id)[1] : $id);
    }

    /** "X440G2-48p-10G4" → 48 ; "X440G2-24t-10G4" → 24 */
    private function copperCountFromModel(string $model): ?int
    {
        return preg_match('/-(\d+)[pt]\b/i', $model, $m) ? (int) $m[1] : null;
    }

    /**
     * Puertos de cobre del slot. Con modelo conocido se usa ese dato;
     * si no (stacks con modelos mixtos), se infiere del puerto más alto:
     *   ≤16 → equipo solo fibra, 17–28 → 24p + uplinks, >28 → 48p + uplinks.
     */
    private function copperCountForSlot(int $maxPort, ?int $modelCopper): int
    {
        if ($modelCopper !== null) {
            return $modelCopper;
        }

        return match (true) {
            $maxPort <= 16 => 0,
            $maxPort <= 28 => 24,
            default        => 48,
        };
    }
}
