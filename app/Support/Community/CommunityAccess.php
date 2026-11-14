<?php

namespace App\Support\Community;

use App\Models\CommunityBan;
use App\Models\CommunityRuleAcceptance;
use App\Models\Member;
use App\Support\Api\ApiError;
use Illuminate\Http\JsonResponse;

/**
 * Who may post, comment and like (everyone with a member record may read):
 *  1. the current rules version is accepted (community_rules_required);
 *  2. no active ban (community_banned);
 *  3. aged community.minimum_age or over by date of birth; with no date of birth on
 *     record, only after ticking "I am 18 or older" when accepting (community_not_allowed).
 * Reports and blocks are always allowed: they keep members safe.
 */
final class CommunityAccess
{
    public const RULES_REQUIRED = 'community_rules_required';
    public const BANNED         = 'community_banned';
    public const NOT_ALLOWED    = 'community_not_allowed';

    public static function rulesVersion(): string
    {
        return (string) config('community.rules.version');
    }

    public static function acceptance(Member $member): ?CommunityRuleAcceptance
    {
        return CommunityRuleAcceptance::where('member_id', $member->id)
            ->where('rules_version', self::rulesVersion())
            ->first();
    }

    /** True when the member has no date of birth on record, so the app must ask for the tick. */
    public static function needsAgeConfirmation(Member $member): bool
    {
        return $member->date_of_birth === null;
    }

    public static function isOldEnough(Member $member, ?CommunityRuleAcceptance $acceptance): bool
    {
        if ($member->date_of_birth !== null) {
            return $member->date_of_birth->age >= (int) config('community.minimum_age');
        }

        return (bool) $acceptance?->adult_confirmed;
    }

    /** The code that stops the member taking part, or null when they may. */
    public static function blockingCode(Member $member): ?string
    {
        $acceptance = self::acceptance($member);

        return match (true) {
            $acceptance === null                            => self::RULES_REQUIRED,
            CommunityBan::activeFor($member->id) !== null   => self::BANNED,
            ! self::isOldEnough($member, $acceptance)       => self::NOT_ALLOWED,
            default                                         => null,
        };
    }

    /** The API error for a member who may not take part, or null when they may. */
    public static function denial(Member $member): ?JsonResponse
    {
        return match (self::blockingCode($member)) {
            self::RULES_REQUIRED => ApiError::response(
                self::RULES_REQUIRED,
                'Please read and accept the community guidelines first.',
                403,
                extra: ['rules_version' => self::rulesVersion()],
            ),
            self::BANNED => ApiError::response(
                self::BANNED,
                "You can't post, comment or like in the community at the moment. Please contact the church office.",
                403,
                extra: ['until' => CommunityBan::activeFor($member->id)?->banned_until?->toIso8601String()],
            ),
            self::NOT_ALLOWED => self::needsAgeConfirmation($member)
                ? ApiError::response(
                    self::NOT_ALLOWED,
                    'Please confirm you are 18 or older in the community guidelines to post, comment or like.',
                    403,
                    extra: ['age_confirmation_possible' => true],
                )
                : ApiError::response(
                    self::NOT_ALLOWED,
                    'Posting, commenting and liking are for members aged 18 and over. You can still read the community.',
                    403,
                    extra: ['age_confirmation_possible' => false],
                ),
            default => null,
        };
    }
}
