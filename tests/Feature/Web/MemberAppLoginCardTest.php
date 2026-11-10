<?php

namespace Tests\Feature\Web;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** The "Website Login" and "Mobile App" cards on a member's page, rendered through real HTTP requests. */
class MemberAppLoginCardTest extends TestCase
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

    private function loginFor(Member $member, array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'member_id'   => $member->id,
            'name'        => $member->full_name,
            'email'       => null,
            'login_phone' => '+233241234567',
        ])->assignRole('Member');
    }

    private function page(User $as, Member $member): TestResponse
    {
        return $this->actingAs($as)->get(route('members.show', $member))->assertOk();
    }

    // ── members with a login ───────────────────────────────────────────

    public function test_a_phone_login_shows_the_masked_phone_and_the_devices_signed_in(): void
    {
        $member = $this->member();
        $user = $this->loginFor($member);
        $user->createAppToken('Phone');
        $user->createAppToken('Tablet');
        $user->createToken('Old phone', ['*'], now()->subDay());   // expired: not counted

        $this->page($this->editor, $member)
            ->assertSee('Mobile App')
            ->assertSee('App login: *** *** 4567')
            ->assertSee('Signed in on 2 devices')
            ->assertSee('No website login: this member signs in to the mobile app only.');
    }

    public function test_an_email_only_login_says_there_is_no_app_login(): void
    {
        $member = $this->member();
        $this->loginFor($member, ['email' => 'ama@example.com', 'login_phone' => null]);

        $this->page($this->editor, $member)
            ->assertSee('Website login by email')
            ->assertSee('ama@example.com')
            ->assertSee("No app login. This member can't activate the app themselves because they already have a website login.", false);   // template text, not escaped
    }

    public function test_viewers_see_the_status_but_no_buttons(): void
    {
        $withLogin = $this->member();
        $this->loginFor($withLogin);
        $withoutLogin = $this->member(['first_name' => 'Kofi', 'phone' => '0551234567', 'email' => 'kofi@example.com']);

        $this->page($this->viewer, $withLogin)
            ->assertSee('App login: *** *** 4567')
            ->assertDontSee(route('members.app-login.reset', $withLogin))
            ->assertDontSee(route('members.app-login.destroy', $withLogin))
            ->assertDontSee(route('members.portal.reset', $withLogin))
            ->assertDontSee(route('members.portal.revoke', $withLogin));

        $this->page($this->viewer, $withoutLogin)
            ->assertSee('can activate the mobile app themselves')
            ->assertDontSee(route('members.portal.create', $withoutLogin))
            ->assertDontSee('Create Portal Login')
            ->assertDontSee(route('members.app-login.store', $withoutLogin));
    }

    public function test_editors_see_the_app_buttons_for_a_phone_login_and_not_the_old_portal_reset(): void
    {
        $member = $this->member();
        $this->loginFor($member);
        $escapedApostrophe = chr(92) . 'u0027';   // the 6 characters ', as @js() writes them

        $this->page($this->editor, $member)
            ->assertSee(route('members.app-login.reset', $member))
            ->assertSee(route('members.app-login.destroy', $member))
            ->assertSee("confirm('Remove this member{$escapedApostrophe}s app login?", false)
            ->assertDontSee(route('members.portal.reset', $member))
            ->assertDontSee("member''s", false);
    }

    public function test_editors_see_reset_and_revoke_for_an_email_login_with_working_confirmations(): void
    {
        $member = $this->member();
        $this->loginFor($member, ['email' => 'ama@example.com', 'login_phone' => null]);
        $escapedApostrophe = chr(92) . 'u0027';

        $this->page($this->editor, $member)
            ->assertSee(route('members.portal.reset', $member))
            ->assertSee(route('members.portal.revoke', $member))
            ->assertSee("confirm('Revoke this member{$escapedApostrophe}s login?", false)
            ->assertDontSee("member''s", false);
    }

    public function test_the_old_portal_reset_refuses_a_phone_login(): void
    {
        $member = $this->member();
        $user = $this->loginFor($member);
        $user->createAppToken('Phone');
        $hash = $user->password;
        $url = route('members.show', $member);

        $this->actingAs($this->editor)->from($url)
            ->post(route('members.portal.reset', $member))
            ->assertRedirect($url)
            ->assertSessionHas('error', 'This member has an app login. Use "Reset app password" on the Mobile App card.')
            ->assertSessionMissing('portal_created');

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_resetting_an_email_login_shows_the_email(): void
    {
        $member = $this->member();
        $this->loginFor($member, ['email' => 'ama@example.com', 'login_phone' => null]);
        $url = route('members.show', $member);

        $this->actingAs($this->editor)->from($url)->post(route('members.portal.reset', $member))->assertRedirect($url);

        $this->get($url)->assertOk()->assertSee('Email: <strong>ama@example.com</strong>', false);
    }

    public function test_the_create_confirmation_survives_an_apostrophe_in_the_name(): void
    {
        $member = $this->member(['last_name' => "O'Neil", 'email' => 'ama@example.com']);
        $escapedApostrophe = chr(92) . 'u0027';

        $this->page($this->editor, $member)
            ->assertSee("confirm('Create a website login for Ama O{$escapedApostrophe}Neil?')", false);
    }

    // ── members without a login: can they activate the app? ────────────

    public static function eligibleStatuses(): array
    {
        return ['Active' => ['Active'], 'Inactive' => ['Inactive']];
    }

    #[DataProvider('eligibleStatuses')]
    public function test_an_eligible_member_with_a_valid_phone_can_activate(string $status): void
    {
        $member = $this->member(['membership_status' => $status]);

        $this->page($this->editor, $member)
            ->assertSee('This member can activate the mobile app themselves with the number on record (024 123 4567).');
    }

    public function test_a_number_already_used_by_another_login_is_flagged(): void
    {
        $other = $this->member(['first_name' => 'Yaw']);
        $this->loginFor($other);                       // Yaw signs in with +233241234567
        $member = $this->member();                      // Ama has the same number on record

        $this->page($this->editor, $member)
            ->assertSee('024 123 4567 already belongs to another app account')
            ->assertDontSee('can activate the mobile app themselves');
    }

    public static function unusablePhones(): array
    {
        return ['not a Ghana number' => ['12345'], 'no phone' => [null]];
    }

    #[DataProvider('unusablePhones')]
    public function test_a_member_without_a_valid_phone_cannot_activate(?string $phone): void
    {
        $member = $this->member(['phone' => $phone]);

        $this->page($this->editor, $member)
            ->assertSee('No valid phone number on record');
    }

    public static function ineligibleStatuses(): array
    {
        return ['Visitor' => ['Visitor'], 'Transferred' => ['Transferred'], 'Deceased' => ['Deceased']];
    }

    #[DataProvider('ineligibleStatuses')]
    public function test_only_active_or_inactive_members_can_activate(string $status): void
    {
        $member = $this->member(['membership_status' => $status]);

        $this->page($this->editor, $member)
            ->assertSee("Only Active or Inactive members can activate the mobile app (status: {$status}).")
            ->assertDontSee('can activate the mobile app themselves');
    }
}
