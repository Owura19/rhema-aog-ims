<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\ValidatesPhone;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuthTokenResource;
use App\Models\User;
use App\Support\Api\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** POST /auth/login {phone, password, device_name} → app token (members 90 days, staff 30). */
class LoginController extends Controller
{
    use ValidatesPhone;

    public const MSG_INVALID = 'The phone number or password is incorrect.';

    /** A real hash to check against when the number is unknown, so response time doesn't reveal it. */
    private static ?string $dummyHash = null;

    public function __invoke(Request $request): JsonResponse
    {
        $phone = $this->validatedPhone($request);
        $data = $request->validate([
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('login_phone', $phone)->first();

        $passwordOk = Hash::check(
            $data['password'],
            $user?->password ?? (self::$dummyHash ??= Hash::make(Str::random(40))),
        );

        if (! $user || ! $passwordOk) {
            return ApiError::response('invalid_credentials', self::MSG_INVALID, 401);
        }

        if (! $user->is_active) {
            return ApiError::response(
                'account_inactive',
                'This account has been deactivated. Please contact the church office.',
                403,
            );
        }

        return (new AuthTokenResource($user, $user->createAppToken($data['device_name'])))->response();
    }
}
