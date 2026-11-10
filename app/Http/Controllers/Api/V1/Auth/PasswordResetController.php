<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\ValidatesPhone;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuthTokenResource;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\Auth\SmsCodeSender;
use App\Services\Auth\VerificationCodes;
use App\Support\Api\ApiError;
use App\Support\Auth\AppPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Forgotten password, by SMS code:
 *   1. POST /auth/password/forgot {phone}  → always the same neutral 202; a code only for an ACTIVE login
 *   2. POST /auth/password/reset  {phone, code, password, password_confirmation, device_name}
 *      → new password, every old token revoked (User model hook), a new token for this device
 */
class PasswordResetController extends Controller
{
    use ValidatesPhone;

    private const PURPOSE = PhoneVerificationCode::PURPOSE_PASSWORD_RESET;

    public const MSG_SENT         = "If this number has an account, we've sent a code.";
    public const MSG_CODE_INVALID = 'The code is invalid or has expired.';

    public function __construct(
        private VerificationCodes $codes,
        private SmsCodeSender $sms,
    ) {
    }

    public function forgot(Request $request): JsonResponse
    {
        $phone = $this->validatedPhone($request);

        // Same per-phone caps as activation (shared counters).
        if ($refusal = $this->sms->guard($phone)) {
            return $refusal;
        }

        if (User::where('login_phone', $phone)->where('is_active', true)->exists()) {
            $code = $this->codes->issue($phone, self::PURPOSE, $request->ip());
            $this->sms->sendCode($phone, $code);
        }

        return response()->json([
            'data' => [
                'message'    => self::MSG_SENT,
                'expires_in' => VerificationCodes::CODE_TTL_MINUTES * 60,
            ],
        ], 202);
    }

    public function reset(Request $request): JsonResponse
    {
        $phone = $this->validatedPhone($request);

        // Validate everything first, so a rejected password never uses up a code attempt.
        $data = $request->validate([
            'code'        => ['required', 'digits:6'],
            'password'    => AppPassword::rules(),
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        // Outside the transaction below: a wrong code's attempt must be counted even though we then fail.
        $session = $this->codes->verify($phone, self::PURPOSE, (string) $data['code']);

        if ($session === null) {
            throw ValidationException::withMessages(['code' => self::MSG_CODE_INVALID]);
        }

        return DB::transaction(function () use ($phone, $session, $data) {
            $row  = $this->codes->lockSession($session, self::PURPOSE);
            $user = User::where('login_phone', $phone)->lockForUpdate()->first();

            if (! $row || ! $user) {
                throw ValidationException::withMessages(['code' => self::MSG_CODE_INVALID]);
            }

            $this->codes->consume($row);

            if (! $user->is_active) {
                return ApiError::response(
                    'account_inactive',
                    'This account has been deactivated. Please contact the church office.',
                    403,
                );
            }

            $user->password = $data['password'];   // hashed by the cast
            $user->must_change_password = false;   // the member chose this password themselves
            $user->save();                         // the User hook deletes every token of this user

            return (new AuthTokenResource($user, $user->createAppToken($data['device_name'])))->response();
        });
    }
}
