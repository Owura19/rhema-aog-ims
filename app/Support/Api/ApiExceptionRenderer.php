<?php

namespace App\Support\Api;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception under /api/* in the ApiError shape. Returns null for
 * anything outside /api/*, so the web app keeps Laravel's normal HTML/redirects.
 *
 * Laravel has already converted exceptions by the time this runs
 * (ModelNotFound → 404, AuthorizationException → 403), so mapping by status
 * code is enough — and model class names never reach the client.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') || $e instanceof HttpResponseException) {
            return null;
        }

        if ($e instanceof AuthenticationException) {
            return ApiError::response('unauthenticated', 'Unauthenticated.', 401);
        }

        if ($e instanceof ValidationException) {
            return ApiError::response('validation_failed', $e->getMessage(), 422, $e->errors());
        }

        if ($e instanceof HttpExceptionInterface) {
            $status  = $e->getStatusCode();
            $headers = $e->getHeaders();

            return match ($status) {
                401     => ApiError::response('unauthenticated', 'Unauthenticated.', 401, null, $headers),
                403     => ApiError::response('forbidden', 'You do not have permission to do this.', 403, null, $headers),
                404     => ApiError::response('not_found', 'Not found.', 404, null, $headers),
                405     => ApiError::response('method_not_allowed', 'This method is not allowed here.', 405, null, $headers),
                429     => ApiError::response('too_many_requests', 'Too many requests. Please wait and try again.', 429, null, $headers, [
                    'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                ]),
                // `php artisan down` during deploys: let the app show a friendly "updating" screen.
                503     => ApiError::response(
                    'maintenance',
                    'The service is being updated. Please try again in a minute.',
                    503,
                    null,
                    $headers,
                    isset($headers['Retry-After']) ? ['retry_after' => (int) $headers['Retry-After']] : [],
                ),
                default => ApiError::response(
                    'http_error',
                    $e->getMessage() !== '' ? $e->getMessage() : (Response::$statusTexts[$status] ?? 'Error'),
                    $status,
                    null,
                    $headers,
                ),
            };
        }

        // Anything else is a server error: generic text for the client; details are logged.
        $extra = config('app.debug')
            ? ['debug' => ['exception' => get_class($e), 'message' => $e->getMessage()]]
            : [];

        return ApiError::response('server_error', 'Something went wrong. Please try again.', 500, null, [], $extra);
    }
}
