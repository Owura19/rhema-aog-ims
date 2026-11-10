<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Devotion permissions and the Devotion Translator role — how they reach production
 * (deploys run `migrate`; nobody re-runs seeders). RolesAndPermissionsSeeder has the
 * same lines for fresh installs.
 *
 * Idempotent. Grants only to roles that already exist (Super Admin, Pastor, Office
 * Staff on production), and grants Super Admin explicitly: there is no Gate::before.
 * down() removes exactly these 4 permissions and the Devotion Translator role.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view devotions', 'manage devotions', 'approve devotions', 'review translations'];

    private const TRANSLATOR = 'Devotion Translator';

    /** role => permissions it gets */
    private const GRANTS = [
        'Super Admin'         => self::PERMISSIONS,
        'Pastor'              => self::PERMISSIONS,
        'Office Staff'        => ['view devotions', 'manage devotions'],
        self::TRANSLATOR      => ['view devotions', 'review translations'],
    ];

    public function up(): void
    {
        $this->forgetCache();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(self::TRANSLATOR, 'web');

        foreach (self::GRANTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($permissions);   // missing roles (e.g. a fresh database) are left to the seeder
        }

        $this->forgetCache();
    }

    public function down(): void
    {
        $this->forgetCache();

        Role::where('name', self::TRANSLATOR)->where('guard_name', 'web')->get()->each->delete();
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();

        $this->forgetCache();
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
