<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Step 3a schema: phone-only app logins.
 *  - users.email nullable (still unique when present)
 *  - users.login_phone: normalised +233 number, unique = one login per phone
 *  - phone_verification_codes table for SMS codes (used from Step 3b)
 */
class PhoneLoginSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_have_no_email(): void
    {
        $user = User::factory()->create(['email' => null, 'login_phone' => '+233241234567']);

        $this->assertNull($user->fresh()->email);
        $this->assertSame('+233241234567', $user->fresh()->login_phone);
    }

    public function test_many_users_may_have_no_email_and_no_login_phone(): void
    {
        User::factory()->count(3)->create(['email' => null, 'login_phone' => null]);

        $this->assertSame(3, User::whereNull('email')->whereNull('login_phone')->count());
    }

    public function test_email_is_still_unique_when_present(): void
    {
        User::factory()->create(['email' => 'office@example.test']);

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['email' => 'office@example.test']);
    }

    public function test_login_phone_is_unique_one_login_per_phone(): void
    {
        User::factory()->create(['email' => null, 'login_phone' => '+233241234567']);

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['email' => null, 'login_phone' => '+233241234567']);
    }

    public function test_phone_verification_codes_table_has_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('phone_verification_codes'));
        $this->assertSame(
            ['id', 'phone', 'purpose', 'code_hash', 'attempts', 'expires_at', 'verified_at',
             'session_token_hash', 'session_expires_at', 'consumed_at', 'request_ip', 'created_at', 'updated_at'],
            Schema::getColumnListing('phone_verification_codes'),
        );
    }
}
