<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('switche_connections', function (Blueprint $table) {
            $table->id();
            // Switch origen
            $table->foreignId('src_switch_id')->constrained('switches')->cascadeOnDelete();
            $table->string('src_mac', 23);              // MAC del switch origen
            $table->string('src_port', 10);             // puerto origen (ej. 22)
            // Switch destino (puede no estar en la BD)
            $table->foreignId('dst_switch_id')->nullable()->constrained('switches')->nullOnDelete();
            $table->string('dst_mac', 23);              // MAC destino (00:00:dc:...)
            $table->string('dst_port', 10);             // puerto destino (ej. 1:23)
            $table->string('neighbor_name')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->unsignedTinyInteger('num_vlans')->nullable();
            $table->timestamps();

            $table->index(['src_mac', 'dst_mac']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('switche_connections');
    }
};
