<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Auth\Concerns\ActivationHelpers;
use Tests\TestCase;

/** users.must_change_password: a temporary password allows only /me, /me/password and logout. */
class PasswordChangeRequiredTest extends TestCase
{
    use RefreshDatabase, ActivationHelpers;

    private const PASSWORD = 'secret-pass-1';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $member = $this->member('0241234567');
        $this->user = User::factory()->create([
            'member_id'            => $member->id,
            'name'                 => $member->full_name,
            'email'                => null,
            'login_phone'          => '+233241234567',
            'password'             => self::PASSWORD,
            'must_change_password' => true,
        ]);
    }

    private function token(): string
    {
        return $this->user->createAppToken('Test phone')->plainTextToken;
    }

    private function as(string $token, string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($method, '/api/v1' . $uri, $data);
    }

    public static function flags(): array
    {
        return ['must change' => [true], 'chosen password' => [false]];
    }

    #[DataProvider('flags')]
    public function test_login_returns_must_change_password(bool $flag): void
    {
        $this->user->forceFill(['must_change_password' => $flag])->save();

        $this->postJson('/api/v1/auth/login', ['phone' => '0241234567', 'password' => self::PASSWORD, 'device_name' => 'Phone'])
            ->assertOk()
            ->assertJsonPath('data.user.must_change_password', $flag);
    }

    public function test_me_includes_must_change_password_as_the_last_key(): void
    {
        $response = $this->as($this->token(), 'GET', '/me')->assertOk();

        $keys = array_keys($response->json('data'));
        $this->assertSame('must_change_password', end($keys));
        $response->assertJsonPath('data.must_change_password', true);
    }

    public static function allowed(): array
    {
        return [
            'GET /me'               => ['GET', '/me', [], 200],
            'PUT /me/password'      => ['PUT', '/me/password', ['current_password' => 'wrong', 'password' => 'new-pass-123', 'password_confirmation' => 'new-pass-123', 'device_name' => 'Phone'], 422],
            'POST /auth/logout'     => ['POST', '/auth/logout', [], 204],
            'POST /auth/logout-all' => ['POST', '/auth/logout-all', [], 204],
        ];
    }

    #[DataProvider('allowed')]
    public function test_allowed_routes_still_work_while_the_flag_is_set(string $method, string $uri, array $data, int $status): void
    {
        $response = $this->as($this->token(), $method, $uri, $data);

        $response->assertStatus($status);   // reached the controller (PUT: 422 for the wrong current password)
        $this->assertStringNotContainsString('password_change_required', $response->getContent());   // 204s have no body
    }

    public static function blocked(): array
    {
        return [
            'profile'   => ['/me/profile'],
            'devotions' => ['/devotions/today'],
            'events'    => ['/events'],
            'calendar'  => ['/calendar'],
        ];
    }

    #[DataProvider('blocked')]
    public function test_other_routes_are_blocked_with_password_change_required(string $uri): void
    {
        $this->as($this->token(), 'GET', $uri)
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Please change your password to continue.', 'code' => 'password_change_required']);
    }

    public function test_every_authenticated_api_route_has_the_middleware(): void
    {
        $authenticated = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/') && in_array('auth:sanctum', $route->gatherMiddleware(), true));

        $this->assertGreaterThanOrEqual(15, $authenticated->count());
        foreach ($authenticated as $route) {
            $this->assertContains('password.changed', $route->gatherMiddleware(), $route->uri() . ' lacks password.changed');
        }

        foreach (EnsurePasswordChanged::ALLOWED_ROUTES as $name) {
            $this->assertTrue(Route::has($name), "{$name} is not a route");
        }
    }

    public function test_changing_the_password_clears_the_flag_and_unblocks_the_app(): void
    {
        $response = $this->as($this->token(), 'PUT', '/me/password', [
            'current_password'      => self::PASSWORD,
            'password'              => 'new-pass-123',
            'password_confirmation' => 'new-pass-123',
            'device_name'           => 'Phone',
        ])->assertOk()->assertJsonPath('data.user.must_change_password', false);

        $this->assertFalse($this->user->fresh()->must_change_password);
        $this->as($response->json('data.token'), 'GET', '/me/profile')->assertOk();
    }

    public function test_an_sms_password_reset_clears_the_flag(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', ['phone' => '0241234567'])->assertStatus(202);
        $code = $this->lastCodeSentTo('+233241234567');
        $this->assertNotNull($code);

        $this->postJson('/api/v1/auth/password/reset', [
            'phone'                 => '0241234567',
            'code'                  => $code,
            'password'              => 'new-pass-123',
            'password_confirmation' => 'new-pass-123',
            'device_name'           => 'Phone',
        ])->assertOk()->assertJsonPath('data.user.must_change_password', false);

        $this->assertFalse($this->user->fresh()->must_change_password);
    }

    public function test_activation_creates_logins_without_the_flag(): void
    {
        $kofi = $this->member('0551234567', 'Active', 'Kofi');
        $session = $this->verifiedSession('0551234567', '+233551234567')->assertOk()->json('data.activation_token');

        $this->complete($session, $kofi->id)->assertCreated()->assertJsonPath('data.user.must_change_password', false);

        $this->assertFalse(User::where('login_phone', '+233551234567')->sole()->must_change_password);
    }

    public function test_changing_the_password_on_the_website_clears_the_flag(): void
    {
        $this->user->forceFill(['email' => 'ama@example.com'])->save();

        $this->actingAs($this->user)->from('/profile')->put(route('password.update'), [
            'current_password'      => self::PASSWORD,
            'password'              => 'New-pass-12345',
            'password_confirmation' => 'New-pass-12345',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated');

        $this->assertFalse($this->user->fresh()->must_change_password);
    }
}
