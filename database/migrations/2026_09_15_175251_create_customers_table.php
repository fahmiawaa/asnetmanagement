<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('customer_code')->unique();
            $table->string('name');
            $table->string('whatsapp_number', 20)->nullable();
            $table->text('address')->nullable();

            $table->date('installation_date')->nullable();
            $table->unsignedTinyInteger('due_day')->default(10);

            $table->string('status')->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
