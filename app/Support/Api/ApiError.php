<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

/**
 * The one error shape used by the whole mobile API:
 *
 *   { "message": "Human-readable text", "code": "machine_readable_code", "errors": { … } }
 *
 * "errors" appears only for validation failures; extra keys (retry_after, debug)
 * only where documented.
 */
final class ApiError
{
    public static function response(
        string $code,
        string $message,
        int $status,
        ?array $errors = null,
        array $headers = [],
        array $extra = [],
    ): JsonResponse {
        $body = ['message' => $message, 'code' => $code];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body + $extra, $status, $headers);
    }
}
