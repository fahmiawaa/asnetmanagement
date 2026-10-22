<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pppoe_accounts', function (Blueprint $table) {
            $table->text('encrypted_password')->nullable()->after('username');
            $table->string('provision_status')->default('synced')->after('is_present');
            $table->timestamp('provisioned_at')->nullable()->after('provision_status');
            $table->text('provision_error')->nullable()->after('provisioned_at');
        });
    }

    public function down(): void
    {
        Schema::table('pppoe_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'encrypted_password',
                'provision_status',
                'provisioned_at',
                'provision_error',
            ]);
        });
    }
};
