<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->json('topology_clusters')->nullable()->after('topology_image_path')
                  ->comment('Array de metadatos por clúster: [{hub_id,hub_label,hub_role,node_count,image_path}]');
        });
    }

    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropColumn('topology_clusters');
        });
    }
};
