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
        Schema::create('network_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_node_id')
                ->constrained('network_nodes')
                ->cascadeOnDelete();
            $table->foreignId('from_port_id')
                ->nullable()
                ->unique()
                ->constrained('network_ports')
                ->nullOnDelete();
            $table->foreignId('to_node_id')
                ->unique()
                ->constrained('network_nodes')
                ->cascadeOnDelete();
            $table->foreignId('to_port_id')
                ->nullable()
                ->unique()
                ->constrained('network_ports')
                ->nullOnDelete();
            $table->json('path_coordinates')->nullable();
            $table->decimal('cable_length_m', 10, 2)->nullable();
            $table->decimal('measured_attenuation_db', 6, 2)->nullable();
            $table->decimal('calculated_attenuation_db', 6, 2)->nullable();
            $table->string('status', 20)->default('unknown');
            $table->timestamps();

            $table->unique(['from_node_id', 'to_node_id'], 'network_links_from_to_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_links');
    }
};
