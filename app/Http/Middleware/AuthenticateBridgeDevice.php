<?php

namespace App\Http\Middleware;

use App\Models\BiometricDevice;
use App\Support\Api\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the biometric bridge program on /api/v1/bridge/*: "Authorization: Bearer <key>".
 * The key is hashed and matched to an ACTIVE device (BiometricDevice::findByKey); anything else
 * is a 401 that says nothing about why. The device is placed on the request for the controller,
 * and its last_seen_at is updated. The key is never logged or echoed.
 */
class AuthenticateBridgeDevice
{
    public const ATTRIBUTE = 'biometric_device';

    public function handle(Request $request, Closure $next): Response
    {
        $device = BiometricDevice::findByKey((string) $request->bearerToken());

        if (! $device) {
            return ApiError::response('unauthenticated', 'Unauthenticated.', 401, null, ['WWW-Authenticate' => 'Bearer']);
        }

        $device->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set(self::ATTRIBUTE, $device);

        return $next($request);
    }
}
