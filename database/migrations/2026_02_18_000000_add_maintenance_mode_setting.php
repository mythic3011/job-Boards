<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add maintenance mode setting if it doesn't exist
        DB::table('settings')->updateOrInsert(
            ['key' => 'maintenance_mode'],
            [
                'value' => 'false',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Add maintenance message setting if it doesn't exist
        DB::table('settings')->updateOrInsert(
            ['key' => 'maintenance_message'],
            [
                'value' => 'The system is currently under maintenance. Please try again later.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Remove the settings we added
        DB::table('settings')->whereIn('key', ['maintenance_mode', 'maintenance_message'])->delete();
    }
};
