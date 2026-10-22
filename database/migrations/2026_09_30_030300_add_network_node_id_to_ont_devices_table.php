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
        Schema::table('ont_devices', function (Blueprint $table) {
            $table->foreignId('network_node_id')
                ->nullable()
                ->unique()
                ->after('customer_id')
                ->constrained('network_nodes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ont_devices', function (Blueprint $table) {
            $table->dropForeign(['network_node_id']);
            $table->dropUnique(['network_node_id']);
            $table->dropColumn('network_node_id');
        });
    }
};
