<?php

namespace App\Support\Devotions;

/**
 * Word counting for devotions, the same way the form's live counter does it in the
 * browser (text.trim().split(/\s+/)): any run of non-space characters is a word,
 * so "3:16" and "God's" each count once.
 */
final class Words
{
    public static function count(?string $text): int
    {
        $text = trim((string) $text);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }

    /** A warning when a staff-written reflection is outside the usual band, else null. */
    public static function staffLengthWarning(?string $body): ?string
    {
        [$min, $max] = config('devotions.limits.staff_warning.reflection_words');
        $words = self::count($body);
        $noun = $words === 1 ? 'word' : 'words';

        return ($words < $min || $words > $max)
            ? "The reflection has {$words} {$noun}; the usual length is {$min}–{$max}. It was saved anyway."
            : null;
    }
}
