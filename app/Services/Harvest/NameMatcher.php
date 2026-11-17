<?php

namespace App\Services\Harvest;

use App\Models\Member;

/**
 * Compares a free-text pledger / payer name with a member's name.
 *
 * Names are reduced to a set of words: lowercase, punctuation dropped, leading titles
 * (M/M, Mr, Mrs, Rev, Dr, Elder, Deacon, Pastor, ...) removed. A name matches a member
 * when its words are exactly the member's full name, or first + last name, in any order.
 * "Mensah Kofi" matches Kofi Kwame Mensah; "Kofi" alone does not.
 */
final class NameMatcher
{
    private const TITLES = [
        'mm', 'mr', 'mrs', 'ms', 'miss', 'master', 'madam', 'mad', 'rev', 'revd', 'reverend', 'dr', 'doctor',
        'elder', 'eld', 'deacon', 'deaconess', 'dcn', 'pastor', 'ps', 'pst', 'prof', 'professor',
        'bro', 'brother', 'sis', 'sister', 'apostle', 'evangelist', 'evang', 'hon', 'nana', 'lady',
    ];

    /** @return list<string> sorted unique words, titles removed */
    public static function words(?string $name): array
    {
        $name = mb_strtolower((string) $name);
        $name = preg_replace('#\bm\s*/\s*m\b#u', 'mm', $name);     // "M/M" (Mr and Mrs)
        $name = preg_replace('/[^\p{L}\s]+/u', ' ', $name);        // punctuation and digits
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        while ($words && in_array($words[0], self::TITLES, true)) {   // only leading titles
            array_shift($words);
        }

        $words = array_values(array_unique($words));
        sort($words);

        return $words;
    }

    /** One comparable string, e.g. for grouping: "kofi mensah". */
    public static function key(?string $name): string
    {
        return implode(' ', self::words($name));
    }

    public static function same(?string $a, ?string $b): bool
    {
        $wa = self::words($a);

        return $wa !== [] && $wa === self::words($b);
    }

    public static function matchesMember(Member $member, ?string $name): bool
    {
        $words = self::words($name);
        if ($words === []) {
            return false;
        }

        return $words === self::words($member->full_name)
            || $words === self::words("{$member->first_name} {$member->last_name}");
    }
}
