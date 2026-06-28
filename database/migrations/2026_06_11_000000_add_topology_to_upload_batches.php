<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->json('topology_json')->nullable()->after('error_log')
                  ->comment('JSON de nodos/aristas para el diagrama exportado');
            $table->string('topology_image_path')->nullable()->after('topology_json')
                  ->comment('Ruta relativa al PNG generado (storage/app/)');
        });
    }

    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropColumn(['topology_json', 'topology_image_path']);
        });
    }
};
