<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

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

            // Attendance
            'view attendance',
            'manage attendance',

            // Finance
            'view finance',
            'create finance',
            'edit finance',
            'delete finance',

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
            Permission::create(['name' => $permission]);
        }

        // ── ROLES ─────────────────────────────────────────────────

        // Super Admin — has all permissions
        $superAdmin = Role::create(['name' => 'Super Admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Pastor / Overseer — sees everything, manages nothing sensitive
        $pastor = Role::create(['name' => 'Pastor']);
        $pastor->givePermissionTo([
            'view members',
            'view attendance',
            'view finance',
            'view cell groups',
            'view events',
            'view reports',
            'create events',
            'edit events',
        ]);

        // Head of Department (HOD)
        $hod = Role::create(['name' => 'HOD']);
        $hod->givePermissionTo([
            'view members',
            'view attendance',
            'manage attendance',
            'view cell groups',
            'manage cell groups',
            'view events',
            'view reports',
        ]);

        // Cell Leader
        $cellLeader = Role::create(['name' => 'Cell Leader']);
        $cellLeader->givePermissionTo([
            'view members',
            'view attendance',
            'manage attendance',
            'view cell groups',
            'view events',
        ]);

        // Data Entry
        $dataEntry = Role::create(['name' => 'Data Entry']);
        $dataEntry->givePermissionTo([
            'view members',
            'create members',
            'edit members',
            'view attendance',
            'manage attendance',
            'view events',
        ]);

        // Usher
        $usher = Role::create(['name' => 'Usher']);
        $usher->givePermissionTo([
            'view members',
            'view attendance',
            'manage attendance',
        ]);
    }
}