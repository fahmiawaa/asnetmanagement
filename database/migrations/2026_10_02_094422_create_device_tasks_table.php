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
        Schema::create('device_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ont_device_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('payload_encrypted')->nullable();
            $table->json('safe_summary')->nullable();
            $table->string('remote_task_id')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(
                ['ont_device_id', 'status'],
                'task_device_status_idx'
            );
            $table->index(['type', 'created_at'], 'task_type_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tasks');
    }
};
