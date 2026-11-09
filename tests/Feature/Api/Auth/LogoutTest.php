<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** POST /api/v1/auth/logout and /api/v1/auth/logout-all */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private function me(string $token): int
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/me')->status();
    }

    public function test_logout_ends_only_this_device(): void
    {
        $user = User::factory()->create();
        $phone  = $user->createAppToken('Phone')->plainTextToken;
        $tablet = $user->createAppToken('Tablet')->plainTextToken;

        $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(401, $this->me($phone));
        $this->assertSame(200, $this->me($tablet));
        $this->assertSame(['Tablet'], $user->tokens()->pluck('name')->all());
    }

    public function test_logout_all_ends_every_device(): void
    {
        $user = User::factory()->create();
        $phone  = $user->createAppToken('Phone')->plainTextToken;
        $tablet = $user->createAppToken('Tablet')->plainTextToken;
        $other  = User::factory()->create()->createAppToken('Someone else')->plainTextToken;

        $this->withToken($phone)->postJson('/api/v1/auth/logout-all')->assertNoContent();

        $this->assertSame(401, $this->me($phone));
        $this->assertSame(401, $this->me($tablet));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(200, $this->me($other), "other users' devices are untouched");
    }

    public function test_both_need_a_token(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->postJson('/api/v1/auth/logout-all')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
