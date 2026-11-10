<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A fresh seed must give exactly the roles and permissions production has
 * (snapshot taken from the local copy of production on 2026-11-10).
 * When production gains a permission (via a migration), add it to the seeder
 * AND to this snapshot.
 */
class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    /** role => its permissions, as production has them today. */
    private const EXPECTED = [
        'Super Admin' => self::ALL_PERMISSIONS,
        'Pastor' => [
            'create events', 'edit events', 'view attendance', 'view cell groups', 'view events',
            'view finance', 'view members', 'view reports', 'view visitors',
        ],
        'HOD' => [
            'create visitors', 'edit visitors', 'manage attendance', 'manage cell groups', 'view attendance',
            'view cell groups', 'view events', 'view members', 'view reports', 'view visitors',
        ],
        'Cell Leader' => [
            'create visitors', 'edit visitors', 'manage attendance', 'view attendance', 'view cell groups',
            'view events', 'view members', 'view visitors',
        ],
        'Data Entry' => [
            'create members', 'create visitors', 'edit members', 'edit visitors', 'manage attendance',
            'view attendance', 'view events', 'view members', 'view visitors',
        ],
        'Usher' => [
            'create visitors', 'edit visitors', 'manage attendance', 'view attendance', 'view members', 'view visitors',
        ],
        'Member' => [],
        'Finance Officer' => ['create finance', 'edit finance', 'view finance'],
        'Office Staff' => [
            'create finance', 'create members', 'edit members', 'view attendance', 'view cell groups',
            'view finance', 'view members',
        ],
        'Pledge Officer' => ['manage pledges', 'view members', 'view pledges'],
    ];

    private const ALL_PERMISSIONS = [
        'create events', 'create finance', 'create members', 'create visitors',
        'delete events', 'delete finance', 'delete members', 'delete visitors',
        'edit events', 'edit finance', 'edit members', 'edit pledges', 'edit visitors', 'edit vouchers',
        'manage attendance', 'manage cell groups', 'manage pledges', 'manage roles', 'manage settings', 'manage users',
        'view attendance', 'view cell groups', 'view events', 'view finance', 'view members', 'view pledges',
        'view reports', 'view visitors',
    ];

    /** @return array<string, list<string>> role => sorted permission names, as the database has them */
    private function actual(): array
    {
        $actual = Role::with('permissions')->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => $role->permissions->pluck('name')->sort()->values()->all()])
            ->all();
        ksort($actual);

        return $actual;
    }

    /** @return array<string, list<string>> */
    private function expected(): array
    {
        $expected = array_map(function (array $permissions) {
            sort($permissions);

            return $permissions;
        }, self::EXPECTED);
        ksort($expected);

        return $expected;
    }

    public function test_a_fresh_seed_gives_exactly_the_production_roles_and_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(self::ALL_PERMISSIONS, Permission::orderBy('name')->pluck('name')->all());
        $this->assertSame(28, Permission::count());
        $this->assertSame(10, Role::count());
        $this->assertSame($this->expected(), $this->actual());
    }

    public function test_super_admin_holds_every_permission_explicitly(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = Role::findByName('Super Admin', 'web');

        $this->assertSame(Permission::count(), $superAdmin->permissions()->count());
        foreach (Permission::pluck('name') as $permission) {
            $this->assertTrue($superAdmin->hasPermissionTo($permission), "Super Admin lacks '{$permission}'");
        }
    }

    public function test_seeding_twice_changes_nothing(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(28, Permission::count());
        $this->assertSame(10, Role::count());
        $this->assertSame($this->expected(), $this->actual());
    }
}
