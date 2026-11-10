<?php

namespace App\Support\Auth;

/**
 * A temporary app password that staff read out or text to a member: 10 characters,
 * lowercase letters and digits only, without the easily confused 0 o 1 l i.
 * 31 symbols, about 49.5 bits. Meets AppPassword (8 characters to 72 bytes).
 */
final class TemporaryPassword
{
    public const LENGTH   = 10;
    public const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function generate(): string
    {
        $last = strlen(self::ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $password .= self::ALPHABET[random_int(0, $last)];
        }

        return $password;
    }
}
