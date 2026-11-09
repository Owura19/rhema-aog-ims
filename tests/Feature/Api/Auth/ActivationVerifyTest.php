<?php

namespace Tests\Feature\Api\Auth;

use App\Http\Controllers\Api\V1\Auth\ActivationController;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\Auth\Concerns\ActivationHelpers;
use Tests\TestCase;

/** POST /api/v1/auth/activation/verify */
class ActivationVerifyTest extends TestCase
{
    use RefreshDatabase, ActivationHelpers;

    private const PHONE = '0241111111';
    private const E164  = '+233241111111';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();   // whole seconds: DB timestamps don't keep microseconds
    }

    public function test_a_correct_code_returns_a_session_token_and_masked_members(): void
    {
        $member = $this->member(self::PHONE, 'Active', 'Ama', 'Mensah');

        $response = $this->verifiedSession(self::PHONE, self::E164);

        $response->assertOk()
            ->assertJsonPath('data.expires_in', 900)
            ->assertJsonPath('data.members', [['id' => $member->id, 'masked_name' => 'A*** M***']]);

        $token = $response->json('data.activation_token');
        $this->assertSame(64, strlen($token));

        $row = PhoneVerificationCode::sole();
        $this->assertSame(hash_hmac('sha256', $token, config('app.key')), $row->session_token_hash);
        $this->assertNotNull($row->verified_at);
        $this->assertTrue($row->session_expires_at->equalTo(now()->addMinutes(15)));
        $this->assertStringNotContainsString($token, json_encode(DB::table('phone_verification_codes')->get()));
    }

    public function test_a_shared_number_lists_only_eligible_members_without_an_account(): void
    {
        $kofi  = $this->member(self::PHONE, 'Active', 'Kofi', 'Boateng');
        $abena = $this->member(self::PHONE, 'Inactive', 'Abena', 'Owusu');
        $this->member(self::PHONE, 'Deceased', 'Yaw', 'Owusu');
        $this->member(self::PHONE, 'Transferred', 'Esi', 'Owusu');
        $hasAccount = $this->member(self::PHONE, 'Active', 'Kwame', 'Asante');
        User::factory()->create(['member_id' => $hasAccount->id]);

        $this->verifiedSession(self::PHONE, self::E164)
            ->assertOk()
            ->assertJsonPath('data.members', [
                ['id' => $kofi->id,  'masked_name' => 'K*** B***'],
                ['id' => $abena->id, 'masked_name' => 'A*** O***'],
            ]);
    }

    public function test_409_account_exists_when_every_member_on_the_number_already_has_an_account(): void
    {
        $member = $this->member(self::PHONE);
        User::factory()->create(['member_id' => $member->id]);   // e.g. an email portal login

        $response = $this->verifiedSession(self::PHONE, self::E164);

        $response->assertStatus(409)->assertJsonPath('code', 'account_exists');
        $this->assertSame(ActivationController::MSG_ACCOUNT_EXISTS, $response->json('message'));
    }

    public function test_409_already_registered_when_the_number_got_a_login_meanwhile(): void
    {
        $this->member(self::PHONE);
        $this->requestCode(self::PHONE)->assertStatus(202);
        $code = $this->lastCodeSentTo(self::E164);
        User::factory()->create(['email' => null, 'login_phone' => self::E164]);

        $this->verify(self::PHONE, $code)->assertStatus(409)->assertJsonPath('code', 'already_registered');
    }

    public function test_wrong_codes_get_a_generic_422_and_the_fifth_retires_the_code(): void
    {
        $this->member(self::PHONE);
        $this->requestCode(self::PHONE)->assertStatus(202);
        $code = $this->lastCodeSentTo(self::E164);

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->verify(self::PHONE, $this->wrongCode($code));
            $response->assertStatus(422)->assertJsonPath('code', 'validation_failed');
            $this->assertSame([ActivationController::MSG_CODE_INVALID], $response->json('errors.code'));
            $this->travel(61)->seconds();   // stay under the 5/min per-phone limit
        }

        $this->verify(self::PHONE, $code)->assertStatus(422);   // even the right code is now dead

        $row = PhoneVerificationCode::sole();
        $this->assertSame(5, $row->attempts);
        $this->assertNotNull($row->consumed_at);
    }

    public function test_codes_expire_after_ten_minutes(): void
    {
        $this->member(self::PHONE);
        $this->requestCode(self::PHONE)->assertStatus(202);
        $code = $this->lastCodeSentTo(self::E164);

        $this->travel(11)->minutes();

        $this->verify(self::PHONE, $code)->assertStatus(422);
    }

    public function test_an_unknown_number_gets_exactly_the_same_answer_as_a_wrong_code(): void
    {
        $this->member(self::PHONE);
        $this->requestCode(self::PHONE)->assertStatus(202);
        $code = $this->lastCodeSentTo(self::E164);

        $wrong   = $this->verify(self::PHONE, $this->wrongCode($code));
        $unknown = $this->verify('0245555555', '123456');

        $wrong->assertStatus(422);
        $unknown->assertStatus(422);
        $this->assertSame($wrong->getContent(), $unknown->getContent());
    }

    public function test_a_member_removed_after_the_code_was_sent_gets_the_generic_422(): void
    {
        $member = $this->member(self::PHONE);
        $this->requestCode(self::PHONE)->assertStatus(202);
        $code = $this->lastCodeSentTo(self::E164);
        $member->delete();

        $this->verify(self::PHONE, $code)->assertStatus(422)->assertJsonPath('errors.code', [ActivationController::MSG_CODE_INVALID]);
    }

    public function test_the_code_must_be_six_digits(): void
    {
        $response = $this->verify(self::PHONE, '12ab');

        $response->assertStatus(422);
        $this->assertArrayHasKey('code', $response->json('errors'));
    }
}
