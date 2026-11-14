<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sermon permissions — how they reach production (deploys run `migrate`; nobody
 * re-runs seeders). RolesAndPermissionsSeeder has the same lines for fresh installs.
 * 'manage sermons' includes publishing.
 *
 * Idempotent. Grants only to roles that already exist, and grants Super Admin
 * explicitly: there is no Gate::before. down() removes exactly these 2 permissions.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view sermons', 'manage sermons'];

    /** role => permissions it gets */
    private const GRANTS = [
        'Super Admin'  => self::PERMISSIONS,
        'Pastor'       => self::PERMISSIONS,
        'Office Staff' => self::PERMISSIONS,
    ];

    public function up(): void
    {
        $this->forgetCache();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::GRANTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($permissions);   // missing roles (e.g. a fresh database) are left to the seeder
        }

        $this->forgetCache();
    }

    public function down(): void
    {
        $this->forgetCache();

        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();

        $this->forgetCache();
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
