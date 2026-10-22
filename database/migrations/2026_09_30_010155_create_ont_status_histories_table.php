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
        Schema::create('ont_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ont_device_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->timestamp('last_inform_at')->nullable();
            $table->decimal('rx_power_dbm', 6, 2)->nullable();
            $table->timestamp('observed_at')->index();
            $table->string('source', 30)->default('genieacs_sync');
            $table->timestamps();

            $table->index(
                ['ont_device_id', 'observed_at'],
                'ont_history_device_observed_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ont_status_histories');
    }
};
