<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $firstAdmin = DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if ($firstAdmin) {
            DB::table('users')
                ->where('id', $firstAdmin->id)
                ->update(['role' => 'owner']);
        }
    }

    public function down(): void
    {
        $firstOwner = DB::table('users')
            ->where('role', 'owner')
            ->orderBy('id')
            ->first();

        if ($firstOwner) {
            DB::table('users')
                ->where('id', $firstOwner->id)
                ->update(['role' => 'admin']);
        }
    }
};
