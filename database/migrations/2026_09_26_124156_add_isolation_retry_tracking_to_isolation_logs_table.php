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
            $table->unsignedSmallInteger('attempt_count')
                ->default(0)
                ->after('executed_at');
            $table->timestamp('last_attempt_at')
                ->nullable()
                ->after('attempt_count');
            $table->timestamp('next_retry_at')
                ->nullable()
                ->after('last_attempt_at');
            $table->text('last_error')
                ->nullable()
                ->after('next_retry_at');

            $table->index(
                [
                    'action',
                    'approval_status',
                    'next_retry_at',
                    'id',
                ],
                'isolation_logs_retry_queue_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('isolation_logs', function (Blueprint $table) {
            $table->dropIndex('isolation_logs_retry_queue_index');
            $table->dropColumn([
                'attempt_count',
                'last_attempt_at',
                'next_retry_at',
                'last_error',
            ]);
        });
    }
};
