<?php

namespace App\Services\Auth;

use App\Models\PhoneVerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SMS one-time codes and the short session that follows a correct code.
 *
 *  issue()        new 6-digit code (10 min), retiring any earlier one for the same phone + purpose
 *  verify()       checks a code; 5 wrong tries retire it; success returns a 64-char session token (15 min)
 *  lockSession()  finds a live session row FOR UPDATE — call inside a DB transaction
 *  consume()      marks it used, so a session works exactly once
 *
 * Codes and session tokens are never stored in plain text: only HMAC-SHA256 with the app key.
 */
class VerificationCodes
{
    public const CODE_TTL_MINUTES    = 10;
    public const SESSION_TTL_MINUTES = 15;
    public const MAX_ATTEMPTS        = 5;

    /** @return string the plain 6-digit code — only ever sent by SMS */
    public function issue(string $phone, string $purpose, ?string $ip = null): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($phone, $purpose, $ip, $code) {
            PhoneVerificationCode::where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            PhoneVerificationCode::create([
                'phone'      => $phone,
                'purpose'    => $purpose,
                'code_hash'  => $this->hash($code),
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'request_ip' => $ip,
            ]);
        });

        return $code;
    }

    /** @return string|null a session token on success; null for ANY failure (callers can't tell why) */
    public function verify(string $phone, string $purpose, string $code): ?string
    {
        return DB::transaction(function () use ($phone, $purpose, $code) {
            $row = PhoneVerificationCode::where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->whereNull('verified_at')
                ->where('expires_at', '>', now())
                ->where('attempts', '<', self::MAX_ATTEMPTS)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return null;
            }

            if (! hash_equals($row->code_hash, $this->hash($code))) {
                $row->attempts++;
                if ($row->attempts >= self::MAX_ATTEMPTS) {
                    $row->consumed_at = now();
                }
                $row->save();

                return null;
            }

            $session = Str::random(64);
            $row->forceFill([
                'verified_at'        => now(),
                'session_token_hash' => $this->hash($session),
                'session_expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
            ])->save();

            return $session;
        });
    }

    /** Live, unused session row, locked FOR UPDATE. Call inside DB::transaction(). */
    public function lockSession(string $sessionToken, string $purpose): ?PhoneVerificationCode
    {
        return PhoneVerificationCode::where('session_token_hash', $this->hash($sessionToken))
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('session_expires_at', '>', now())
            ->lockForUpdate()
            ->first();
    }

    public function consume(PhoneVerificationCode $row): void
    {
        $row->forceFill(['consumed_at' => now()])->save();
    }

    public function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
