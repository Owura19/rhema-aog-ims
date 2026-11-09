<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 'member': the login must be linked to a member record that still exists
 * (users.member_id set, member not soft-deleted). Use after auth:sanctum + active
 * on member-only endpoints (giving, prayer requests, bookings, …).
 */
class EnsureUserIsMember
{
    public function handle(Request $request, Closure $next): Response
    {
        // belongsTo excludes soft-deleted members, so a deleted member reads as null.
        if (! $request->user()?->member) {
            return ApiError::response(
                'member_link_required',
                'This account is not linked to a member record. Please contact the church office.',
                403,
            );
        }

        return $next($request);
    }
}
