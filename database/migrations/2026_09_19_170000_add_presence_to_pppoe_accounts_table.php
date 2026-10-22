<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pppoe_accounts', function (Blueprint $table) {
            $table->boolean('is_present')
                ->default(true)
                ->after('disabled');
        });
    }

    public function down(): void
    {
        Schema::table('pppoe_accounts', function (Blueprint $table) {
            $table->dropColumn('is_present');
        });
    }
};
