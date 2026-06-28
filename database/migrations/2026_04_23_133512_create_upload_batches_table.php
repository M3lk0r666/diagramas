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
        Schema::create('upload_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();          // nombre del lote
            $table->enum('status', ['pending','processing','completed','failed'])->default('pending');
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('error_log')->nullable();       // [{file, message, line}]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_batches');
    }
};
