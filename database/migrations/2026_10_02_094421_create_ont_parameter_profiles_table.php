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
        Schema::create('ont_parameter_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('manufacturer')->nullable()->index();
            $table->string('oui')->nullable();
            $table->string('product_class')->nullable()->index();
            $table->string('pon_mode', 20)->nullable();
            $table->json('ssid_paths');
            $table->json('wifi_password_paths');
            $table->json('rx_power_rules')->nullable();
            $table->json('temperature_rules')->nullable();
            $table->json('wan_ip_paths')->nullable();
            $table->json('active_client_paths')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(
                ['manufacturer', 'product_class', 'pon_mode'],
                'profile_match_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ont_parameter_profiles');
    }
};
