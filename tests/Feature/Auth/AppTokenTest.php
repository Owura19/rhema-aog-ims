<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * App tokens: members 90 days, staff (any role other than Member) 30 days,
 * a 180-day Sanctum backstop for any token, and the "rhema_" prefix.
 */
class AppTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(9, 0));
    }

    private function userWithRoles(string ...$roles): User
    {
        $user = User::factory()->create();
        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }
        $user->assignRole($roles);

        return $user->fresh();
    }

    public static function lifetimes(): array
    {
        return [
            'Member only'            => [['Member'], 90],
            'no role at all'         => [[], 90],
            'Office Staff'           => [['Office Staff'], 30],
            'Member + Pastor (staff)'=> [['Member', 'Pastor'], 30],
            'Super Admin'            => [['Super Admin'], 30],
        ];
    }

    #[DataProvider('lifetimes')]
    public function test_token_lifetime_depends_on_roles(array $roles, int $days): void
    {
        $user = $this->userWithRoles(...$roles);

        $token = $user->createAppToken('Ama Samsung A14');

        $this->assertSame('Ama Samsung A14', $token->accessToken->name);
        $this->assertTrue($token->accessToken->expires_at->equalTo(now()->addDays($days)));
        $this->assertSame($days === 30, $user->isStaff());
    }

    public function test_tokens_carry_the_rhema_prefix(): void
    {
        $plain = $this->userWithRoles('Member')->createAppToken('phone')->plainTextToken;

        // Sanctum's format is "{id}|{prefix}{random}".
        $this->assertStringStartsWith('rhema_', Str::after($plain, '|'));
    }

    public function test_an_app_token_works_until_it_expires(): void
    {
        $plain = $this->userWithRoles('Member')->createAppToken('phone')->plainTextToken;

        $this->withToken($plain)->get('/api/v1/me')->assertOk();

        $this->travel(91)->days();
        $this->app['auth']->forgetGuards();

        $this->withToken($plain)->get('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_180_day_backstop_applies_even_to_tokens_without_expiry(): void
    {
        $this->assertSame(60 * 24 * 180, config('sanctum.expiration'));

        $plain = $this->userWithRoles('Member')->createToken('no-expiry')->plainTextToken;

        $this->travel(179)->days();
        $this->withToken($plain)->get('/api/v1/me')->assertOk();

        $this->travel(2)->days();   // day 181
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->get('/api/v1/me')->assertUnauthorized();
    }

    public function test_expired_tokens_are_pruned_daily_by_the_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('sanctum:prune-expired --hours=24')
            ->assertSuccessful();
    }
}
