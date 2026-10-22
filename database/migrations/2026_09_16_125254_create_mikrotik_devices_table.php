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
        Schema::create('mikrotik_devices', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('host');
            $table->unsignedInteger('api_port')->default(8728);
            $table->boolean('use_ssl')->default(false);

            $table->string('username');
            $table->text('encrypted_password');

            $table->string('connection_status')->default('unknown');
            $table->timestamp('last_sync_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotik_devices');
    }
};
