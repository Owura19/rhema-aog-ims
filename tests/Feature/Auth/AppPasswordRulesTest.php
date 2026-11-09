<?php

namespace Tests\Feature\Auth;

use App\Support\Auth\AppPassword;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AppPassword::rules(): 8 characters minimum, 72 BYTES maximum (bcrypt), confirmed.
 * Multi-byte letters are built from code points (mb_chr), never typed.
 */
class AppPasswordRulesTest extends TestCase
{
    private function validator(string $password, ?string $confirmation = null): \Illuminate\Validation\Validator
    {
        return Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation ?? $password],
            ['password' => AppPassword::rules()],
        );
    }

    public function test_the_two_byte_letter_used_below_really_is_two_bytes(): void
    {
        $this->assertSame(2, strlen(mb_chr(0x025B, 'UTF-8')));
        $this->assertSame(1, mb_strlen(mb_chr(0x025B, 'UTF-8')));
    }

    public static function passwords(): array
    {
        $twoByte = mb_chr(0x025B, 'UTF-8');

        return [
            '8 ASCII characters'                       => ['abcdefgh', true],
            '7 ASCII characters'                       => ['abcdefg', false],
            '72 ASCII characters = 72 bytes'           => [str_repeat('a', 72), true],
            '73 ASCII characters = 73 bytes'           => [str_repeat('a', 73), false],
            '36 two-byte letters = 72 bytes'           => [str_repeat($twoByte, 36), true],
            '37 two-byte letters = 74 bytes (37 chars)'=> [str_repeat($twoByte, 37), false],
            '70 ASCII + 1 two-byte = 72 bytes'         => [str_repeat('a', 70) . $twoByte, true],
            '71 ASCII + 1 two-byte = 73 bytes (72 ch)' => [str_repeat('a', 71) . $twoByte, false],
        ];
    }

    #[DataProvider('passwords')]
    public function test_length_is_8_characters_to_72_bytes(string $password, bool $valid): void
    {
        $this->assertSame($valid, $this->validator($password)->passes());
    }

    public function test_the_confirmation_must_match(): void
    {
        $this->assertFalse($this->validator('secret-pass-1', 'secret-pass-2')->passes());
    }

    public function test_the_too_long_message_explains_special_letters(): void
    {
        $message = $this->validator(str_repeat('a', 73))->errors()->first('password');

        $this->assertSame(
            'The password is too long: at most 72 characters, or fewer if it uses accented or special letters.',
            $message,
        );
    }
}
