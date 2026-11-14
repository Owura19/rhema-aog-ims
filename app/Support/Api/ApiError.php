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
 *
 * Every code in use (keep this list in step when adding one):
 *   validation_failed (422, +errors)        unauthenticated (401)
 *   forbidden (403)                         not_found (404)
 *   method_not_allowed (405)                too_many_requests (429, +retry_after)
 *   maintenance (503, +retry_after)         http_error (other HTTP statuses)
 *   server_error (500, +debug when APP_DEBUG)
 *   invalid_credentials (401)               account_inactive (403)
 *   member_link_required (403)              password_change_required (403)
 *   already_registered (409)                account_exists (409)
 *   sms_unavailable (503)                   no_devotion_today (404)
 *   event_full (409, +spots_left)           event_cancelled (409)
 *   event_ended (409)                       rsvp_closed (409)
 *   community_rules_required (403, +rules_version)
 *   community_banned (403, +until)          community_not_allowed (403, +age_confirmation_possible)
 *   rules_outdated (409, +rules_version)
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
