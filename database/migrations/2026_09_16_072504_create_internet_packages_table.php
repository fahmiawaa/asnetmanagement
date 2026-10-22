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
        Schema::create('internet_packages', function (Blueprint $table) {
            $table->id();

            $table->string('package_code')->unique();
            $table->string('name');

            $table->string('download_speed');
            $table->string('upload_speed');

            $table->decimal('price', 12, 2);

            $table->string('mikrotik_profile')->nullable();
            $table->string('status')->default('active');
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internet_packages');
    }
};
