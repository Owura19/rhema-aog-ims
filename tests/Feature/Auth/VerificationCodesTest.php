<?php

namespace Tests\Feature\Auth;

use App\Models\PhoneVerificationCode;
use App\Services\Auth\VerificationCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** App\Services\Auth\VerificationCodes — codes, attempts, expiry, single-use sessions, pruning. */
class VerificationCodesTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE   = '+233241234567';
    private const PURPOSE = PhoneVerificationCode::PURPOSE_ACTIVATION;

    private VerificationCodes $codes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->codes = $this->app->make(VerificationCodes::class);
    }

    private function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }

    public function test_issue_returns_a_six_digit_code_stored_only_as_an_hmac(): void
    {
        $code = $this->codes->issue(self::PHONE, self::PURPOSE, '10.0.0.1');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $row = PhoneVerificationCode::sole();
        $this->assertSame($this->hmac($code), $row->code_hash);
        $this->assertSame('10.0.0.1', $row->request_ip);
        $this->assertSame(0, $row->attempts);
        $this->assertStringNotContainsString($code, json_encode(DB::table('phone_verification_codes')->get()));
    }

    public function test_wrong_codes_count_attempts_and_the_fifth_retires_the_code(): void
    {
        $code = $this->codes->issue(self::PHONE, self::PURPOSE);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 5; $i++) {
            $this->assertNull($this->codes->verify(self::PHONE, self::PURPOSE, $wrong));
            $this->assertSame($i, PhoneVerificationCode::sole()->attempts);
        }

        $this->assertNotNull(PhoneVerificationCode::sole()->consumed_at);
        $this->assertNull($this->codes->verify(self::PHONE, self::PURPOSE, $code));
    }

    public function test_a_correct_code_gives_one_session_and_cannot_be_reused(): void
    {
        $code = $this->codes->issue(self::PHONE, self::PURPOSE);

        $session = $this->codes->verify(self::PHONE, self::PURPOSE, $code);

        $this->assertSame(64, strlen($session));
        $this->assertSame($this->hmac($session), PhoneVerificationCode::sole()->session_token_hash);
        $this->assertNull($this->codes->verify(self::PHONE, self::PURPOSE, $code));
    }

    public function test_codes_expire_after_ten_minutes(): void
    {
        $code = $this->codes->issue(self::PHONE, self::PURPOSE);

        $this->travel(10)->minutes();

        $this->assertNull($this->codes->verify(self::PHONE, self::PURPOSE, $code));
    }

    public function test_purposes_and_numbers_are_kept_apart(): void
    {
        $code = $this->codes->issue(self::PHONE, self::PURPOSE);

        $this->assertNull($this->codes->verify(self::PHONE, PhoneVerificationCode::PURPOSE_PASSWORD_RESET, $code));
        $this->assertNull($this->codes->verify('+233559999999', self::PURPOSE, $code));
        $this->assertNotNull($this->codes->verify(self::PHONE, self::PURPOSE, $code));
    }

    public function test_a_new_code_retires_the_previous_one_even_after_it_was_verified(): void
    {
        $session = $this->codes->verify(self::PHONE, self::PURPOSE, $this->codes->issue(self::PHONE, self::PURPOSE));

        $this->codes->issue(self::PHONE, self::PURPOSE);

        $this->assertNull(DB::transaction(fn () => $this->codes->lockSession($session, self::PURPOSE)));
    }

    public function test_a_session_can_be_consumed_only_once(): void
    {
        $session = $this->codes->verify(self::PHONE, self::PURPOSE, $this->codes->issue(self::PHONE, self::PURPOSE));

        DB::transaction(function () use ($session) {
            $row = $this->codes->lockSession($session, self::PURPOSE);
            $this->assertNotNull($row);
            $this->assertSame(self::PHONE, $row->phone);
            $this->codes->consume($row);
        });

        $this->assertNull(DB::transaction(fn () => $this->codes->lockSession($session, self::PURPOSE)));
    }

    public function test_sessions_expire_after_fifteen_minutes_and_are_purpose_bound(): void
    {
        $session = $this->codes->verify(self::PHONE, self::PURPOSE, $this->codes->issue(self::PHONE, self::PURPOSE));

        $this->assertNull(DB::transaction(fn () => $this->codes->lockSession($session, PhoneVerificationCode::PURPOSE_PASSWORD_RESET)));

        $this->travel(15)->minutes();

        $this->assertNull(DB::transaction(fn () => $this->codes->lockSession($session, self::PURPOSE)));
    }

    public function test_codes_older_than_a_day_are_pruned_daily(): void
    {
        $this->codes->issue(self::PHONE, self::PURPOSE);
        $this->travel(25)->hours();
        $this->codes->issue('+233559999999', self::PURPOSE);   // recent: must survive

        $this->artisan('model:prune', ['--model' => [PhoneVerificationCode::class]])->assertSuccessful();

        $this->assertSame(['+233559999999'], PhoneVerificationCode::pluck('phone')->all());

        $this->artisan('schedule:list')->expectsOutputToContain('model:prune')->assertSuccessful();
    }
}
