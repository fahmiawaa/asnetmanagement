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
            $table->string('rx_power_source', 100)->nullable()->after('rx_power_quality');
            $table->timestamp('rx_power_collected_at')->nullable()->after('rx_power_source');
            $table->foreignId('mapped_by')
                ->nullable()
                ->after('customer_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('mapped_at')->nullable()->after('mapped_by');
            $table->string('mapping_method', 30)->nullable()->after('mapped_at');
            $table->unsignedTinyInteger('mapping_confidence')->nullable()->after('mapping_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ont_devices', function (Blueprint $table) {
            $table->dropForeign(['mapped_by']);
            $table->dropColumn([
                'mapped_by',
                'rx_power_source',
                'rx_power_collected_at',
                'mapped_at',
                'mapping_method',
                'mapping_confidence',
            ]);
        });
    }
};
