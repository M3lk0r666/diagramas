<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de mapeos de puertos.
     * Cada registro representa un plan de migración switch-a-switch.
     */
    public function up(): void
    {
        Schema::create('port_mappings', function (Blueprint $table) {
            $table->id();

            // Propietario del mapeo (solo él puede verlo/editarlo)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nombre descriptivo: "Migración IDF-3 Floor 2"
            $table->string('name', 120);

            // IP compartida que se conserva tras la migración
            $table->string('ip', 45)->nullable();

            // Configuración del switch origen
            // Estructura: { type: '48'|'24'|'2x24', fiber: 0|4|6, model: '', serials: ['',''] }
            $table->json('origin_config');

            // Configuración del switch destino
            // Estructura: { copper: 24|48, fiber: 0|4|6, model: '', serial: '' }
            $table->json('dest_config');

            // Estado completo de todos los puertos (mismo esquema que el prototipo JS)
            // Estructura: el objeto `state` serializado (ip, origin.ports, dest.ports, etc.)
            $table->json('mapping_state');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('port_mappings');
    }
};
