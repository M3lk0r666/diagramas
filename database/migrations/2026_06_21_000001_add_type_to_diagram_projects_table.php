<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagram_projects', function (Blueprint $table) {
            // 'png' = ensamblador de imágenes de topología
            // 'vectorial' = diagrama global vectorial con iconos de switches desde BD
            $table->enum('type', ['png', 'vectorial'])->default('png')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('diagram_projects', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
