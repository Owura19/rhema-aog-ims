<?php

namespace App\Support;

/** Ghana cedi amounts for web pages: "GH₵ 589,134.90". In Blade: @cedis($amount). */
final class Cedis
{
    public const SYMBOL = 'GH₵';

    public static function format(int|float|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $sign = $value < 0 ? '-' : '';

        return $sign . self::SYMBOL . ' ' . number_format(abs($value), 2);
    }
}
