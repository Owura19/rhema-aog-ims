<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * php artisan db:seed (DatabaseSeeder): never in production; starter accounts get a
 * random password printed once; existing accounts are never changed.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /** The old hard-coded passwords, built in pieces so this file doesn't contain them verbatim. */
    private const OLD_PASSWORDS = ['Admin' . '@1234', 'Pastor' . '@1234'];

    private const ACCOUNTS = ['admin@rhemaims.com' => 'Super Admin', 'pastor@rhemaims.com' => 'Pastor'];

    /** Runs db:seed for real and returns everything it printed. */
    private function seedAndCapture(array $options = []): string
    {
        $this->withoutMockingConsoleOutput();
        Artisan::call('db:seed', $options);

        return Artisan::output();
    }

    /** @return array<string, string> email => password, from the "Created … with password: …" lines */
    private function printedPasswords(string $output): array
    {
        preg_match_all('/Created (\S+) with password: (\S+)/', $output, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $m) => [$m[1] => $m[2]])->all();
    }

    public function test_it_refuses_to_run_in_production_and_changes_nothing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // --force skips Laravel's own "are you sure?" prompt, so the seeder's guard is what is tested.
        $output = $this->seedAndCapture(['--force' => true]);

        $this->assertStringContainsString('DatabaseSeeder must not run in production. Nothing was changed.', $output);
        $this->assertSame(0, User::count());
        $this->assertSame(0, Role::count());
        $this->assertSame(0, Permission::count());
    }

    public function test_outside_production_both_accounts_get_different_random_passwords_printed_once(): void
    {
        $output = $this->seedAndCapture();
        $printed = $this->printedPasswords($output);

        $this->assertSame(array_keys(self::ACCOUNTS), array_keys($printed));
        $this->assertNotSame($printed['admin@rhemaims.com'], $printed['pastor@rhemaims.com']);
        $this->assertStringContainsString('Change this password after the first login.', $output);

        foreach (self::ACCOUNTS as $email => $role) {
            $user = User::where('email', $email)->sole();
            $password = $printed[$email];

            $this->assertTrue($user->hasRole($role), "{$email} should have the {$role} role");
            $this->assertGreaterThanOrEqual(16, strlen($password));
            $this->assertNotContains($password, self::OLD_PASSWORDS);
            $this->assertTrue(Hash::check($password, $user->password), "the printed password for {$email} doesn't match");
            foreach (self::OLD_PASSWORDS as $old) {
                $this->assertFalse(Hash::check($old, $user->password));
            }
        }
    }

    public function test_running_again_keeps_the_passwords_and_prints_none(): void
    {
        $this->seedAndCapture();
        $hashes = User::whereIn('email', array_keys(self::ACCOUNTS))->pluck('password', 'email')->all();

        $output = $this->seedAndCapture();

        $this->assertSame($hashes, User::whereIn('email', array_keys(self::ACCOUNTS))->pluck('password', 'email')->all());
        $this->assertSame([], $this->printedPasswords($output));
        $this->assertStringNotContainsString('with password:', $output);
        $this->assertStringContainsString('admin@rhemaims.com already exists; its password was not changed.', $output);
        $this->assertSame(2, User::count());
    }

    public function test_an_account_created_by_hand_is_never_given_a_new_password(): void
    {
        $existing = User::factory()->create(['email' => 'admin@rhemaims.com', 'password' => 'chosen-by-the-admin-1']);
        $hash = $existing->password;

        $output = $this->seedAndCapture();

        $this->assertSame($hash, $existing->fresh()->password);
        $this->assertTrue(Hash::check('chosen-by-the-admin-1', $existing->fresh()->password));
        $this->assertArrayNotHasKey('admin@rhemaims.com', $this->printedPasswords($output));
        $this->assertArrayHasKey('pastor@rhemaims.com', $this->printedPasswords($output));
        $this->assertTrue($existing->fresh()->hasRole('Super Admin'));   // the role is still assigned
    }

    public function test_the_old_passwords_are_gone_from_the_repository(): void
    {
        $files = Finder::create()->files()->in(base_path('database'))->name('*.php');
        if (is_dir(base_path('docs'))) {
            $files->append(Finder::create()->files()->in(base_path('docs')));
        }
        $paths = array_map(fn ($file) => $file->getRealPath(), iterator_to_array($files, false));
        if (is_file(base_path('README.md'))) {
            $paths[] = base_path('README.md');
        }

        $this->assertNotEmpty($paths);
        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            foreach (self::OLD_PASSWORDS as $old) {
                $this->assertStringNotContainsString($old, $contents, "{$path} still contains an old seed password");
            }
        }
    }
}
