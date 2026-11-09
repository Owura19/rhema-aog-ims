<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** GET /api/v1/health — public connectivity check for the app. */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status'      => 'ok',
                'version'     => 'v1',
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
