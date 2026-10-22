<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'due_date']);
            $table->index(['customer_id', 'period_start']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['invoice_id', 'status']);
        });

        Schema::table('isolation_logs', function (Blueprint $table) {
            $table->index([
                'action',
                'execution_mode',
                'approval_status',
            ]);
            $table->index(['customer_id', 'invoice_id']);
        });

        Schema::table('pppoe_accounts', function (Blueprint $table) {
            $table->index(['mikrotik_device_id', 'is_present']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_status_created_at_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_status_due_date_index');
            $table->dropIndex('invoices_customer_id_period_start_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_invoice_id_status_index');
        });

        Schema::table('isolation_logs', function (Blueprint $table) {
            $table->dropIndex(
                'isolation_logs_action_execution_mode_approval_status_index'
            );
            $table->dropIndex(
                'isolation_logs_customer_id_invoice_id_index'
            );
        });

        Schema::table('pppoe_accounts', function (Blueprint $table) {
            $table->dropIndex(
                'pppoe_accounts_mikrotik_device_id_is_present_index'
            );
        });
    }
};
