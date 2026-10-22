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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('pppoe_account_id')
                ->nullable()
                ->unique()
                ->after('internet_package_id')
                ->constrained('pppoe_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign([
                'pppoe_account_id',
            ]);

            $table->dropUnique([
                'pppoe_account_id',
            ]);

            $table->dropColumn('pppoe_account_id');
        });
    }
};
