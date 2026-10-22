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
        Schema::create('network_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_node_id')
                ->constrained('network_nodes')
                ->cascadeOnDelete();
            $table->unsignedInteger('port_number');
            $table->string('port_type', 20);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('technology', 20)->default('unknown');
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->unique(
                ['network_node_id', 'port_number', 'port_type'],
                'network_ports_node_number_type_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_ports');
    }
};
