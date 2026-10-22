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
        Schema::create('isolation_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();

            $table->string('action');
            $table->text('reason')->nullable();

            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();

            $table->string('execution_mode')
                ->default('simulation');

            $table->foreignId('executed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('executed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isolation_logs');
    }
};
