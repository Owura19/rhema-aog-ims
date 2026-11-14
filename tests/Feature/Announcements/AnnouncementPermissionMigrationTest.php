<?php

namespace Tests\Feature\Announcements;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Migration 2026_11_16_100100_add_announcement_permission: up, down and re-run. */
class AnnouncementPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_16_100100_add_announcement_permission.php');
    }

    private function productionLikeRoles(): void
    {
        Permission::findOrCreate('view members', 'web');
        Role::findOrCreate('Super Admin', 'web')->givePermissionTo('view members');
        Role::findOrCreate('Pastor', 'web');
        Role::findOrCreate('Office Staff', 'web');
        Role::findOrCreate('HOD', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_migrating_creates_the_permission(): void
    {
        $this->assertSame(1, Permission::where('name', 'manage announcements')->count());
    }

    public function test_it_grants_super_admin_pastor_and_office_staff_only(): void
    {
        $this->productionLikeRoles();

        $this->migration()->up();

        foreach (['Super Admin', 'Pastor', 'Office Staff'] as $role) {
            $this->assertTrue(Role::findByName($role, 'web')->hasPermissionTo('manage announcements'), $role);
        }
        $this->assertFalse(Role::findByName('HOD', 'web')->hasPermissionTo('manage announcements'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('view members'));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->productionLikeRoles();
        $this->migration()->up();
        $permissions = Permission::count();
        $grants = Role::withCount('permissions')->get()->sum('permissions_count');

        $this->migration()->up();

        $this->assertSame($permissions, Permission::count());
        $this->assertSame($grants, Role::withCount('permissions')->get()->sum('permissions_count'));
    }

    public function test_down_removes_only_this_permission(): void
    {
        $this->productionLikeRoles();
        $this->migration()->up();
        $others = Permission::where('name', '!=', 'manage announcements')->orderBy('name')->pluck('name')->all();

        $this->migration()->down();

        $this->assertSame(0, Permission::where('name', 'manage announcements')->count());
        $this->assertSame($others, Permission::orderBy('name')->pluck('name')->all());
    }
}
