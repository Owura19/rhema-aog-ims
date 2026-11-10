<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 'password.changed': while users.must_change_password is true (a temporary password
 * issued by staff), the API answers 403 password_change_required on every route
 * except the ones needed to see the account, change the password or sign out.
 * Use after auth:sanctum + active on every authenticated API route.
 */
class EnsurePasswordChanged
{
    /** Route names still allowed while a password change is required. */
    public const ALLOWED_ROUTES = [
        'api.v1.me',
        'api.v1.me.password',
        'api.v1.auth.logout',
        'api.v1.auth.logout-all',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return ApiError::response('password_change_required', 'Please change your password to continue.', 403);
        }

        return $next($request);
    }
}
