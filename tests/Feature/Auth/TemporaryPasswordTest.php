<?php

namespace Tests\Feature\Auth;

use App\Support\Auth\AppPassword;
use App\Support\Auth\TemporaryPassword;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Temporary app passwords staff read out to members. No database. */
class TemporaryPasswordTest extends TestCase
{
    public function test_it_is_10_characters_from_the_readable_alphabet(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->assertMatchesRegularExpression('/^[abcdefghjkmnpqrstuvwxyz23456789]{10}$/', TemporaryPassword::generate());
        }
    }

    public function test_it_never_uses_0_o_1_l_or_i(): void
    {
        $this->assertSame('abcdefghjkmnpqrstuvwxyz23456789', TemporaryPassword::ALPHABET);

        $all = '';
        for ($i = 0; $i < 200; $i++) {
            $all .= TemporaryPassword::generate();
        }

        foreach (['0', 'o', '1', 'l', 'i'] as $ambiguous) {
            $this->assertStringNotContainsString($ambiguous, $all);
        }
    }

    public function test_it_passes_the_app_password_rules(): void
    {
        $password = TemporaryPassword::generate();

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $password],
            ['password' => AppPassword::rules()],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_it_differs_each_time(): void
    {
        $passwords = array_map(fn () => TemporaryPassword::generate(), range(1, 50));

        $this->assertCount(50, array_unique($passwords));
    }
}
