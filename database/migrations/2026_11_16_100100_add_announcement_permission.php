<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 'manage announcements' — how it reaches production (deploys run `migrate`; nobody
 * re-runs seeders). RolesAndPermissionsSeeder has the same lines for fresh installs.
 *
 * Idempotent. Grants only to roles that already exist, and grants Super Admin
 * explicitly: there is no Gate::before. down() removes exactly this permission.
 */
return new class extends Migration
{
    private const PERMISSION = 'manage announcements';

    private const ROLES = ['Super Admin', 'Pastor', 'Office Staff'];

    public function up(): void
    {
        $this->forgetCache();

        Permission::findOrCreate(self::PERMISSION, 'web');

        foreach (self::ROLES as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISSION);
        }

        $this->forgetCache();
    }

    public function down(): void
    {
        $this->forgetCache();

        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->get()->each->delete();

        $this->forgetCache();
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
