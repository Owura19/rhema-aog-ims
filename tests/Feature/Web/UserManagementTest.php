<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** The Users page (staff accounts) and editing accounts that sign in with a phone. */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (['manage users', 'view members', 'edit members'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('Super Admin', 'web')->syncPermissions(['manage users', 'view members', 'edit members']);
        Role::findOrCreate('Office Staff', 'web')->syncPermissions(['view members', 'edit members']);
        Role::findOrCreate('Member', 'web');

        $registrar->forgetCachedPermissions();

        $this->admin = $this->account('Zed Admin', ['Super Admin']);
    }

    /** @param list<string> $roles */
    private function account(string $name, array $roles, array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name] + $attributes);
        $user->syncRoles($roles);

        return $user;
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return $overrides + [
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => '',
            'role'  => $user->getRoleNames()->first(),
        ];
    }

    public function test_the_list_shows_staff_and_role_less_accounts_but_not_member_only_logins(): void
    {
        $this->account('Olivia Office', ['Office Staff']);
        $this->account('Rory Norole', []);
        $this->account('Pat Pastor', ['Office Staff', 'Member'], ['login_phone' => '+233241234567']);
        $this->account('Mona Phonemember', ['Member'], ['email' => null, 'login_phone' => '+233551234567']);
        $this->account('Mel Emailmember', ['Member']);

        $this->actingAs($this->admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Zed Admin')
            ->assertSee('Olivia Office')
            ->assertSee('Rory Norole')
            ->assertSee('Pat Pastor')
            ->assertSee('App: 024 123 4567')
            ->assertDontSee('Mona Phonemember')
            ->assertDontSee('Mel Emailmember')
            ->assertSee("Member app and portal logins are managed from each member's profile.", false);
    }

    public function test_a_phone_account_can_be_saved_without_an_email(): void
    {
        $user = $this->account('Mona Phonemember', ['Member'], ['email' => null, 'login_phone' => '+233551234567']);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, ['name' => 'Mona P. Member', 'email' => '']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('Mona P. Member', $user->name);
        $this->assertNull($user->email);
        $this->assertSame('+233551234567', $user->login_phone);
    }

    public function test_a_staff_account_still_needs_an_email(): void
    {
        $user = $this->account('Olivia Office', ['Office Staff']);
        $email = $user->email;

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, ['email' => '']))
            ->assertSessionHasErrors('email');

        $this->assertSame($email, $user->fresh()->email);
    }

    public function test_the_edit_page_shows_the_app_login_phone_only_for_phone_accounts(): void
    {
        $phone = $this->account('Mona Phonemember', ['Member'], ['email' => null, 'login_phone' => '+233551234567']);
        $staff = $this->account('Olivia Office', ['Office Staff']);

        $this->actingAs($this->admin)->get(route('users.edit', $phone))
            ->assertOk()
            ->assertSee('App Login Phone')
            ->assertSee('055 123 4567');

        $this->actingAs($this->admin)->get(route('users.edit', $staff))
            ->assertOk()
            ->assertDontSee('App Login Phone');
    }

    public function test_the_delete_confirmation_survives_an_apostrophe_in_the_name(): void
    {
        $this->account("Ama O'Neil", ['Office Staff']);
        $escapedApostrophe = chr(92) . 'u0027';   // the 6 characters ', as @js() writes them

        $this->actingAs($this->admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee("confirm('Permanently delete Ama O{$escapedApostrophe}Neil? This cannot be undone. Consider deactivating instead.')", false);
    }
}
