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
        Schema::table('isolation_logs', function (Blueprint $table) {
            $table->string('approval_status')
                ->default('pending')
                ->after('execution_mode');

            $table->foreignId('approved_by')
                ->nullable()
                ->after('approval_status')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('isolation_logs', function (Blueprint $table) {
            $table->dropForeign([
                'approved_by',
            ]);

            $table->dropColumn([
                'approval_status',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
