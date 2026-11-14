<?php

namespace Tests\Feature\DayBorn;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Migration 2026_11_17_100100_add_day_born_offering_permissions: up, down and re-run. */
class DayBornPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['publish day-born offering', 'record day-born offering', 'view day-born offering'];

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_17_100100_add_day_born_offering_permissions.php');
    }

    private function productionLikeRoles(): void
    {
        Permission::findOrCreate('view members', 'web');
        Role::findOrCreate('Super Admin', 'web')->givePermissionTo('view members');
        foreach (['Pastor', 'Office Staff', 'Finance Officer', 'Usher', 'HOD', 'Pledge Officer'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function dayBornPermissionsOf(string $role): array
    {
        return Role::findByName($role, 'web')->permissions()->whereIn('name', self::PERMISSIONS)->orderBy('name')->pluck('name')->all();
    }

    public function test_migrating_creates_the_three_permissions(): void
    {
        $this->assertSame(self::PERMISSIONS, Permission::whereIn('name', self::PERMISSIONS)->orderBy('name')->pluck('name')->all());
    }

    public function test_it_grants_each_role_its_set_explicitly(): void
    {
        $this->productionLikeRoles();

        $this->migration()->up();

        $this->assertSame(self::PERMISSIONS, $this->dayBornPermissionsOf('Super Admin'));
        $this->assertSame(self::PERMISSIONS, $this->dayBornPermissionsOf('Finance Officer'));
        $this->assertSame(['record day-born offering', 'view day-born offering'], $this->dayBornPermissionsOf('Office Staff'));
        $this->assertSame(['record day-born offering', 'view day-born offering'], $this->dayBornPermissionsOf('Usher'));
        $this->assertSame(['view day-born offering'], $this->dayBornPermissionsOf('Pastor'));
        $this->assertSame([], $this->dayBornPermissionsOf('HOD'));
        $this->assertSame([], $this->dayBornPermissionsOf('Pledge Officer'));
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

    public function test_down_removes_exactly_these_permissions(): void
    {
        $this->productionLikeRoles();
        $this->migration()->up();
        $others = Permission::whereNotIn('name', self::PERMISSIONS)->orderBy('name')->pluck('name')->all();

        $this->migration()->down();

        $this->assertSame(0, Permission::whereIn('name', self::PERMISSIONS)->count());
        $this->assertSame($others, Permission::orderBy('name')->pluck('name')->all());
    }
}
