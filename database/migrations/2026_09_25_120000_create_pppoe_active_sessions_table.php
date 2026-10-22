<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pppoe_active_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_device_id')
                ->constrained('mikrotik_devices')
                ->cascadeOnDelete();
            $table->string('router_session_id');
            $table->string('username');
            $table->string('service')->nullable();
            $table->string('profile')->nullable();
            $table->string('address')->nullable();
            $table->string('caller_id')->nullable();
            $table->string('uptime')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->unique(
                ['mikrotik_device_id', 'router_session_id'],
                'pppoe_active_sessions_device_router_unique'
            );
            $table->index(
                ['mikrotik_device_id', 'username', 'is_active'],
                'pppoe_active_sessions_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pppoe_active_sessions');
    }
};
