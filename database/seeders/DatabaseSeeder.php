<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Sets up a NEW, non-production database: roles and permissions, plus two
 * starter accounts.
 *
 *  - Refuses to run in production (no override): nothing is changed there.
 *  - A starter account that is CREATED gets a random password, printed once.
 *  - An account that already exists keeps its password; only its role is set.
 */
class DatabaseSeeder extends Seeder
{
    /** email => [name, role] */
    public const ACCOUNTS = [
        'admin@rhemaims.com'  => ['name' => 'System Administrator', 'role' => 'Super Admin'],
        'pastor@rhemaims.com' => ['name' => 'Pastor Rhema', 'role' => 'Pastor'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DatabaseSeeder must not run in production. Nothing was changed.');

            return;
        }

        // Run roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        foreach (self::ACCOUNTS as $email => $account) {
            $password = Str::password(16);

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => $account['name'],
                    'password' => $password,   // hashed by the model cast; used only if the account is created
                ]
            );
            $user->syncRoles($account['role']);

            if ($user->wasRecentlyCreated) {
                // escape(): the password may contain "<" or "\", which the console would otherwise treat as formatting
                $this->command?->warn("Created {$email} with password: " . OutputFormatter::escape($password));
                $this->command?->warn('  Change this password after the first login. It will not be shown again.');
            } else {
                $this->command?->info("{$email} already exists; its password was not changed.");
            }
        }
    }
}
