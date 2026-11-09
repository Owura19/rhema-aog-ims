<?php

namespace App\Support;

/**
 * Ghanaian phone numbers in one canonical form: +233XXXXXXXXX (E.164).
 *
 * The 9-digit national number must start with 2–5 (mobile 02x/05x, fixed 03x).
 * Anything that can't be normalised unambiguously returns null — never a guess.
 * Used for the one-login-per-phone rule and the per-phone rate limit.
 */
final class PhoneNumber
{
    /** Accepted spellings; each captures the 9-digit national number. */
    private const PATTERNS = [
        '/^\+233([2-5]\d{8})$/',   // +233241234567
        '/^00233([2-5]\d{8})$/',   // 00233241234567
        '/^233([2-5]\d{8})$/',     // 233241234567
        '/^0([2-5]\d{8})$/',       // 0241234567
        '/^([2-5]\d{8})$/',        // 241234567 (leading 0 lost, e.g. by a spreadsheet)
    ];

    public static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Allow common separators only; anything else (letters, slashes, …) makes it invalid.
        $compact = preg_replace('/[\s\-.()]/', '', trim($value));

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $compact, $m)) {
                return '+233' . $m[1];
            }
        }

        return null;
    }

    /**
     * The local form the office reads and dials: "+233241234567" → "024 123 4567".
     * Only accepts the canonical form; anything else returns null.
     */
    public static function display(?string $e164): ?string
    {
        if ($e164 === null || ! preg_match('/^\+233([2-5]\d)(\d{3})(\d{4})$/', $e164, $m)) {
            return null;
        }

        return "0{$m[1]} {$m[2]} {$m[3]}";
    }
}
