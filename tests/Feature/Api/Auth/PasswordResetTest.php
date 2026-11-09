<?php

namespace Tests\Feature\Api\Auth;

use App\Contracts\SmsSender;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\Auth\Concerns\ActivationHelpers;
use Tests\TestCase;

/** POST /api/v1/auth/password/forgot and /api/v1/auth/password/reset */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase, ActivationHelpers;

    private const PHONE = '0241111111';
    private const E164  = '+233241111111';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
    }

    private function account(string $e164 = self::E164, bool $active = true): User
    {
        return User::factory()->create([
            'email'       => null,
            'login_phone' => $e164,
            'password'    => 'old-pass-123',
            'is_active'   => $active,
        ]);
    }

    private function forgot(string $phone): TestResponse
    {
        return $this->postJson('/api/v1/auth/password/forgot', ['phone' => $phone]);
    }

    private function reset(string $phone, string $code, string $password = 'new-pass-123', ?string $confirmation = null, string $device = 'New Phone'): TestResponse
    {
        return $this->postJson('/api/v1/auth/password/reset', [
            'phone'                 => $phone,
            'code'                  => $code,
            'password'              => $password,
            'password_confirmation' => $confirmation ?? $password,
            'device_name'           => $device,
        ]);
    }

    private function codeFor(string $phone, string $e164): string
    {
        $this->forgot($phone)->assertStatus(202);
        $code = $this->lastCodeSentTo($e164);
        $this->assertNotNull($code, "no reset code was texted to {$e164}");

        return $code;
    }

    // ── forgot ─────────────────────────────────────────────────────────

    public function test_forgot_answers_identically_and_texts_only_active_accounts(): void
    {
        $this->account('+233241111111');                     // active login → gets a code
        $this->account('+233242222222', active: false);      // deactivated login
        $this->member('0243333333');                         // member, but no login
        // 0245555555: nobody

        $responses = collect(['0241111111', '0242222222', '0243333333', '0245555555'])
            ->map(fn (string $phone) => $this->forgot($phone));

        foreach ($responses as $response) {
            $response->assertStatus(202);
            $this->assertSame($responses->first()->getContent(), $response->getContent());
        }
        $responses->first()->assertJsonPath('data.message', PasswordResetController::MSG_SENT);

        $this->assertSame(['+233241111111'], array_values(array_unique(array_column($this->sms()->messages, 'to'))));
        $this->assertSame([PhoneVerificationCode::PURPOSE_PASSWORD_RESET], PhoneVerificationCode::pluck('purpose')->all());
    }

    public function test_the_sms_caps_are_shared_with_activation(): void
    {
        $this->account();

        for ($i = 1; $i <= 3; $i++) {
            $this->requestCode(self::PHONE)->assertStatus(202);   // activation requests count too
        }

        $response = $this->forgot(self::PHONE);

        $response->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
        $this->assertStringContainsString('Too many codes', $response->json('message'));
    }

    public function test_production_refuses_to_send_reset_codes_through_the_log_driver(): void
    {
        $this->account();
        $this->app->detectEnvironment(fn () => 'production');
        config(['sms.driver' => 'log']);
        $this->app->forgetInstance(SmsSender::class);

        $this->forgot(self::PHONE)->assertStatus(503)->assertJsonPath('code', 'sms_unavailable');
    }

    // ── reset ──────────────────────────────────────────────────────────

    public function test_reset_sets_the_password_and_signs_out_every_device(): void
    {
        $user = $this->account();
        $oldToken = $user->createAppToken('Old Phone')->plainTextToken;

        $response = $this->reset(self::PHONE, $this->codeFor(self::PHONE, self::E164), 'new-pass-123', null, 'New Phone');

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id);
        $newToken = $response->json('data.token');

        $user->refresh();
        $this->assertTrue(Hash::check('new-pass-123', $user->password));
        $this->assertSame(['New Phone'], $user->tokens()->pluck('name')->all());
        $this->assertNotNull(PhoneVerificationCode::sole()->consumed_at);

        $this->app['auth']->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($newToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_wrong_code_an_unknown_number_and_an_activation_code_all_get_the_same_422(): void
    {
        $this->account();
        $code = $this->codeFor(self::PHONE, self::E164);

        $this->member('0246666666');                                   // no login: can get an ACTIVATION code
        $this->requestCode('0246666666')->assertStatus(202);
        $activationCode = $this->lastCodeSentTo('+233246666666');

        $wrong      = $this->reset(self::PHONE, $this->wrongCode($code));
        $unknown    = $this->reset('0245555555', '123456');
        $activation = $this->reset('0246666666', $activationCode);

        $wrong->assertStatus(422)->assertJsonPath('errors.code', [PasswordResetController::MSG_CODE_INVALID]);
        $this->assertSame($wrong->getContent(), $unknown->getContent());
        $this->assertSame($wrong->getContent(), $activation->getContent());
    }

    public function test_five_wrong_codes_retire_the_code(): void
    {
        $user = $this->account();
        $code = $this->codeFor(self::PHONE, self::E164);

        for ($i = 1; $i <= 5; $i++) {
            $this->reset(self::PHONE, $this->wrongCode($code))->assertStatus(422);
            $this->travel(61)->seconds();   // stay under the 5/min per-phone limit
        }

        $this->reset(self::PHONE, $code)->assertStatus(422);
        $this->assertTrue(Hash::check('old-pass-123', $user->fresh()->password));
    }

    public function test_reset_codes_expire_after_ten_minutes(): void
    {
        $this->account();
        $code = $this->codeFor(self::PHONE, self::E164);

        $this->travel(11)->minutes();

        $this->reset(self::PHONE, $code)->assertStatus(422);
    }

    public function test_a_rejected_new_password_does_not_use_up_an_attempt(): void
    {
        $this->account();
        $code = $this->codeFor(self::PHONE, self::E164);

        $this->reset(self::PHONE, $code, str_repeat('a', 73))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('password');
        $this->assertSame(0, PhoneVerificationCode::sole()->attempts);

        $this->reset(self::PHONE, $code)->assertOk();
    }

    public function test_the_byte_rule_applies(): void
    {
        $this->account();
        $tooManyBytes = str_repeat(mb_chr(0x025B, 'UTF-8'), 37);   // 37 characters, 74 bytes

        $this->reset(self::PHONE, $this->codeFor(self::PHONE, self::E164), $tooManyBytes)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('password');
    }

    public function test_an_account_deactivated_after_the_code_was_sent_is_refused(): void
    {
        $user = $this->account();
        $code = $this->codeFor(self::PHONE, self::E164);
        $user->update(['is_active' => false]);

        $this->reset(self::PHONE, $code)->assertStatus(403)->assertJsonPath('code', 'account_inactive');
        $this->assertTrue(Hash::check('old-pass-123', $user->fresh()->password));
    }
}
