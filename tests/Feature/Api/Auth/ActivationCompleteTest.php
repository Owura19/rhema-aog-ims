<?php

namespace Tests\Feature\Api\Auth;

use App\Http\Controllers\Api\V1\Auth\ActivationController;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\Auth\MemberPhoneDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Auth\Concerns\ActivationHelpers;
use Tests\TestCase;

/** POST /api/v1/auth/activation/complete */
class ActivationCompleteTest extends TestCase
{
    use RefreshDatabase, ActivationHelpers;

    private const PHONE = '0241111111';
    private const E164  = '+233241111111';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    private function activationToken(): string
    {
        return $this->verifiedSession(self::PHONE, self::E164)->assertOk()->json('data.activation_token');
    }

    public function test_completing_creates_a_member_login_and_returns_a_90_day_app_token(): void
    {
        $member = $this->member(self::PHONE, 'Inactive');   // Inactive members may activate

        $response = $this->complete($this->activationToken(), $member->id, 'secret-pass-1', null, 'Ama Pixel 7');

        $expiresAt = now()->addDays(90)->toIso8601String();
        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', $expiresAt)
            ->assertJsonPath('data.user.login_phone', self::E164)
            ->assertJsonPath('data.user.member.id', $member->id)
            ->assertJsonPath('data.user.roles', ['Member'])
            ->assertJsonPath('data.user.token_expires_at', $expiresAt);

        $plain = $response->json('data.token');
        $this->assertStringStartsWith('rhema_', Str::after($plain, '|'));

        $user = User::where('login_phone', self::E164)->sole();
        $this->assertNull($user->email);
        $this->assertSame($member->id, $user->member_id);
        $this->assertTrue($user->is_active);
        $this->assertSame('Ama Mensah', $user->name);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
        $this->assertSame(['Ama Pixel 7'], $user->tokens()->pluck('name')->all());
        $this->assertNotNull(PhoneVerificationCode::sole()->consumed_at);

        $this->withToken($plain)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.login_phone', self::E164);
    }

    public function test_the_activation_token_works_only_once(): void
    {
        $member = $this->member(self::PHONE);
        $session = $this->activationToken();

        $this->complete($session, $member->id)->assertCreated();

        $this->complete($session, $member->id)
            ->assertStatus(422)
            ->assertJsonPath('errors.activation_token', [ActivationController::MSG_SESSION_EXPIRED]);
    }

    public function test_the_activation_token_expires_after_15_minutes(): void
    {
        $member = $this->member(self::PHONE);
        $session = $this->activationToken();

        $this->travel(16)->minutes();

        $this->complete($session, $member->id)->assertStatus(422)->assertJsonValidationErrorFor('activation_token');
        $this->assertSame(0, User::count());
    }

    public function test_a_member_not_on_this_number_is_rejected_and_the_session_survives(): void
    {
        $member = $this->member(self::PHONE);
        $stranger = $this->member('0559999999', 'Active', 'Kojo', 'Mensah');
        $session = $this->activationToken();

        $this->complete($session, $stranger->id)->assertStatus(422)->assertJsonValidationErrorFor('member_id');
        $this->assertNull(User::where('member_id', $stranger->id)->first());

        $this->complete($session, $member->id)->assertCreated();
    }

    public function test_a_member_with_an_existing_account_gets_409_and_that_account_is_untouched(): void
    {
        $withAccount = $this->member(self::PHONE, 'Active', 'Kofi', 'Boateng');
        $existing = User::factory()->create(['member_id' => $withAccount->id, 'password' => 'original-pass-1']);
        $free = $this->member(self::PHONE, 'Active', 'Abena', 'Owusu');
        $session = $this->activationToken();

        // Someone holding the shared phone tries to take over Kofi's existing account.
        $this->complete($session, $withAccount->id, 'attacker-pass-1')
            ->assertStatus(409)
            ->assertJsonPath('code', 'account_exists');

        $existing->refresh();
        $this->assertTrue(Hash::check('original-pass-1', $existing->password));
        $this->assertNull($existing->login_phone);

        // The session was not used up, so the member without an account can still activate.
        $this->complete($session, $free->id)->assertCreated();
    }

    public function test_409_already_registered_if_the_number_got_a_login_meanwhile(): void
    {
        $this->member(self::PHONE, 'Active', 'Kofi', 'Boateng');
        $second = $this->member(self::PHONE, 'Active', 'Abena', 'Owusu');
        $session = $this->activationToken();
        User::factory()->create(['email' => null, 'login_phone' => self::E164]);   // another device finished first

        $this->complete($session, $second->id)->assertStatus(409)->assertJsonPath('code', 'already_registered');
        $this->assertNull(User::where('member_id', $second->id)->first());
    }

    public function test_a_simultaneous_registration_is_caught_by_the_unique_index(): void
    {
        $member = $this->member(self::PHONE);
        $session = $this->activationToken();
        User::factory()->create(['email' => null, 'login_phone' => self::E164]);
        // Simulate the race: the pre-check still sees the number as free.
        $this->partialMock(MemberPhoneDirectory::class, fn (MockInterface $mock) => $mock->shouldReceive('isRegistered')->andReturn(false));

        $this->complete($session, $member->id)->assertStatus(409)->assertJsonPath('code', 'already_registered');
        $this->assertSame(1, User::where('login_phone', self::E164)->count());
    }

    public static function badPasswords(): array
    {
        $twoByteLetter = mb_chr(0x025B, 'UTF-8');   // a letter taking 2 bytes in UTF-8

        return [
            'too short'                          => ['short1', 'short1'],
            'too long (73 chars)'                => [str_repeat('a', 73), str_repeat('a', 73)],
            'too long in bytes (37 chars, 74 B)' => [str_repeat($twoByteLetter, 37), str_repeat($twoByteLetter, 37)],
            'confirmation mismatch'              => ['secret-pass-1', 'secret-pass-2'],
        ];
    }

    #[DataProvider('badPasswords')]
    public function test_password_rules(string $password, string $confirmation): void
    {
        $member = $this->member(self::PHONE);

        $this->complete($this->activationToken(), $member->id, $password, $confirmation)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('password');
        $this->assertSame(0, User::count());
    }

    public function test_a_device_name_is_required(): void
    {
        $member = $this->member(self::PHONE);

        $this->complete($this->activationToken(), $member->id, 'secret-pass-1', null, '')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('device_name');
    }
}
