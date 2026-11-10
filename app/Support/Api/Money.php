<?php

namespace App\Support\Api;

/**
 * Money in API responses: a string with exactly 2 decimals ("1250.00"), so the
 * app never meets binary floating-point rounding. Sums are done in whole pesewas.
 */
final class Money
{
    /** "1250.5", 1250.5, "1250.50" → "1250.50" */
    public static function format(int|float|string|null $amount): string
    {
        return self::fromCents(self::toCents($amount));
    }

    public static function toCents(int|float|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
