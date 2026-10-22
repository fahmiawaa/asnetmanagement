<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('qris_reference')->nullable()->unique()->after('method');
            $table->timestamp('qris_created_at')->nullable()->after('qris_reference');
            $table->timestamp('qris_expires_at')->nullable()->after('qris_created_at');
            $table->index(['method', 'qris_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['method', 'qris_expires_at']);
            $table->dropUnique(['qris_reference']);
            $table->dropColumn([
                'qris_reference',
                'qris_created_at',
                'qris_expires_at',
            ]);
        });
    }
};
