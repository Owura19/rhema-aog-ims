<?php

namespace Tests\Feature\Sermons;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Migration 2026_11_14_100200_add_sermon_permissions: up, down and re-run. */
class SermonPermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const SERMON_PERMISSIONS = ['manage sermons', 'view sermons'];

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_14_100200_add_sermon_permissions.php');
    }

    /** The roles production has that the migration grants to, plus one it must leave alone. */
    private function productionLikeRoles(): void
    {
        Permission::findOrCreate('view members', 'web');
        Role::findOrCreate('Super Admin', 'web')->givePermissionTo('view members');
        Role::findOrCreate('Pastor', 'web');
        Role::findOrCreate('Office Staff', 'web');
        Role::findOrCreate('HOD', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> sermon permissions of a role */
    private function sermonPermissionsOf(string $role): array
    {
        return Role::findByName($role, 'web')->permissions()->whereIn('name', self::SERMON_PERMISSIONS)->orderBy('name')->pluck('name')->all();
    }

    public function test_migrating_creates_both_permissions(): void
    {
        // RefreshDatabase has already run every migration on an empty database.
        $this->assertSame(self::SERMON_PERMISSIONS, Permission::whereIn('name', self::SERMON_PERMISSIONS)->orderBy('name')->pluck('name')->all());
    }

    public function test_it_grants_super_admin_pastor_and_office_staff_explicitly_and_no_one_else(): void
    {
        $this->productionLikeRoles();

        $this->migration()->up();

        foreach (['Super Admin', 'Pastor', 'Office Staff'] as $role) {
            $this->assertSame(self::SERMON_PERMISSIONS, $this->sermonPermissionsOf($role), $role);
        }
        $this->assertSame([], $this->sermonPermissionsOf('HOD'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('view members'));   // untouched
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

    public function test_down_removes_exactly_the_sermon_permissions(): void
    {
        $this->productionLikeRoles();
        $this->migration()->up();
        $others = Permission::whereNotIn('name', self::SERMON_PERMISSIONS)->orderBy('name')->pluck('name')->all();
        $roles = Role::orderBy('name')->pluck('name')->all();

        $this->migration()->down();

        $this->assertSame(0, Permission::whereIn('name', self::SERMON_PERMISSIONS)->count());
        $this->assertSame($others, Permission::orderBy('name')->pluck('name')->all());
        $this->assertSame($roles, Role::orderBy('name')->pluck('name')->all());   // no role is created or removed
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('view members'));
    }

    public function test_down_then_up_restores_the_grants(): void
    {
        $this->productionLikeRoles();
        $this->migration()->up();
        $this->migration()->down();

        $this->migration()->up();

        $this->assertSame(self::SERMON_PERMISSIONS, $this->sermonPermissionsOf('Office Staff'));
    }
}
