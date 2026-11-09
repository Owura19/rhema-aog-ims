<?php

namespace Tests\Feature\Api\Auth;

use App\Contracts\SmsSender;
use App\Http\Controllers\Api\V1\Auth\ActivationController;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Auth\Concerns\ActivationHelpers;
use Tests\TestCase;

/** POST /api/v1/auth/activation/request */
class ActivationRequestTest extends TestCase
{
    use RefreshDatabase, ActivationHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();   // whole seconds: DB timestamps don't keep microseconds
    }

    public function test_known_unknown_registered_and_ineligible_numbers_get_identical_responses(): void
    {
        $this->member('0241111111');                                                   // eligible, no login
        $this->member('0242222222');
        User::factory()->create(['email' => null, 'login_phone' => '+233242222222']);  // already registered
        $this->member('0243333333', 'Deceased');                                       // not eligible
        $this->member('0244444444')->delete();                                         // soft-deleted
        // 0245555555: nobody at all

        $responses = collect(['0241111111', '0242222222', '0243333333', '0244444444', '0245555555'])
            ->map(fn (string $phone) => $this->requestCode($phone));

        foreach ($responses as $response) {
            $response->assertStatus(202);
            $this->assertSame($responses->first()->getContent(), $response->getContent());
        }
        $responses->first()
            ->assertJsonPath('data.message', ActivationController::MSG_SENT)
            ->assertJsonPath('data.expires_in', 600);

        // …but only the eligible, unregistered number got a text and a code.
        $this->assertSame(['+233241111111'], array_values(array_unique(array_column($this->sms()->messages, 'to'))));
        $this->assertSame(['+233241111111'], PhoneVerificationCode::pluck('phone')->all());
    }

    public function test_the_code_is_texted_and_stored_only_as_a_hash(): void
    {
        $this->member('0241111111');

        $this->requestCode('0241111111')->assertStatus(202);

        $this->assertMatchesRegularExpression(
            '/^Your Rhema AoG code is \d{6}\. It expires in 10 minutes\. Never share it\.$/',
            $this->sms()->lastMessageTo('+233241111111'),
        );
        $code = $this->lastCodeSentTo('+233241111111');

        $row = PhoneVerificationCode::sole();
        $this->assertSame(PhoneVerificationCode::PURPOSE_ACTIVATION, $row->purpose);
        $this->assertSame(hash_hmac('sha256', $code, config('app.key')), $row->code_hash);
        $this->assertTrue($row->expires_at->equalTo(now()->addMinutes(10)));
        $this->assertStringNotContainsString($code, json_encode(DB::table('phone_verification_codes')->get()));
    }

    public static function spellings(): array
    {
        return [
            'stored local, requested E.164'   => ['0241111111', '+233241111111'],
            'stored with spaces'              => ['024 111 1111', '0241111111'],
            'stored with dashes'              => ['024-111-1111', '233241111111'],
            'stored E.164, requested spaced'  => ['+233241111111', '024 111 1111'],
        ];
    }

    #[DataProvider('spellings')]
    public function test_any_spelling_of_the_number_matches(string $stored, string $requested): void
    {
        $this->member($stored);

        $this->requestCode($requested)->assertStatus(202);

        $this->assertNotNull($this->lastCodeSentTo('+233241111111'));
    }

    public static function statuses(): array
    {
        return [
            'Active'      => ['Active', true],
            'Inactive'    => ['Inactive', true],
            'Deceased'    => ['Deceased', false],
            'Transferred' => ['Transferred', false],
            'Visitor'     => ['Visitor', false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_active_or_inactive_members_get_a_code(string $status, bool $getsCode): void
    {
        $this->member('0241111111', $status);

        $this->requestCode('0241111111')->assertStatus(202);

        $this->assertSame($getsCode, $this->lastCodeSentTo('+233241111111') !== null);
    }

    public function test_three_codes_per_hour_per_number(): void
    {
        $this->member('0241111111');

        for ($i = 1; $i <= 3; $i++) {
            $this->requestCode('0241111111')->assertStatus(202);
        }

        $response = $this->requestCode('0241111111');

        $response->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
        $this->assertStringContainsString('Too many codes', $response->json('message'));
        $this->assertGreaterThan(0, $response->json('retry_after'));
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertCount(3, $this->sms()->messages);
    }

    public function test_the_caps_also_count_unknown_numbers_so_they_reveal_nothing(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->requestCode('0245555555')->assertStatus(202);
        }

        $this->requestCode('0245555555')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_ten_codes_per_day_per_number(): void
    {
        $this->member('0241111111');

        foreach ([3, 3, 3, 1] as $batch) {
            for ($i = 1; $i <= $batch; $i++) {
                $this->requestCode('0241111111')->assertStatus(202);
            }
            $this->travel(61)->minutes();     // the hourly cap resets, the daily one doesn't
        }

        $response = $this->requestCode('0241111111');

        $response->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
        $this->assertStringContainsString('Too many codes', $response->json('message'));
        $this->assertCount(10, $this->sms()->messages);
    }

    public function test_a_new_request_retires_the_previous_code(): void
    {
        $this->member('0241111111');

        $this->requestCode('0241111111')->assertStatus(202);
        $oldCode = $this->lastCodeSentTo('+233241111111');
        $this->requestCode('0241111111')->assertStatus(202);
        $newCode = $this->lastCodeSentTo('+233241111111');

        [$old, $new] = PhoneVerificationCode::orderBy('id')->get()->all();
        $this->assertNotNull($old->consumed_at);
        $this->assertNull($new->consumed_at);

        if ($oldCode !== $newCode) {
            $this->verify('0241111111', $oldCode)->assertStatus(422);
        }
        $this->verify('0241111111', $newCode)->assertOk();
    }

    public function test_an_unreadable_number_is_a_422_on_phone(): void
    {
        foreach (['12345', 'not a number', ''] as $bad) {
            $response = $this->requestCode($bad);
            $response->assertStatus(422)->assertJsonPath('code', 'validation_failed');
            $this->assertArrayHasKey('phone', $response->json('errors'));
        }

        $this->assertSame([], $this->sms()->messages);
    }

    public function test_production_refuses_to_send_codes_through_the_log_driver(): void
    {
        $this->member('0241111111');
        $this->app->detectEnvironment(fn () => 'production');
        config(['sms.driver' => 'log']);
        $this->app->forgetInstance(SmsSender::class);

        foreach (['0241111111', '0245555555'] as $phone) {   // member or not: same answer
            $this->requestCode($phone)->assertStatus(503)->assertJsonPath('code', 'sms_unavailable');
        }

        $this->assertSame(0, PhoneVerificationCode::count());
    }

    public function test_local_development_may_use_the_log_driver(): void
    {
        $this->member('0241111111');
        $this->app->detectEnvironment(fn () => 'local');
        config(['sms.driver' => 'log', 'logging.default' => 'null']);   // don't write codes into the real log file
        $this->app->forgetInstance(SmsSender::class);

        $this->requestCode('0241111111')->assertStatus(202);

        $this->assertSame(1, PhoneVerificationCode::count());
    }
}
