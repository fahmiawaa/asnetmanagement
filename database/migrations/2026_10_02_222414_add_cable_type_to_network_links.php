<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_links', function (Blueprint $table) {
            $table->string('cable_type')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('network_links', function (Blueprint $table) {
            $table->dropColumn('cable_type');
        });
    }
};
