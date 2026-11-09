<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** PUT /api/v1/me/password */
class MePasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $phoneToken;
    private string $tabletToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => null, 'login_phone' => '+233241234567', 'password' => 'old-pass-123']);
        $this->phoneToken  = $this->user->createAppToken('Phone')->plainTextToken;
        $this->tabletToken = $this->user->createAppToken('Tablet')->plainTextToken;
    }

    private function change(string $current, string $new = 'new-pass-123', ?string $confirmation = null, string $device = 'Phone'): TestResponse
    {
        return $this->withToken($this->phoneToken)->putJson('/api/v1/me/password', [
            'current_password'      => $current,
            'password'              => $new,
            'password_confirmation' => $confirmation ?? $new,
            'device_name'           => $device,
        ]);
    }

    private function me(string $token): int
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/me')->status();
    }

    public function test_a_wrong_current_password_is_422_and_changes_nothing(): void
    {
        $this->change('not-my-password')
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password', ['The current password is incorrect.']);

        $this->assertTrue(Hash::check('old-pass-123', $this->user->fresh()->password));
        $this->assertSame(2, $this->user->tokens()->count());
    }

    public function test_changing_the_password_signs_out_every_device_and_returns_a_new_token(): void
    {
        $response = $this->change('old-pass-123', 'new-pass-123', null, 'Phone (after change)');

        $response->assertOk()->assertJsonPath('data.token_type', 'Bearer');
        $newToken = $response->json('data.token');

        $this->assertTrue(Hash::check('new-pass-123', $this->user->fresh()->password));
        $this->assertSame(['Phone (after change)'], $this->user->tokens()->pluck('name')->all());
        $this->assertSame(401, $this->me($this->phoneToken));
        $this->assertSame(401, $this->me($this->tabletToken));
        $this->assertSame(200, $this->me($newToken));
    }

    public function test_attempts_are_limited_to_5_per_minute(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->change('not-my-password')->assertStatus(422);
        }

        $this->change('old-pass-123')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
        $this->assertTrue(Hash::check('old-pass-123', $this->user->fresh()->password));
    }

    public function test_the_new_password_follows_the_app_rules(): void
    {
        $this->change('old-pass-123', 'short')->assertStatus(422)->assertJsonValidationErrorFor('password');
        $this->change('old-pass-123', str_repeat(mb_chr(0x025B, 'UTF-8'), 37))->assertStatus(422)->assertJsonValidationErrorFor('password');
        $this->change('old-pass-123', 'new-pass-123', 'different-123')->assertStatus(422)->assertJsonValidationErrorFor('password');

        $this->assertTrue(Hash::check('old-pass-123', $this->user->fresh()->password));
    }

    public function test_it_needs_a_token(): void
    {
        $this->putJson('/api/v1/me/password', [])->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
