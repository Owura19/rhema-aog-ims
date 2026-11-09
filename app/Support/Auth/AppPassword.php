<?php

namespace App\Support\Auth;

use App\Rules\MaxBytes;

/**
 * The one password rule for mobile app accounts — used by activation,
 * password reset and change password: 8 characters to 72 bytes, confirmed.
 */
final class AppPassword
{
    public const MIN_CHARACTERS = 8;
    public const MAX_BYTES      = 72;   // bcrypt's limit

    /** @return array<int, mixed> */
    public static function rules(): array
    {
        return ['required', 'string', 'min:' . self::MIN_CHARACTERS, new MaxBytes(self::MAX_BYTES), 'confirmed'];
    }
}
