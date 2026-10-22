<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kabel pada topologi dibuat bebas: satu node dan satu port boleh memiliki
     * lebih dari satu kabel, dan kabel boleh dibuat tanpa node sama sekali.
     * Karena itu batasan unique single-column pada node/port tujuan dilepas.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $relaxedColumns = ['from_port_id', 'to_port_id', 'to_node_id'];

        if ($driver !== 'sqlite') {
            $foreignKeys = collect(Schema::getForeignKeys('network_links'))->pluck('name')->all();

            foreach ($foreignKeys as $name) {
                Schema::table('network_links', fn (Blueprint $table) => $table->dropForeign($name));
            }
        }

        foreach (Schema::getIndexes('network_links') as $index) {
            if (! $index['unique'] || $index['primary']) {
                continue;
            }

            if ($index['columns'] !== [$index['columns'][0]] || ! in_array($index['columns'][0], $relaxedColumns, true)) {
                continue;
            }

            try {
                Schema::table('network_links', fn (Blueprint $table) => $table->dropUnique($index['name']));
            } catch (Throwable) {
                // Abaikan bila driver tidak mendukung penalized drop unique.
            }
        }

        if ($driver !== 'sqlite') {
            Schema::table('network_links', function (Blueprint $table) {
                $table->foreign('from_port_id')->references('id')->on('network_ports')->nullOnDelete();
                $table->foreign('to_port_id')->references('id')->on('network_ports')->nullOnDelete();
                $table->foreign('to_node_id')->references('id')->on('network_nodes')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('network_links', function (Blueprint $table) {
            $table->unique('from_port_id');
            $table->unique('to_port_id');
            $table->unique('to_node_id');
        });
    }
};
