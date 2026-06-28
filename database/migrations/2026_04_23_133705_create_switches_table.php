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
        Schema::create('switches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_batch_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('sys_name')->nullable();
            $table->string('sys_location')->nullable();
            $table->string('sys_contact')->nullable();
            $table->string('system_mac', 23)->nullable()->index(); // DC:E6:50:B1:87:07
            $table->string('system_type')->nullable();
            $table->string('serial_number')->nullable();           // SB012427G-00181
            $table->string('firmware_version')->nullable();
            $table->json('vlans')->nullable();           // array de objetos vlan
            $table->json('ip_routes')->nullable();       // array de rutas filtradas
            $table->json('edp_ports')->nullable();       // array de vecinos EDP
            $table->json('active_ports')->nullable();    // puertos E+A de show ports
            $table->json('raw_sections')->nullable();    // texto crudo por sección
            $table->enum('parse_status', ['ok','error'])->default('ok');
            $table->text('parse_error')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('switches');
    }
};
