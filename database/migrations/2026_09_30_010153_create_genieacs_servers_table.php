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
        Schema::create('genieacs_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url')->unique();
            $table->string('username')->nullable();
            $table->text('encrypted_password')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('connection_status', 20)->default('unknown')->index();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable()->index();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('genieacs_servers');
    }
};
