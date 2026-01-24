<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $companyRole = Role::firstOrCreate(['name' => 'company']);
        $individualRole = Role::firstOrCreate(['name' => 'individual']);

        // User permissions
        $userPermissions = [
            'create jobs',
            'view own jobs',
            'update own jobs',
            'delete own jobs',
            'view own applications',
            'download cv',
            'apply to jobs',
            'view own submitted applications',
        ];

        // Admin permissions
        $adminPermissions = [
            'admin.users.view',
            'admin.users.create',
            'admin.users.update',
            'admin.users.delete',
            'admin.users.lock',
            'admin.users.unlock',
            'admin.users.force_password_reset',
            'admin.users.require_2fa',
            'admin.jobs.view',
            'admin.jobs.moderate',
            'admin.jobs.publish',
            'admin.jobs.unpublish',
            'admin.jobs.feature',
            'admin.applications.view',
            'admin.applications.export',
            'admin.audit.view',
            'admin.settings.view',
            'admin.settings.update',
            'admin.system.view',
            'admin.system.cache_clear',
        ];

        // Create all permissions
        foreach (array_merge($userPermissions, $adminPermissions) as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $companyRole->givePermissionTo([
            'create jobs',
            'view own jobs',
            'update own jobs',
            'delete own jobs',
            'view own applications',
            'download cv',
        ]);

        $individualRole->givePermissionTo([
            'apply to jobs',
            'view own submitted applications',
        ]);

        // Admin gets all permissions
        $adminRole->givePermissionTo(Permission::all());
    }
}
