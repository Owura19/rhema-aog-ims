<?php

namespace App\Http\Controllers\Api\V1\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityRuleAcceptance;
use App\Support\Api\ApiError;
use App\Support\Community\CommunityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET  /community/rules
 * POST /community/rules/accept  {version, adult?}
 *
 * "adult" is the "I am 18 or older" tick; it counts only for members with no date of
 * birth on record (needs_age_confirmation). Accepting again with the tick adds it.
 */
class RulesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $member = $request->user()->member;
        $acceptance = CommunityAccess::acceptance($member);

        return response()->json(['data' => [
            'version'                => CommunityAccess::rulesVersion(),
            'title'                  => (string) config('community.rules.title'),
            'body'                   => (string) file_get_contents(config('community.rules.path')),
            'accepted'               => $acceptance !== null,
            'accepted_at'            => $acceptance?->accepted_at?->toIso8601String(),
            'needs_age_confirmation' => CommunityAccess::needsAgeConfirmation($member),
            'can_participate'        => CommunityAccess::blockingCode($member) === null,
            'blocked_reason'         => CommunityAccess::blockingCode($member),
        ]]);
    }

    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:20'],
            'adult'   => ['nullable', 'boolean'],
        ]);

        if ($data['version'] !== CommunityAccess::rulesVersion()) {
            return ApiError::response(
                'rules_outdated',
                'The community guidelines have changed. Please read the latest version.',
                409,
                extra: ['rules_version' => CommunityAccess::rulesVersion()],
            );
        }

        $member = $request->user()->member;
        $adult = CommunityAccess::needsAgeConfirmation($member) && $request->boolean('adult');

        $acceptance = CommunityRuleAcceptance::firstOrNew(['member_id' => $member->id, 'rules_version' => $data['version']]);
        $acceptance->accepted_at ??= now();
        $acceptance->adult_confirmed = (bool) $acceptance->adult_confirmed || $adult;   // a tick is never taken back
        $acceptance->save();

        return response()->json(['data' => [
            'version'         => $acceptance->rules_version,
            'accepted_at'     => $acceptance->accepted_at->toIso8601String(),
            'adult_confirmed' => $acceptance->adult_confirmed,
            'can_participate' => CommunityAccess::blockingCode($member) === null,
            'blocked_reason'  => CommunityAccess::blockingCode($member),
        ]]);
    }
}
