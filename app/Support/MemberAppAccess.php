<?php

namespace App\Support;

use App\Models\Member;
use App\Models\User;
use App\Services\Auth\MemberPhoneDirectory;

/**
 * What the "Website Login" and "Mobile App" cards on a member's page should say.
 * Worked out here with the same rules the activation endpoint uses (eligible
 * statuses, members.phone only, one login per phone), so the cards never promise
 * something the app would refuse.
 */
final class MemberAppAccess
{
    public const PHONE_LOGIN       = 'phone_login';        // has a login that signs in with a phone
    public const EMAIL_LOGIN       = 'email_login';        // has an email-only login (website portal)
    public const CAN_ACTIVATE      = 'can_activate';       // no login; may activate the app themselves
    public const NUMBER_TAKEN      = 'number_taken';       // no login; the number already belongs to another login
    public const NO_VALID_PHONE    = 'no_valid_phone';     // no login; phone missing or not a Ghana number
    public const INELIGIBLE_STATUS = 'ineligible_status';  // no login; status is not Active or Inactive

    private function __construct(
        public readonly string $state,
        public readonly ?User $user,
        public readonly ?string $phone,         // display form, e.g. "024 123 4567"
        public readonly int $devices,           // unexpired app tokens
        public readonly ?string $maskedPhone,   // the app login phone, e.g. "*** *** 4567"
        public readonly ?string $memberPhone,   // members.phone in display form (null if not a Ghana number)
        public readonly bool $eligibleStatus,   // membership_status allows an app login
    ) {
    }

    public static function for(Member $member): self
    {
        $user = $member->user;
        $memberPhone = PhoneNumber::normalise($member->phone);
        $eligible = in_array($member->membership_status, MemberPhoneDirectory::ELIGIBLE_STATUSES, true);

        if ($user) {
            return new self(
                $user->login_phone ? self::PHONE_LOGIN : self::EMAIL_LOGIN,
                $user,
                PhoneNumber::display($user->login_phone),
                $user->tokens()
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->count(),
                self::mask($user->login_phone),
                PhoneNumber::display($memberPhone),
                $eligible,
            );
        }

        $state = match (true) {
            ! $eligible                                        => self::INELIGIBLE_STATUS,
            $memberPhone === null                              => self::NO_VALID_PHONE,
            User::where('login_phone', $memberPhone)->exists() => self::NUMBER_TAKEN,
            default                                            => self::CAN_ACTIVATE,
        };

        return new self($state, null, PhoneNumber::display($memberPhone), 0, null, PhoneNumber::display($memberPhone), $eligible);
    }

    public function hasLogin(): bool
    {
        return $this->user !== null;
    }

    public function hasAppLogin(): bool
    {
        return $this->state === self::PHONE_LOGIN;
    }

    public function mustChangePassword(): bool
    {
        return (bool) $this->user?->must_change_password;
    }

    /** Why staff can't manage this member's app login here, or null when they can. */
    public function refusal(): ?string
    {
        return match (true) {
            $this->user?->isStaff() === true   => 'staff',
            $this->user && ! $this->user->is_active => 'inactive',
            default                            => null,
        };
    }

    /** "+233241234567" → "*** *** 4567": only the last 4 digits shown. */
    public static function mask(?string $e164): ?string
    {
        return $e164 === null ? null : '*** *** ' . substr($e164, -4);
    }
}
