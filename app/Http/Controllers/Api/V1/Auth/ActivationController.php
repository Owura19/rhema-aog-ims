<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\ValidatesPhone;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuthTokenResource;
use App\Models\Member;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\Auth\MemberPhoneDirectory;
use App\Services\Auth\SmsCodeSender;
use App\Services\Auth\VerificationCodes;
use App\Support\Api\ApiError;
use App\Support\Auth\AppPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Member self-activation of the mobile app:
 *   1. POST /auth/activation/request   {phone}                      → always the same neutral 202
 *   2. POST /auth/activation/verify    {phone, code}                → session token + masked members
 *   3. POST /auth/activation/complete  {activation_token, member_id, password, password_confirmation, device_name}
 */
class ActivationController extends Controller
{
    use ValidatesPhone;

    private const PURPOSE = PhoneVerificationCode::PURPOSE_ACTIVATION;

    public const MSG_SENT               = "If this number is registered with the church, we've sent a code.";
    public const MSG_CODE_INVALID       = 'The code is invalid or has expired.';
    public const MSG_SESSION_EXPIRED    = 'This activation has expired. Please start again.';
    public const MSG_ALREADY_REGISTERED = 'This number is already linked to an account. Log in, or contact the church office.';
    public const MSG_ACCOUNT_EXISTS     = 'This member already has an account. Please contact the church office.';

    public function __construct(
        private VerificationCodes $codes,
        private MemberPhoneDirectory $directory,
        private SmsCodeSender $sms,
    ) {
    }

    public function requestCode(Request $request): JsonResponse
    {
        $phone = $this->validatedPhone($request);

        if ($refusal = $this->sms->guard($phone)) {
            return $refusal;
        }

        // Only a number with an eligible member and no login gets a code — but the reply never says.
        if (! $this->directory->isRegistered($phone) && $this->directory->eligibleMembers($phone)->isNotEmpty()) {
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

    public function verifyCode(Request $request): JsonResponse
    {
        $phone = $this->validatedPhone($request);
        $request->validate(['code' => ['required', 'digits:6']]);

        $session = $this->codes->verify($phone, self::PURPOSE, (string) $request->input('code'));

        if ($session === null || $this->directory->eligibleMembers($phone)->isEmpty()) {
            throw ValidationException::withMessages(['code' => self::MSG_CODE_INVALID]);
        }

        if ($this->directory->isRegistered($phone)) {
            return ApiError::response('already_registered', self::MSG_ALREADY_REGISTERED, 409);
        }

        $members = $this->directory->eligibleMembersWithoutAccount($phone);

        if ($members->isEmpty()) {
            return ApiError::response('account_exists', self::MSG_ACCOUNT_EXISTS, 409);
        }

        return response()->json([
            'data' => [
                'activation_token' => $session,
                'expires_in'       => VerificationCodes::SESSION_TTL_MINUTES * 60,
                'members'          => $members->map(fn (Member $member) => [
                    'id'          => $member->id,
                    'masked_name' => MemberPhoneDirectory::mask($member->full_name),
                ])->values(),
            ],
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activation_token' => ['required', 'string', 'size:64'],
            'member_id'        => ['required', 'integer'],
            'password'         => AppPassword::rules(),   // 8 characters to 72 bytes (bcrypt), confirmed
            'device_name'      => ['required', 'string', 'max:100'],
        ]);

        try {
            return DB::transaction(function () use ($data) {
                $session = $this->codes->lockSession($data['activation_token'], self::PURPOSE);

                if (! $session) {
                    throw ValidationException::withMessages(['activation_token' => self::MSG_SESSION_EXPIRED]);
                }

                $phone = $session->phone;

                if ($this->directory->isRegistered($phone)) {
                    return ApiError::response('already_registered', self::MSG_ALREADY_REGISTERED, 409);
                }

                $member = $this->directory->eligibleMembers($phone)->firstWhere('id', (int) $data['member_id']);

                if (! $member) {
                    throw ValidationException::withMessages(['member_id' => 'Choose one of the members listed for this number.']);
                }

                // Never attach a phone login to an existing account (on a shared number,
                // whoever holds the phone could otherwise take that account over).
                if ($member->user()->exists()) {
                    return ApiError::response('account_exists', self::MSG_ACCOUNT_EXISTS, 409);
                }

                $user = User::create([
                    'name'        => $member->full_name,
                    'email'       => null,
                    'login_phone' => $phone,
                    'password'    => $data['password'],   // hashed by the model cast
                    'member_id'   => $member->id,
                    'is_active'   => true,
                ]);
                $user->assignRole(Role::findOrCreate('Member', 'web'));

                $this->codes->consume($session);

                $token = $user->createAppToken($data['device_name']);

                return (new AuthTokenResource($user, $token))->response()->setStatusCode(201);
            });
        } catch (UniqueConstraintViolationException) {
            // Another request registered this number at the same moment.
            return ApiError::response('already_registered', self::MSG_ALREADY_REGISTERED, 409);
        }
    }
}
