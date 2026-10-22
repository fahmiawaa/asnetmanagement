<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internet_packages', function (Blueprint $table) {
            $table->foreignId('mikrotik_device_id')
                ->nullable()
                ->after('id')
                ->constrained('mikrotik_devices')
                ->nullOnDelete();
            $table->string('sync_source')
                ->default('manual')
                ->after('mikrotik_profile');
            $table->boolean('is_present')
                ->default(true)
                ->after('sync_source');
            $table->timestamp('last_synced_at')
                ->nullable()
                ->after('is_present');

            $table->index([
                'mikrotik_device_id',
                'mikrotik_profile',
                'is_present',
            ], 'internet_packages_mikrotik_sync_index');
        });
    }

    public function down(): void
    {
        Schema::table('internet_packages', function (Blueprint $table) {
            $table->dropIndex('internet_packages_mikrotik_sync_index');
            $table->dropForeign(['mikrotik_device_id']);
            $table->dropColumn([
                'mikrotik_device_id',
                'sync_source',
                'is_present',
                'last_synced_at',
            ]);
        });
    }
};
