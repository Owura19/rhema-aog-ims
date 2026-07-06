<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Run roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create Super Admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@rhemaims.com'],
            [
                'name'              => 'System Administrator',
                'password'          => Hash::make('Admin@1234'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles('Super Admin');

        // Create Pastor user
        $pastor = User::firstOrCreate(
            ['email' => 'pastor@rhemaims.com'],
            [
                'name'              => 'Pastor Rhema',
                'password'          => Hash::make('Pastor@1234'),
                'email_verified_at' => now(),
            ]
        );
        $pastor->syncRoles('Pastor');
    }
}