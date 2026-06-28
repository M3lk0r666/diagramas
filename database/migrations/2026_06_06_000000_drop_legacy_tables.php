<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina las tablas del flujo legacy (enero 2026).
     * Reemplazadas por: switches, switche_connections, upload_batches (abril 2026).
     */
    public function up(): void
    {
        Schema::dropIfExists('switch_connections');
        Schema::dropIfExists('switches_device');
        Schema::dropIfExists('processed_files');
    }

    /**
     * No se restauran: los modelos y servicios legacy ya fueron eliminados.
     */
    public function down(): void
    {
        // Restauración intencionalmente omitida.
    }
};
