<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortMapping extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'ip',
        'origin_config',
        'dest_config',
        'mapping_state',
    ];

    /**
     * Los campos JSON se auto-convierten a array al leerlos de la BD.
     */
    protected $casts = [
        'origin_config' => 'array',
        'dest_config'   => 'array',
        'mapping_state' => 'array',
    ];

    /**
     * Un mapeo pertenece al usuario que lo creó.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Accesores de conveniencia ───────────────────────────────────────────────

    /**
     * Descripción corta del origen: "48 cu + 4 SFP" o "2×24 cu + 4 SFP"
     */
    public function getOriginSummaryAttribute(): string
    {
        $c = $this->origin_config ?? [];
        $type  = $c['type']  ?? '?';
        $fiber = $c['fiber'] ?? 0;
        $label = $type === '2x24' ? '2×24 cu' : ($type . ' cu');
        return $label . ($fiber ? ' + ' . $fiber . ' SFP' : '');
    }

    /**
     * Descripción corta del destino: "48 cu + 4 SFP"
     */
    public function getDestSummaryAttribute(): string
    {
        $c = $this->dest_config ?? [];
        $cu    = $c['copper'] ?? '?';
        $fiber = $c['fiber']  ?? 0;
        return $cu . ' cu' . ($fiber ? ' + ' . $fiber . ' SFP' : '');
    }
}
