<?php

namespace App\Support;

use App\Models\Member;
use App\Models\User;
use App\Services\Auth\MemberPhoneDirectory;

/**
 * What the "App / Portal Login" card on a member's page should say. Worked out
 * here with the same rules the activation endpoint uses (eligible statuses,
 * members.phone only, one login per phone), so the card never promises
 * something the app would refuse.
 */
final class MemberAppAccess
{
    public const PHONE_LOGIN       = 'phone_login';        // has a login that signs in with a phone
    public const EMAIL_LOGIN       = 'email_login';        // has an older, email-only portal login
    public const CAN_ACTIVATE      = 'can_activate';       // no login; may activate the app themselves
    public const NUMBER_TAKEN      = 'number_taken';       // no login; the number already belongs to another login
    public const NO_VALID_PHONE    = 'no_valid_phone';     // no login; phone missing or not a Ghana number
    public const INELIGIBLE_STATUS = 'ineligible_status';  // no login; status is not Active or Inactive

    private function __construct(
        public readonly string $state,
        public readonly ?User $user,
        public readonly ?string $phone,   // display form, e.g. "024 123 4567"
        public readonly int $devices,     // unexpired app tokens
    ) {
    }

    public static function for(Member $member): self
    {
        $user = $member->user;

        if ($user) {
            return new self(
                $user->login_phone ? self::PHONE_LOGIN : self::EMAIL_LOGIN,
                $user,
                PhoneNumber::display($user->login_phone),
                $user->tokens()
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->count(),
            );
        }

        $e164 = PhoneNumber::normalise($member->phone);

        $state = match (true) {
            ! in_array($member->membership_status, MemberPhoneDirectory::ELIGIBLE_STATUSES, true) => self::INELIGIBLE_STATUS,
            $e164 === null                                => self::NO_VALID_PHONE,
            User::where('login_phone', $e164)->exists()   => self::NUMBER_TAKEN,
            default                                       => self::CAN_ACTIVATE,
        };

        return new self($state, null, PhoneNumber::display($e164), 0);
    }

    public function hasLogin(): bool
    {
        return $this->user !== null;
    }
}
