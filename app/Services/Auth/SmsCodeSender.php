<?php

namespace App\Services\Auth;

use App\Contracts\SmsSender;
use App\Support\Api\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sends verification codes by SMS, behind two guards:
 *  - production with a driver that reaches no phone (log) → 503 sms_unavailable
 *  - per-phone caps shared by activation and password reset: 3 per hour, 10 per 24 hours.
 *    Counted for EVERY well-formed request, member or not, so the caps reveal nothing.
 *
 * Later, with a real provider: send via dispatch(...)->afterResponse() to hide the
 * timing difference between "code sent" and "unknown number".
 */
class SmsCodeSender
{
    public const HOURLY_LIMIT = 3;
    public const DAILY_LIMIT  = 10;

    public function __construct(private SmsSender $sms)
    {
    }

    /** null = go ahead (and this attempt is counted); otherwise the error response to return. */
    public function guard(string $phone): ?JsonResponse
    {
        if (app()->isProduction() && ! $this->sms->deliversToRealPhones()) {
            return ApiError::response(
                'sms_unavailable',
                'Text messages are not available right now. Please try again later or contact the church office.',
                503,
            );
        }

        $caps = [
            ['sms-hour:' . $phone, self::HOURLY_LIMIT, 3600],
            ['sms-day:' . $phone, self::DAILY_LIMIT, 86400],
        ];

        foreach ($caps as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $retryAfter = RateLimiter::availableIn($key);

                return ApiError::response(
                    'too_many_requests',
                    'Too many codes requested for this number. Please try again later.',
                    429,
                    null,
                    ['Retry-After' => $retryAfter],
                    ['retry_after' => $retryAfter],
                );
            }
        }

        foreach ($caps as [$key, , $decaySeconds]) {
            RateLimiter::hit($key, $decaySeconds);
        }

        return null;
    }

    public function sendCode(string $phone, string $code): void
    {
        $minutes = VerificationCodes::CODE_TTL_MINUTES;

        $this->sms->send($phone, "Your Rhema AoG code is {$code}. It expires in {$minutes} minutes. Never share it.");
    }
}
