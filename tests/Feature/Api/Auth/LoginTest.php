<?php

namespace Tests\Feature\Api\Auth;

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** POST /api/v1/auth/login */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const E164 = '+233241234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    private function account(array $roles = ['Member'], bool $active = true): User
    {
        $user = User::factory()->create([
            'email'       => null,
            'login_phone' => self::E164,
            'password'    => 'secret-pass-1',
            'is_active'   => $active,
        ]);
        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }
        $user->assignRole($roles);

        return $user;
    }

    private function login(string $phone, string $password, string $device = 'Test Phone'): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['phone' => $phone, 'password' => $password, 'device_name' => $device]);
    }

    public static function spellings(): array
    {
        return [
            'local'      => ['0241234567'],
            'E.164'      => ['+233241234567'],
            '233 no plus'=> ['233241234567'],
            'spaced'     => ['024 123 4567'],
        ];
    }

    #[DataProvider('spellings')]
    public function test_members_log_in_with_any_spelling_and_get_a_90_day_token(string $spelling): void
    {
        $user = $this->account();

        $response = $this->login($spelling, 'secret-pass-1');

        $expiresAt = now()->addDays(90)->toIso8601String();
        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', $expiresAt)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.login_phone', self::E164);

        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.token_expires_at', $expiresAt);
    }

    public function test_staff_get_a_30_day_token(): void
    {
        $this->account(['Office Staff']);

        $this->login('0241234567', 'secret-pass-1')
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->addDays(30)->toIso8601String());
    }

    public function test_a_wrong_password_and_an_unknown_number_get_identical_401s(): void
    {
        $this->account();

        $wrongPassword = $this->login('0241234567', 'not-the-password');
        $unknownNumber = $this->login('0559999999', 'secret-pass-1');

        $wrongPassword->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
        $this->assertSame(LoginController::MSG_INVALID, $wrongPassword->json('message'));
        $this->assertSame($wrongPassword->getContent(), $unknownNumber->getContent());
        $this->assertSame(401, $unknownNumber->status());
    }

    public function test_a_deactivated_account_is_refused_only_after_the_correct_password(): void
    {
        $user = $this->account(active: false);

        $this->login('0241234567', 'not-the-password')->assertStatus(401);
        $this->login('0241234567', 'secret-pass-1')->assertStatus(403)->assertJsonPath('code', 'account_inactive');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_each_login_issues_its_own_device_token(): void
    {
        $user = $this->account();

        $this->login('0241234567', 'secret-pass-1', 'Phone')->assertOk();
        $this->login('0241234567', 'secret-pass-1', 'Tablet')->assertOk();

        $this->assertSame(['Phone', 'Tablet'], $user->tokens()->orderBy('id')->pluck('name')->all());
    }

    public function test_login_is_limited_to_5_per_minute_per_phone(): void
    {
        $this->account();

        for ($i = 1; $i <= 5; $i++) {
            $this->login('0241234567', 'not-the-password')->assertStatus(401);
        }

        $this->login('024 123 4567', 'secret-pass-1')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_validation(): void
    {
        $this->postJson('/api/v1/auth/login', ['phone' => '0241234567', 'password' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrorFor('device_name');

        $this->login('12345', 'secret-pass-1')
            ->assertStatus(422)->assertJsonValidationErrorFor('phone');
    }
}
