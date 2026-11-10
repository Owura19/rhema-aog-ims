<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuthTokenResource;
use App\Support\Auth\AppPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * PUT /me/password {current_password, password, password_confirmation, device_name}
 * Changes the password, signs out every device (User model hook), and returns a
 * new token for this device. Throttled to 5 attempts/min per user ('password-change').
 */
class MePasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => AppPassword::rules(),
            'device_name'      => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        $user->password = $data['password'];   // hashed by the cast
        $user->must_change_password = false;   // a temporary password has now been replaced
        $user->save();                         // the User hook deletes every token, including this one

        return (new AuthTokenResource($user, $user->createAppToken($data['device_name'])))->response();
    }
}
