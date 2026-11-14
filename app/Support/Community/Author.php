<?php

namespace App\Support\Community;

use App\Models\Member;

/** How a member appears in the feed: first name, last initial and initials. Never a phone, email or photo. */
final class Author
{
    /** @return array{id: int, display_name: string, initials: string} */
    public static function of(?Member $member): array
    {
        if (! $member) {
            return ['id' => 0, 'display_name' => 'Member', 'initials' => '?'];
        }

        $first = trim((string) $member->first_name);
        $last = trim((string) $member->last_name);
        $lastInitial = $last !== '' ? mb_strtoupper(mb_substr($last, 0, 1)) : '';

        return [
            'id'           => $member->id,
            'display_name' => trim($first . ($lastInitial !== '' ? " {$lastInitial}." : '')),
            'initials'     => mb_strtoupper(mb_substr($first, 0, 1)) . $lastInitial,
        ];
    }
}
