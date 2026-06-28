<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switches', function (Blueprint $table) {
            // IP de gestión extraída del encabezado del archivo de backup
            $table->string('management_ip')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('switches', function (Blueprint $table) {
            $table->dropColumn('management_ip');
        });
    }
};
