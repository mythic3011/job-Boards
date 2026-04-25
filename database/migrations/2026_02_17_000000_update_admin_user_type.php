<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        // Find admin role ID
        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        if ($adminRole) {
            // Find users who have admin role
            $adminUserIds = DB::table('model_has_roles')
                ->where('role_id', $adminRole->id)
                ->where('model_type', 'App\Models\User')
                ->pluck('model_id');

            // Update user_type for these users
            DB::table('users')
                ->whereIn('id', $adminUserIds)
                ->update(['user_type' => 'admin']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        // Revert user_type to 'company' for admins? 
        // Or leave it. Since we don't know who was company before, maybe best to leave it or update back to company if specifically needed.
        // But for this fix, we assume admins should be 'company' type if rolled back.
        
        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        if ($adminRole) {
            $adminUserIds = DB::table('model_has_roles')
                ->where('role_id', $adminRole->id)
                ->where('model_type', 'App\Models\User')
                ->pluck('model_id');

            DB::table('users')
                ->whereIn('id', $adminUserIds)
                ->where('user_type', 'admin') // only revert if they are admin type
                ->update(['user_type' => 'company']);
        }
    }
};
