<?php

namespace App\Enums;

/**
 * Estado de un puerto en el faceplate.
 * Para agregar un estado nuevo: añadir el case y sus entradas en label()
 * y cssClass(), más los estilos .jack.{clase} en switch-faceplate.blade.php.
 */
enum PortStatus: string
{
    case Active     = 'active';      // Port State E + Link State A
    case NoLink     = 'nolink';      // Port State E + Link State R/NP/…
    case Disabled   = 'disabled';    // Port State D
    case Reassigned = 'reassigned';  // Estado propio del sistema (override manual)

    public function label(): string
    {
        return match ($this) {
            self::Active     => 'Activo',
            self::NoLink     => 'Sin link',
            self::Disabled   => 'Deshabilitado',
            self::Reassigned => 'Re-asignado',
        };
    }

    /** Clase CSS del jack ('' = estilo base "sin link"). */
    public function cssClass(): string
    {
        return match ($this) {
            self::Active     => 'active',
            self::NoLink     => '',
            self::Disabled   => 'disabled',
            self::Reassigned => 'reassigned',
        };
    }

    /** Deriva el estado desde las columnas Port State / Link State del "Port Summary" de EXOS. */
    public static function fromPortSummary(string $portState, string $linkState): self
    {
        if (strtoupper(trim($portState)) === 'D') {
            return self::Disabled;
        }

        return strtoupper(trim($linkState)) === 'A' ? self::Active : self::NoLink;
    }
}
