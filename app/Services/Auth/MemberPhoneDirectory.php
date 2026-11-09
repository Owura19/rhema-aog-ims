<?php

namespace App\Services\Auth;

use App\Models\Member;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;

/**
 * Which members a phone number belongs to, for app activation.
 *  - "Phone on record" = members.phone only (not alt_phone).
 *  - Only membership_status Active or Inactive may activate; soft-deleted
 *    members are excluded automatically (SoftDeletes).
 *  - Numbers are compared after normalising to +233XXXXXXXXX.
 */
class MemberPhoneDirectory
{
    /** membership_status values allowed to activate the app (easy to widen later). */
    public const ELIGIBLE_STATUSES = ['Active', 'Inactive'];

    /** @return Collection<int, Member> */
    public function eligibleMembers(string $e164): Collection
    {
        $nationalNumber = substr($e164, 4); // the 9 digits after +233

        return Member::query()
            ->whereIn('membership_status', self::ELIGIBLE_STATUSES)
            // Cheap SQL pre-filter on the last 9 digits (separators stripped)…
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '.', ''), '(', ''), ')', '') LIKE ?",
                ['%' . $nationalNumber]
            )
            ->orderBy('id')
            ->get()
            // …then the exact rule in PHP, so only true matches remain.
            ->filter(fn (Member $member) => PhoneNumber::normalise($member->phone) === $e164)
            ->values();
    }

    /** @return Collection<int, Member> eligible members on this number that have no login yet */
    public function eligibleMembersWithoutAccount(string $e164): Collection
    {
        return $this->eligibleMembers($e164)
            ->filter(fn (Member $member) => ! $member->user()->exists())
            ->values();
    }

    public function isRegistered(string $e164): bool
    {
        return User::where('login_phone', $e164)->exists();
    }

    /** "Ama Serwaa Mensah" → "A*** S*** M***" (first letter of each name part). */
    public static function mask(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $part) => mb_substr($part, 0, 1) . '***', $parts));
    }
}
