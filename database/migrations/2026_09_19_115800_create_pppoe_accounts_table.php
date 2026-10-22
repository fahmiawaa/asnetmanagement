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
        Schema::create('pppoe_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mikrotik_device_id')
                ->constrained('mikrotik_devices')
                ->cascadeOnDelete();

            $table->string('username');
            $table->string('service')->nullable();
            $table->string('profile')->nullable();

            $table->boolean('disabled')->default(false);

            $table->string('local_address')->nullable();
            $table->string('remote_address')->nullable();

            $table->text('comment')->nullable();

            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique([
                'mikrotik_device_id',
                'username',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pppoe_accounts');
    }
};
