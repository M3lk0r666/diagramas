<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switches', function (Blueprint $table) {
            $table->boolean('is_stacked')->default(false)->after('system_type');
            $table->string('stack_topology')->nullable()->after('is_stacked');
            $table->json('stack_members')->nullable()->after('stack_topology');
        });
    }

    public function down(): void
    {
        Schema::table('switches', function (Blueprint $table) {
            $table->dropColumn(['is_stacked', 'stack_topology', 'stack_members']);
        });
    }
};
