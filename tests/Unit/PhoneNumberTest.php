<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function valid(): array
    {
        return [
            'local 10-digit'          => ['0241234567',        '+233241234567'],
            'E.164'                   => ['+233241234567',     '+233241234567'],
            '233 without plus'        => ['233241234567',      '+233241234567'],
            '00233 prefix'            => ['00233241234567',    '+233241234567'],
            'spaces'                  => ['024 123 4567',      '+233241234567'],
            'dashes'                  => ['024-123-4567',      '+233241234567'],
            'dots and brackets'       => ['(024) 123.4567',    '+233241234567'],
            'surrounding whitespace'  => ["  0551234567 \n",   '+233551234567'],
            'leading zero lost'       => ['241234567',         '+233241234567'],
            'Telecel 020'             => ['0201234567',        '+233201234567'],
            'AirtelTigo 027'          => ['0271234567',        '+233271234567'],
            'landline 030'            => ['0302123456',        '+233302123456'],
        ];
    }

    #[DataProvider('valid')]
    public function test_normalises_valid_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalise($input));
    }

    public static function invalid(): array
    {
        return [
            'null'                    => [null],
            'empty'                   => [''],
            'too short (9, leading 0)'=> ['024123456'],
            'too long (11)'           => ['05412345678'],
            'two numbers glued'       => ['02412345670551234567'],
            'letters'                 => ['024ABC4567'],
            'foreign number'          => ['+14155550123'],
            'subscriber starts with 0'=> ['0012345678'],
            'subscriber starts with 9'=> ['0912345678'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_rejects_numbers_that_cannot_be_normalised_safely(?string $input): void
    {
        $this->assertNull(PhoneNumber::normalise($input));
    }

    public static function displayable(): array
    {
        return [
            'MTN 024'     => ['+233241234567', '024 123 4567'],
            'Telecel 020' => ['+233201234567', '020 123 4567'],
            'MTN 055'     => ['+233551234567', '055 123 4567'],
            'landline 030'=> ['+233302123456', '030 212 3456'],
        ];
    }

    #[DataProvider('displayable')]
    public function test_displays_canonical_numbers_in_the_local_form(string $e164, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::display($e164));
    }

    public static function notDisplayable(): array
    {
        return [
            'null'                     => [null],
            'empty'                    => [''],
            'local form, not canonical'=> ['0241234567'],
            'too short'                => ['+23324123456'],
            'too long'                 => ['+2332412345678'],
            'subscriber starts with 9' => ['+233941234567'],
            'garbage'                  => ['not a number'],
        ];
    }

    #[DataProvider('notDisplayable')]
    public function test_display_returns_null_for_anything_not_canonical(?string $input): void
    {
        $this->assertNull(PhoneNumber::display($input));
    }
}
