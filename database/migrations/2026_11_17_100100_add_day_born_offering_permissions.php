<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Day-born offering permissions — how they reach production (deploys run `migrate`;
 * nobody re-runs seeders). RolesAndPermissionsSeeder has the same lines for fresh installs.
 *
 *  - record:  enter a week's 7 totals and save drafts;
 *  - publish: publish / unpublish, edit a published week, post to finance;
 *  - view:    results and reports.
 *
 * Idempotent. Grants only to roles that already exist, and grants Super Admin
 * explicitly: there is no Gate::before. down() removes exactly these 3 permissions.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['record day-born offering', 'publish day-born offering', 'view day-born offering'];

    /** role => permissions it gets */
    private const GRANTS = [
        'Super Admin'     => ['record day-born offering', 'publish day-born offering', 'view day-born offering'],
        'Finance Officer' => ['record day-born offering', 'publish day-born offering', 'view day-born offering'],
        'Office Staff'    => ['record day-born offering', 'view day-born offering'],
        'Usher'           => ['record day-born offering', 'view day-born offering'],
        'Pastor'          => ['view day-born offering'],
    ];

    public function up(): void
    {
        $this->forgetCache();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::GRANTS as $roleName => $permissions) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
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
