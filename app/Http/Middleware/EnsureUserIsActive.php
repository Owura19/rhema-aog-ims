<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 'active': a deactivated account (users.is_active = 0) is refused on EVERY request,
 * even if it still holds a valid token. Use after auth:sanctum.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            return ApiError::response(
                'account_inactive',
                'This account has been deactivated. Please contact the church office.',
                403,
            );
        }

        return $next($request);
    }
}
