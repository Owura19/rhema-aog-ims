<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A fresh seed must give exactly the roles and permissions production has
 * (snapshot taken from the local copy of production on 2026-11-10, plus the
 * devotion permissions and role from migration 2026_11_10_100300, the sermon
 * permissions from migration 2026_11_14_100200, 'moderate community' from
 * migration 2026_11_15_100100 and 'manage announcements' from migration 2026_11_16_100100).
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
            'approve devotions', 'create events', 'edit events', 'manage announcements', 'manage devotions', 'manage sermons', 'moderate community', 'review translations',
            'view attendance', 'view cell groups', 'view devotions', 'view events',
            'view finance', 'view members', 'view reports', 'view sermons', 'view visitors',
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
            'create finance', 'create members', 'edit members', 'manage announcements', 'manage devotions', 'manage sermons', 'view attendance',
            'view cell groups', 'view devotions', 'view finance', 'view members', 'view sermons',
        ],
        'Pledge Officer' => ['manage pledges', 'view members', 'view pledges'],
        'Devotion Translator' => ['review translations', 'view devotions'],
    ];

    private const ALL_PERMISSIONS = [
        'approve devotions',
        'create events', 'create finance', 'create members', 'create visitors',
        'delete events', 'delete finance', 'delete members', 'delete visitors',
        'edit events', 'edit finance', 'edit members', 'edit pledges', 'edit visitors', 'edit vouchers',
        'manage announcements', 'manage attendance', 'manage cell groups', 'manage devotions', 'manage pledges', 'manage roles', 'manage sermons', 'manage settings', 'manage users',
        'moderate community',
        'review translations',
        'view attendance', 'view cell groups', 'view devotions', 'view events', 'view finance', 'view members', 'view pledges',
        'view reports', 'view sermons', 'view visitors',
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
        $this->assertSame(36, Permission::count());
        $this->assertSame(11, Role::count());
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

        $this->assertSame(36, Permission::count());
        $this->assertSame(11, Role::count());
        $this->assertSame($this->expected(), $this->actual());
    }
}
