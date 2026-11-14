<?php

namespace Tests\Feature\Devotions;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Migration 2026_11_10_100300_add_devotion_permissions: up, down and re-run. */
class DevotionPermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const DEVOTION_PERMISSIONS = ['approve devotions', 'manage devotions', 'review translations', 'view devotions'];

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_10_100300_add_devotion_permissions.php');
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

    /** @return list<string> */
    private function permissionsOf(string $role): array
    {
        return Role::findByName($role, 'web')->permissions()->orderBy('name')->pluck('name')->all();
    }

    public function test_migrating_creates_the_permissions_and_the_translator_role(): void
    {
        // RefreshDatabase has already run every migration on an empty database.
        $this->assertSame(self::DEVOTION_PERMISSIONS, Permission::whereIn('name', self::DEVOTION_PERMISSIONS)->orderBy('name')->pluck('name')->all());
        $this->assertSame(['review translations', 'view devotions'], $this->permissionsOf('Devotion Translator'));
    }

    public function test_it_grants_to_the_existing_roles_and_super_admin_explicitly(): void
    {
        $this->productionLikeRoles();

        $this->migration()->up();

        $this->assertSame([...self::DEVOTION_PERMISSIONS, 'view members'], $this->permissionsOf('Super Admin'));
        $this->assertSame(self::DEVOTION_PERMISSIONS, $this->permissionsOf('Pastor'));
        $this->assertSame(['manage devotions', 'view devotions'], $this->permissionsOf('Office Staff'));
        $this->assertSame([], $this->permissionsOf('HOD'));
        $this->assertSame(['review translations', 'view devotions'], $this->permissionsOf('Devotion Translator'));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->productionLikeRoles();
        $this->migration()->up();
        $permissions = Permission::count();
        $roles = Role::count();
        $superAdmin = $this->permissionsOf('Super Admin');

        $this->migration()->up();

        $this->assertSame($permissions, Permission::count());
        $this->assertSame($roles, Role::count());
        $this->assertSame($superAdmin, $this->permissionsOf('Super Admin'));
    }

    public function test_down_removes_exactly_the_devotion_permissions_and_the_translator_role(): void
    {
        $this->productionLikeRoles();
        $this->migration()->up();
        // Other migrations (e.g. sermon permissions) also create permissions: only the devotion ones may go.
        $others = Permission::whereNotIn('name', self::DEVOTION_PERMISSIONS)->orderBy('name')->pluck('name')->all();

        $this->migration()->down();

        $this->assertSame(0, Permission::whereIn('name', self::DEVOTION_PERMISSIONS)->count());
        $this->assertFalse(Role::where('name', 'Devotion Translator')->exists());
        $this->assertContains('view members', $others);
        $this->assertSame($others, Permission::orderBy('name')->pluck('name')->all());
        $this->assertSame(['view members'], $this->permissionsOf('Super Admin'));
        $this->assertSame(['HOD', 'Office Staff', 'Pastor', 'Super Admin'], Role::orderBy('name')->pluck('name')->all());
    }

    public function test_down_then_up_restores_everything(): void
    {
        $this->productionLikeRoles();
        $this->migration()->up();
        $this->migration()->down();

        $this->migration()->up();

        $this->assertSame([...self::DEVOTION_PERMISSIONS, 'view members'], $this->permissionsOf('Super Admin'));
        $this->assertSame(['review translations', 'view devotions'], $this->permissionsOf('Devotion Translator'));
    }
}
