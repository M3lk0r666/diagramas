<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides por puerto propios del sistema (no vienen del config):
 *   { "14": {"description": "AP-GYM"}, "2:7": {"status": "reassigned"} }
 * Permite editar la descripción de CUALQUIER puerto (no solo activos)
 * y marcar puertos como re-asignados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switches', function (Blueprint $table) {
            $table->json('port_overrides')->nullable()->after('active_ports');
        });
    }

    public function down(): void
    {
        Schema::table('switches', function (Blueprint $table) {
            $table->dropColumn('port_overrides');
        });
    }
};
