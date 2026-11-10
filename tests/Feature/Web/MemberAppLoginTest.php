<?php

namespace Tests\Feature\Web;

use App\Contracts\SmsSender;
use App\Http\Controllers\MemberAppLoginController as Controller;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Staff giving, resetting and removing a member's mobile app login (MemberAppLoginController). */
class MemberAppLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;   // Office Staff: view + edit members
    private User $viewer;   // Pledge Officer: view members only

    protected function setUp(): void
    {
        parent::setUp();

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (['view members', 'edit members', 'manage pledges', 'view pledges'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('Office Staff', 'web')->syncPermissions(['view members', 'edit members']);
        Role::findOrCreate('Pledge Officer', 'web')->syncPermissions(['manage pledges', 'view members', 'view pledges']);
        Role::findOrCreate('Pastor', 'web');
        Role::findOrCreate('Member', 'web');

        $registrar->forgetCachedPermissions();

        $this->editor = User::factory()->create(['name' => 'Olivia Office'])->assignRole('Office Staff');
        $this->viewer = User::factory()->create(['name' => 'Percy Pledge'])->assignRole('Pledge Officer');
    }

    private function member(array $attributes = []): Member
    {
        return Member::create($attributes + [
            'first_name'        => 'Ama',
            'last_name'         => 'Mensah',
            'phone'             => '024 123 4567',
            'membership_status' => 'Active',
        ]);
    }

    /** A login linked to $member: app-only (phone, no email) unless overridden. */
    private function loginFor(Member $member, array $attributes = [], string $role = 'Member'): User
    {
        return User::factory()->create($attributes + [
            'member_id'   => $member->id,
            'name'        => $member->full_name,
            'email'       => null,
            'login_phone' => '+233241234567',
        ])->assignRole($role);
    }

    private function give(Member $member, ?string $phone = '024 123 4567', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->editor)
            ->from(route('members.show', $member))
            ->post(route('members.app-login.store', $member), $phone === null ? [] : ['app_phone' => $phone]);
    }

    private function reset(Member $member)
    {
        return $this->actingAs($this->editor)->from(route('members.show', $member))->post(route('members.app-login.reset', $member));
    }

    private function remove(Member $member)
    {
        return $this->actingAs($this->editor)->from(route('members.show', $member))->delete(route('members.app-login.destroy', $member));
    }

    // ── who may ────────────────────────────────────────────────────────

    public function test_who_can_use_each_action(): void
    {
        $member = $this->member();
        $memberLogin = User::factory()->create()->assignRole('Member');
        $actions = [
            ['POST', route('members.app-login.store', $member)],
            ['POST', route('members.app-login.reset', $member)],
            ['DELETE', route('members.app-login.destroy', $member)],
        ];

        foreach ($actions as [$method, $url]) {
            $this->call($method, $url)->assertRedirect(route('login'));   // guests first: actingAs() lasts for the rest of the test
        }

        foreach ($actions as [$method, $url]) {
            $this->actingAs($this->viewer)->call($method, $url, ['app_phone' => '0241234567'])->assertForbidden();
            $this->actingAs($memberLogin)->call($method, $url, ['app_phone' => '0241234567'])->assertForbidden();
        }

        $this->assertNull($member->fresh()->user);
        $this->give($member)->assertRedirect(route('members.show', $member));   // Office Staff ('edit members') may
        $this->assertNotNull($member->fresh()->user);
    }

    // ── give app login ─────────────────────────────────────────────────

    public function test_giving_an_app_login_creates_a_member_user_with_no_email(): void
    {
        $member = $this->member();

        $this->give($member)->assertRedirect(route('members.show', $member))->assertSessionHasNoErrors();

        $user = $member->fresh()->user;
        $this->assertSame($member->full_name, $user->name);
        $this->assertNull($user->email);
        $this->assertSame('+233241234567', $user->login_phone);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(['Member'], $user->getRoleNames()->all());

        $flash = session('app_login_created');
        $this->assertSame('024 123 4567', $flash['phone']);
        $this->assertTrue(Hash::check($flash['password'], $user->password));
    }

    public function test_giving_an_app_login_reuses_the_members_existing_user(): void
    {
        $member = $this->member();
        $user = $this->loginFor($member, ['email' => 'ama@example.com', 'login_phone' => null]);
        $user->createToken('Old session');
        $oldHash = $user->password;
        $users = User::count();

        $this->give($member)->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame($users, User::count());                     // no second user
        $this->assertSame($user->id, $member->fresh()->user->id);
        $this->assertSame('ama@example.com', $user->email);           // website login kept
        $this->assertSame('+233241234567', $user->login_phone);
        $this->assertTrue($user->must_change_password);
        $this->assertNotSame($oldHash, $user->password);              // its password was replaced
        $this->assertSame(0, $user->tokens()->count());               // and every session ended
        $this->assertSame(['Member'], $user->getRoleNames()->all());
    }

    public static function spellings(): array
    {
        return [
            'local'            => ['0241234567'],
            'local spaced'     => ['024 123 4567'],
            'dashes'           => ['024-123-4567'],
            'international'    => ['+233 24 123 4567'],
            'no plus'          => ['233241234567'],
            'leading 0 lost'   => ['241234567'],
        ];
    }

    #[DataProvider('spellings')]
    public function test_the_phone_is_normalised_like_the_api(string $spelling): void
    {
        $member = $this->member();

        $this->give($member, $spelling)->assertSessionHasNoErrors();

        $this->assertSame('+233241234567', $member->fresh()->user->login_phone);
    }

    public function test_an_invalid_or_missing_phone_is_refused(): void
    {
        $member = $this->member();

        $this->give($member, '12345')->assertSessionHasErrors(['app_phone' => Controller::MSG_INVALID_PHONE]);
        $this->give($member, '+44 20 7946 0000')->assertSessionHasErrors(['app_phone' => Controller::MSG_INVALID_PHONE]);
        $this->give($member, null)->assertSessionHasErrors('app_phone');

        $this->assertNull($member->fresh()->user);
    }

    public function test_a_phone_used_by_another_login_is_refused_without_naming_them(): void
    {
        $yaw = $this->member(['first_name' => 'Yaw', 'last_name' => 'Boateng']);
        $this->loginFor($yaw, ['email' => 'yaw@example.com']);
        $ama = $this->member();

        $this->give($ama)->assertSessionHasErrors(['app_phone' => Controller::MSG_NUMBER_TAKEN]);
        $this->assertNull($ama->fresh()->user);

        // The page the editor lands on shows the message but not who has the number.
        // (Yaw's NAME is on the page anyway, in the relationships dropdown; his email is not.)
        $this->followingRedirects()->actingAs($this->editor)->from(route('members.show', $ama))
            ->post(route('members.app-login.store', $ama), ['app_phone' => '024 123 4567'])
            ->assertOk()
            ->assertSee(Controller::MSG_NUMBER_TAKEN)
            ->assertDontSee('yaw@example.com');
        $this->assertStringNotContainsString('Yaw', Controller::MSG_NUMBER_TAKEN);
    }

    public function test_a_member_who_already_has_an_app_login_is_refused(): void
    {
        $member = $this->member();
        $user = $this->loginFor($member);

        $this->give($member, '0551234567')->assertSessionHas('error', Controller::MSG_ALREADY);

        $this->assertSame('+233241234567', $user->fresh()->login_phone);
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public static function statuses(): array
    {
        return [
            'Active'      => ['Active', true],
            'Inactive'    => ['Inactive', true],
            'Visitor'     => ['Visitor', false],
            'Transferred' => ['Transferred', false],
            'Deceased'    => ['Deceased', false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_active_or_inactive_members_can_get_an_app_login(string $status, bool $allowed): void
    {
        $member = $this->member(['membership_status' => $status]);

        $response = $this->give($member);

        if ($allowed) {
            $response->assertSessionHasNoErrors()->assertSessionHas('app_login_created');
            $this->assertNotNull($member->fresh()->user);
        } else {
            $response->assertSessionHas('error', Controller::MSG_INELIGIBLE);
            $this->assertNull($member->fresh()->user);
        }
    }

    // ── refusals: staff and deactivated logins ─────────────────────────

    public function test_staff_accounts_are_refused(): void
    {
        $withPhone = $this->member();
        $staff = $this->loginFor($withPhone, ['email' => 'pastor@example.com'], 'Pastor');
        $hash = $staff->password;

        $this->reset($withPhone)->assertSessionHas('error', Controller::MSG_STAFF);
        $this->remove($withPhone)->assertSessionHas('error', Controller::MSG_STAFF);

        $withoutPhone = $this->member(['first_name' => 'Kofi', 'phone' => '0551234567']);
        $this->loginFor($withoutPhone, ['email' => 'kofi@example.com', 'login_phone' => null], 'Pastor');
        $this->give($withoutPhone, '0551234567')->assertSessionHas('error', Controller::MSG_STAFF);

        $staff->refresh();
        $this->assertSame($hash, $staff->password);
        $this->assertSame('+233241234567', $staff->login_phone);
        $this->assertNull($withoutPhone->fresh()->user->login_phone);
    }

    public function test_a_deactivated_login_cannot_be_given_or_reset_but_can_be_removed(): void
    {
        $emailOnly = $this->member(['first_name' => 'Kofi', 'phone' => '0551234567']);
        $this->loginFor($emailOnly, ['email' => 'kofi@example.com', 'login_phone' => null, 'is_active' => false]);
        $this->give($emailOnly, '0551234567')->assertSessionHas('error', Controller::MSG_INACTIVE);
        $this->assertNull($emailOnly->fresh()->user->login_phone);

        $appOnly = $this->member();
        $user = $this->loginFor($appOnly, ['is_active' => false]);
        $hash = $user->password;
        $this->reset($appOnly)->assertSessionHas('error', Controller::MSG_INACTIVE);
        $this->assertSame($hash, $user->fresh()->password);

        $this->remove($appOnly)->assertSessionHas('success');
        $this->assertNull(User::find($user->id));
    }

    // ── reset and remove ───────────────────────────────────────────────

    public function test_resetting_issues_a_new_password_sets_the_flag_and_signs_out_every_device(): void
    {
        $member = $this->member();
        $user = $this->loginFor($member);
        $user->createAppToken('Phone');
        $user->createAppToken('Tablet');

        $this->reset($member)->assertRedirect(route('members.show', $member));

        $flash = session('app_login_created');
        $user->refresh();
        $this->assertSame('024 123 4567', $flash['phone']);
        $this->assertTrue(Hash::check($flash['password'], $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_resetting_without_an_app_login_is_refused(): void
    {
        $member = $this->member();
        $this->loginFor($member, ['email' => 'ama@example.com', 'login_phone' => null]);

        $this->reset($member)->assertSessionHas('error', Controller::MSG_NO_APP_LOGIN)->assertSessionMissing('app_login_created');
        $this->remove($member)->assertSessionHas('error', Controller::MSG_NO_APP_LOGIN);
    }

    public function test_removing_an_app_only_login_deletes_the_user_and_its_tokens(): void
    {
        $member = $this->member();
        $user = $this->loginFor($member, ['must_change_password' => true]);
        $user->createAppToken('Phone');

        $this->remove($member)
            ->assertRedirect(route('members.show', $member))
            ->assertSessionHas('success', 'App login removed. The member can activate the app again themselves.');

        $this->assertNull(User::find($user->id));
        $this->assertNull($member->fresh()->user);
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $user->id)->count());
    }

    public function test_after_removing_an_app_only_login_the_member_can_activate_again(): void
    {
        $member = $this->member();
        $this->loginFor($member);
        $sms = $this->app->make(SmsSender::class);   // the fake sender (phpunit.xml)

        $this->postJson('/api/v1/auth/activation/request', ['phone' => '0241234567'])->assertStatus(202);
        $this->assertSame([], $sms->messages);   // registered number: no code

        $this->remove($member)->assertSessionHas('success');

        $this->postJson('/api/v1/auth/activation/request', ['phone' => '0241234567'])->assertStatus(202);
        $this->assertSame(['+233241234567'], array_column($sms->messages, 'to'));   // eligible again: a code was sent
    }

    public function test_removing_an_app_login_with_an_email_keeps_the_user_for_the_website(): void
    {
        $member = $this->member();
        $user = $this->loginFor($member, ['email' => 'ama@example.com', 'must_change_password' => true]);
        $user->createAppToken('Phone');
        $hash = $user->password;

        $this->remove($member)->assertSessionHas('success', 'App login removed. Their website login (email) still works.');

        $user->refresh();
        $this->assertNull($user->login_phone);
        $this->assertFalse($user->must_change_password);
        $this->assertSame('ama@example.com', $user->email);
        $this->assertSame($hash, $user->password);
        $this->assertSame(0, $user->tokens()->count());
    }

    // ── the temporary password ─────────────────────────────────────────

    public function test_the_temporary_password_is_shown_once(): void
    {
        $member = $this->member();
        $this->give($member);
        $password = session('app_login_created')['password'];
        $url = route('members.show', $member);

        $this->get($url)->assertOk()->assertSee($password)->assertSee('This password is shown only once.');
        $this->get($url)->assertOk()->assertDontSee($password);
    }

    public function test_the_temporary_password_is_never_logged(): void
    {
        Event::fake([MessageLogged::class]);
        $member = $this->member();

        $this->give($member);
        $first = session('app_login_created')['password'];
        $this->reset($member);
        $second = session('app_login_created')['password'];

        foreach ([$first, $second] as $password) {
            Event::assertNotDispatched(MessageLogged::class, fn (MessageLogged $e) => str_contains($e->message . json_encode($e->context), $password));
        }
    }

    // ── the card ───────────────────────────────────────────────────────

    public function test_the_card_masks_the_phone_and_shows_the_must_change_badge(): void
    {
        $member = $this->member(['phone' => null]);   // so the full number can't come from the member's details
        $this->loginFor($member, ['must_change_password' => true]);

        $this->actingAs($this->editor)->get(route('members.show', $member))->assertOk()
            ->assertSee('App login: *** *** 4567')
            ->assertSee('must change password')
            ->assertDontSee('+233241234567')
            ->assertDontSee('024 123 4567');
    }

    public function test_viewers_see_the_card_without_buttons(): void
    {
        $member = $this->member();

        $this->actingAs($this->viewer)->get(route('members.show', $member))->assertOk()
            ->assertSee('Mobile App')
            ->assertDontSee('Give app login')
            ->assertDontSee(route('members.app-login.store', $member));
    }

    public function test_give_reset_and_remove_ask_for_confirmation(): void
    {
        $escapedApostrophe = chr(92) . 'u0027';   // ' as @js() writes it

        $withEmail = $this->member(['first_name' => 'Kofi', 'phone' => '0551234567']);
        $this->loginFor($withEmail, ['email' => 'kofi@example.com', 'login_phone' => null]);
        $this->actingAs($this->editor)->get(route('members.show', $withEmail))->assertOk()
            ->assertSee(route('members.app-login.store', $withEmail))
            ->assertSee('value="055 123 4567"', false)   // pre-filled from members.phone
            ->assertSee("confirm('Give Kofi Mensah an app login? A temporary password will be shown once. This also replaces their website password and signs them out everywhere.')", false);

        $withApp = $this->member();
        $this->loginFor($withApp);
        $this->actingAs($this->editor)->get(route('members.show', $withApp))->assertOk()
            ->assertSee("confirm('Give this member a new temporary app password? They will be signed out of the app on every device.')", false)
            ->assertSee("confirm('Remove this member{$escapedApostrophe}s app login? They will be signed out of the app on every device.')", false);
    }
}
