<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_links', function (Blueprint $table) {
            $table->unsignedBigInteger('from_node_id')->nullable()->change();
            $table->unsignedBigInteger('to_node_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('network_links', function (Blueprint $table) {
            $table->unsignedBigInteger('from_node_id')->nullable(false)->change();
            $table->unsignedBigInteger('to_node_id')->nullable(false)->change();
        });
    }
};
