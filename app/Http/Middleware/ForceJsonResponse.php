<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API requests always "expect JSON", even if the client forgot the Accept header,
 * so auth failures, validation errors etc. answer in JSON instead of redirecting.
 * Prepended to the 'api' middleware group only.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
