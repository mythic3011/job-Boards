<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ensures all users with admin role have user_type='admin'.
     * This migration is idempotent and can be run multiple times safely.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        // Find admin role
        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        if (!$adminRole) {
            // No admin role exists yet, skip
            return;
        }

        // Find all users who have admin role
        $adminUserIds = DB::table('model_has_roles')
            ->where('role_id', $adminRole->id)
            ->where('model_type', 'App\Models\User')
            ->pluck('model_id')
            ->toArray();

        if (empty($adminUserIds)) {
            // No admin users exist yet, skip
            return;
        }

        // Update user_type for admin users who don't have it set correctly
        $updated = DB::table('users')
            ->whereIn('id', $adminUserIds)
            ->where('user_type', '!=', 'admin')
            ->update(['user_type' => 'admin']);

        if ($updated > 0) {
            \Log::info("Updated {$updated} admin user(s) to have user_type='admin'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * Reverts admin users back to 'company' type.
     * Only reverts users who currently have user_type='admin'.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        if (!$adminRole) {
            return;
        }

        $adminUserIds = DB::table('model_has_roles')
            ->where('role_id', $adminRole->id)
            ->where('model_type', 'App\Models\User')
            ->pluck('model_id')
            ->toArray();

        if (empty($adminUserIds)) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $adminUserIds)
            ->where('user_type', 'admin')
            ->update(['user_type' => 'company']);
    }
};
