<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The roles and permissions production has (10 roles, 28 permissions).
 * Kept in line with production: RolesAndPermissionsSeederTest checks a fresh
 * seed gives exactly these sets. New permissions reach production through a
 * migration; add them here too so fresh installs match.
 *
 * Only adds (firstOrCreate / givePermissionTo); it never removes anything from
 * an existing database.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── PERMISSIONS ──────────────────────────────────────────

        $permissions = [
            // Members
            'view members',
            'create members',
            'edit members',
            'delete members',

            // Visitors
            'view visitors',
            'create visitors',
            'edit visitors',
            'delete visitors',

            // Attendance
            'view attendance',
            'manage attendance',

            // Finance
            'view finance',
            'create finance',
            'edit finance',
            'delete finance',
            'edit vouchers',

            // Pledges
            'view pledges',
            'manage pledges',
            'edit pledges',

            // Cell Groups
            'view cell groups',
            'manage cell groups',

            // Events
            'view events',
            'create events',
            'edit events',
            'delete events',

            // Reports
            'view reports',

            // Users & Roles
            'manage users',
            'manage roles',

            // Settings
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ── ROLES ─────────────────────────────────────────────────

        // Super Admin — has all permissions (granted explicitly: there is no Gate::before bypass)
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Pastor / Overseer — sees everything, manages nothing sensitive
        $pastor = Role::firstOrCreate(['name' => 'Pastor']);
        $pastor->givePermissionTo([
            'view members',
            'view visitors',
            'view attendance',
            'view finance',
            'view cell groups',
            'view events',
            'view reports',
            'create events',
            'edit events',
        ]);

        // Head of Department (HOD)
        $hod = Role::firstOrCreate(['name' => 'HOD']);
        $hod->givePermissionTo([
            'view members',
            'view visitors',
            'create visitors',
            'edit visitors',
            'view attendance',
            'manage attendance',
            'view cell groups',
            'manage cell groups',
            'view events',
            'view reports',
        ]);

        // Cell Leader
        $cellLeader = Role::firstOrCreate(['name' => 'Cell Leader']);
        $cellLeader->givePermissionTo([
            'view members',
            'view visitors',
            'create visitors',
            'edit visitors',
            'view attendance',
            'manage attendance',
            'view cell groups',
            'view events',
        ]);

        // Data Entry
        $dataEntry = Role::firstOrCreate(['name' => 'Data Entry']);
        $dataEntry->givePermissionTo([
            'view members',
            'create members',
            'edit members',
            'view visitors',
            'create visitors',
            'edit visitors',
            'view attendance',
            'manage attendance',
            'view events',
        ]);

        // Usher
        $usher = Role::firstOrCreate(['name' => 'Usher']);
        $usher->givePermissionTo([
            'view members',
            'view visitors',
            'create visitors',
            'edit visitors',
            'view attendance',
            'manage attendance',
        ]);

        // Member — logins linked to a member record (portal / mobile app); no staff permissions
        Role::firstOrCreate(['name' => 'Member']);

        // Finance Officer
        $financeOfficer = Role::firstOrCreate(['name' => 'Finance Officer']);
        $financeOfficer->givePermissionTo([
            'view finance',
            'create finance',
            'edit finance',
        ]);

        // Office Staff
        $officeStaff = Role::firstOrCreate(['name' => 'Office Staff']);
        $officeStaff->givePermissionTo([
            'view members',
            'create members',
            'edit members',
            'view attendance',
            'view cell groups',
            'view finance',
            'create finance',
        ]);

        // Pledge Officer
        $pledgeOfficer = Role::firstOrCreate(['name' => 'Pledge Officer']);
        $pledgeOfficer->givePermissionTo([
            'view members',
            'view pledges',
            'manage pledges',
        ]);
    }
}
