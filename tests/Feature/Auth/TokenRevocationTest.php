<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * API tokens are deleted whenever a user's password changes, the account is
 * deactivated, or the user is deleted — via ONE hook on the User model, so every
 * path (web admin pages, profile, portal access, API) is covered.
 */
class TokenRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function withTokens(User $user, int $count = 2): User
    {
        for ($i = 1; $i <= $count; $i++) {
            $user->createToken("device-$i");
        }
        $this->assertSame($count, $this->tokenCount($user));

        return $user;
    }

    private function tokenCount(User $user): int
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->count();
    }

    private function adminWith(string ...$permissions): User
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $admin = User::factory()->create();
        $admin->givePermissionTo($permissions);
        $registrar->forgetCachedPermissions();

        return $admin;
    }

    // ── model hook ─────────────────────────────────────────────────────

    public function test_changing_the_password_deletes_all_tokens(): void
    {
        $user = $this->withTokens(User::factory()->create());
        $other = $this->withTokens(User::factory()->create());

        $user->password = 'a-brand-new-password';
        $user->save();

        $this->assertSame(0, $this->tokenCount($user));
        $this->assertSame(2, $this->tokenCount($other), "other users' tokens are untouched");
    }

    public function test_deactivating_deletes_all_tokens(): void
    {
        $user = $this->withTokens(User::factory()->create());

        $user->is_active = false;
        $user->save();

        $this->assertSame(0, $this->tokenCount($user));
    }

    public function test_reactivating_or_other_edits_keep_tokens(): void
    {
        $user = $this->withTokens(User::factory()->create(['is_active' => false]));

        $user->is_active = true;
        $user->name = 'Renamed';
        $user->phone = '0241234567';
        $user->save();

        $this->assertSame(2, $this->tokenCount($user));
    }

    public function test_deleting_the_user_deletes_its_tokens(): void
    {
        $user = $this->withTokens(User::factory()->create());

        $user->delete();

        $this->assertSame(0, $this->tokenCount($user));
    }

    // ── existing web paths go through the hook ─────────────────────────

    public function test_web_admin_deactivation_deletes_tokens(): void
    {
        $admin = $this->adminWith('manage users');
        $user = $this->withTokens(User::factory()->create());

        $this->actingAs($admin)->patch(route('users.toggle-active', $user))->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame(0, $this->tokenCount($user));
    }

    public function test_web_admin_password_reset_deletes_tokens(): void
    {
        $admin = $this->adminWith('manage users');
        Role::findOrCreate('Office Staff', 'web');
        $user = $this->withTokens(User::factory()->create());

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'Office Staff',
            'password' => 'another-password-1',
            'password_confirmation' => 'another-password-1',
        ])->assertRedirect(route('users.index'));

        $this->assertSame(0, $this->tokenCount($user));
    }

    public function test_web_admin_edit_without_password_keeps_tokens(): void
    {
        $admin = $this->adminWith('manage users');
        Role::findOrCreate('Office Staff', 'web');
        $user = $this->withTokens(User::factory()->create());

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'New Name',
            'email' => $user->email,
            'role' => 'Office Staff',
        ])->assertRedirect(route('users.index'));

        $this->assertSame(2, $this->tokenCount($user));
    }

    public function test_revoking_portal_access_deletes_the_login_and_its_tokens(): void
    {
        $admin = $this->adminWith('edit members');
        $member = Member::create(['first_name' => 'Ama', 'last_name' => 'Mensah']);
        $user = $this->withTokens(User::factory()->create(['member_id' => $member->id]));

        $this->actingAs($admin)->delete(route('members.portal.revoke', $member))->assertRedirect();

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, $this->tokenCount($user));
    }
}
