<?php

namespace Tests\Feature\Api\Auth\Concerns;

use App\Contracts\SmsSender;
use App\Models\Member;
use App\Services\Sms\FakeSmsSender;
use Illuminate\Testing\TestResponse;

/** Shared helpers for the activation / login API tests. */
trait ActivationHelpers
{
    protected function sms(): FakeSmsSender
    {
        return $this->app->make(SmsSender::class);
    }

    protected function member(string $phone, string $status = 'Active', string $first = 'Ama', string $last = 'Mensah'): Member
    {
        return Member::create([
            'first_name'        => $first,
            'last_name'         => $last,
            'phone'             => $phone,
            'membership_status' => $status,
        ]);
    }

    protected function requestCode(string $phone): TestResponse
    {
        return $this->postJson('/api/v1/auth/activation/request', ['phone' => $phone]);
    }

    protected function verify(string $phone, string $code): TestResponse
    {
        return $this->postJson('/api/v1/auth/activation/verify', ['phone' => $phone, 'code' => $code]);
    }

    /** The 6-digit code in the last SMS sent to a normalised number, or null. */
    protected function lastCodeSentTo(string $e164): ?string
    {
        $message = $this->sms()->lastMessageTo($e164);

        return $message !== null && preg_match('/\b(\d{6})\b/', $message, $m) ? $m[1] : null;
    }

    /** Request a code and verify it; returns the verify response. */
    protected function verifiedSession(string $phone, string $e164): TestResponse
    {
        $this->requestCode($phone)->assertStatus(202);
        $code = $this->lastCodeSentTo($e164);
        $this->assertNotNull($code, "no code was texted to {$e164}");

        return $this->verify($phone, $code);
    }

    protected function complete(
        string $activationToken,
        int $memberId,
        string $password = 'secret-pass-1',
        ?string $confirmation = null,
        string $deviceName = 'Test Phone',
    ): TestResponse {
        return $this->postJson('/api/v1/auth/activation/complete', [
            'activation_token'      => $activationToken,
            'member_id'             => $memberId,
            'password'              => $password,
            'password_confirmation' => $confirmation ?? $password,
            'device_name'           => $deviceName,
        ]);
    }

    /** A code that is certainly not $code. */
    protected function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }
}
