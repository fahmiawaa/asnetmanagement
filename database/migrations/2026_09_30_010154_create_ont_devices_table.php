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
        Schema::create('ont_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('genieacs_server_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('genieacs_device_id')->unique();
            $table->string('serial_number')->nullable()->index();
            $table->string('pon_mac')->nullable()->index();
            $table->string('manufacturer')->nullable()->index();
            $table->string('oui')->nullable();
            $table->string('product_class')->nullable()->index();
            $table->string('software_version')->nullable();
            $table->string('pon_mode', 20)->default('unknown');
            $table->ipAddress('ip_address')->nullable();
            $table->ipAddress('pppoe_ip')->nullable();
            $table->string('pppoe_mac')->nullable();
            $table->unsignedInteger('inform_interval_seconds')->nullable();
            $table->timestamp('last_inform_at')->nullable()->index();
            $table->string('connection_status', 20)->default('unknown')->index();
            $table->string('rx_power_raw')->nullable();
            $table->decimal('rx_power_dbm', 6, 2)->nullable()->index();
            $table->string('rx_power_quality', 20)->default('unknown')->index();
            $table->string('temperature_raw')->nullable();
            $table->decimal('temperature_celsius', 6, 2)->nullable();
            $table->unsignedInteger('active_wifi_devices')->nullable();
            $table->unsignedBigInteger('device_uptime_seconds')->nullable();
            $table->unsignedBigInteger('pppoe_uptime_seconds')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('missing_since')->nullable();
            $table->timestamps();

            $table->index(
                ['genieacs_server_id', 'connection_status', 'last_inform_at'],
                'ont_server_status_inform_idx'
            );
            $table->index(
                ['manufacturer', 'product_class'],
                'ont_manufacturer_product_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ont_devices');
    }
};
