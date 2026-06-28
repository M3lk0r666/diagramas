<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('upload_batches', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Client::class);
            $table->dropColumn('client_id');
        });

        Schema::dropIfExists('clients');
    }
};
