<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicateInvoicePeriods = DB::table('invoices')
            ->select(['customer_id', 'period_start', 'period_end'])
            ->groupBy(['customer_id', 'period_start', 'period_end'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateInvoicePeriods) {
            throw new RuntimeException(
                'Migrasi dibatalkan: terdapat invoice dengan customer dan periode yang sama. '
                .'Gabungkan atau hapus duplikat sebelum menjalankan migrasi kembali.'
            );
        }

        $hasDuplicateIsolationActions = DB::table('isolation_logs')
            ->whereNotNull('invoice_id')
            ->select(['customer_id', 'invoice_id', 'action'])
            ->groupBy(['customer_id', 'invoice_id', 'action'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateIsolationActions) {
            throw new RuntimeException(
                'Migrasi dibatalkan: terdapat log isolasi duplikat untuk invoice yang sama. '
                .'Selesaikan duplikat sebelum menjalankan migrasi kembali.'
            );
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->index(
                ['status', 'id'],
                'customers_status_id_index'
            );
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(
                ['customer_id', 'period_start', 'period_end'],
                'invoices_customer_period_unique'
            );
        });

        Schema::table('isolation_logs', function (Blueprint $table) {
            $table->unique(
                ['customer_id', 'invoice_id', 'action'],
                'isolation_logs_customer_invoice_action_unique'
            );
            $table->index(
                ['action', 'approval_status', 'id'],
                'isolation_logs_pending_action_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('isolation_logs', function (Blueprint $table) {
            $table->dropIndex('isolation_logs_pending_action_index');
            $table->dropUnique(
                'isolation_logs_customer_invoice_action_unique'
            );
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_customer_period_unique');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_status_id_index');
        });
    }
};
