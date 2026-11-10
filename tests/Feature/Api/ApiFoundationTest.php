<?php

namespace Tests\Feature\Api;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Step 2 — API foundation:
 *  - everything under /api/* answers in JSON with one error shape {message, code, errors?}
 *  - 401 for missing/invalid/expired tokens (never a redirect to /login)
 *  - 'active' and 'member' middleware
 *  - rate limiters 'api' (60/min per user, 120/min per guest IP) and
 *    'api-auth' (5/min per normalised phone, 100/min per IP)
 *  - the web app keeps its HTML/redirect behaviour
 *
 * Requests deliberately send NO Accept header unless stated, like a careless client.
 */
class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test-only routes inside the real 'api' middleware group (JSON + throttle:api + bindings).
        Route::middleware('api')->prefix('api/v1/__test')->group(function () {
            Route::middleware(['auth:sanctum', 'active', 'member'])
                ->get('/member-only', fn () => response()->json(['data' => ['ok' => true]]));
            Route::middleware(['auth:sanctum', 'active', 'permission:view finance'])
                ->get('/needs-permission', fn () => response()->json(['data' => ['ok' => true]]));
            Route::post('/validate', fn (Request $request) => response()->json([
                'data' => $request->validate(['name' => 'required|string']),
            ]));
            Route::get('/members/{member}', fn (Member $member) => response()->json(['data' => ['id' => $member->id]]));
            Route::get('/boom', function () {
                throw new \RuntimeException('internal detail xyz');
            });
            Route::get('/unavailable', fn () => abort(503, 'Service Unavailable', ['Retry-After' => 120]));
            Route::middleware('throttle:api-auth')
                ->post('/auth-limited', fn () => response()->json(['data' => ['ok' => true]]));
        });
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function tokenFor(User $user, ?\DateTimeInterface $expiresAt = null): string
    {
        return $user->createToken('test-device', ['*'], $expiresAt)->plainTextToken;
    }

    private function linkedMemberUser(array $userAttributes = []): User
    {
        $member = Member::create(['first_name' => 'Ama', 'last_name' => 'Mensah']);

        return User::factory()->create(['member_id' => $member->id] + $userAttributes);
    }

    private function assertApiError($response, int $status, string $code): void
    {
        $response->assertStatus($status);
        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonPath('code', $code);
        $this->assertIsString($response->json('message'));
        $this->assertNotSame('', $response->json('message'));
    }

    // ── routing, JSON, error shape ─────────────────────────────────────

    public function test_health_is_public_json(): void
    {
        $response = $this->get('/api/v1/health');

        $response->assertOk();
        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonPath('data.status', 'ok');
        $response->assertJsonPath('data.version', 'v1');
        $this->assertNotEmpty($response->json('data.server_time'));
    }

    public function test_unknown_api_route_is_a_json_404(): void
    {
        $this->assertApiError($this->get('/api/v1/nope'), 404, 'not_found');
    }

    public function test_wrong_method_is_a_json_405(): void
    {
        $this->assertApiError($this->post('/api/v1/health'), 405, 'method_not_allowed');
    }

    public function test_missing_model_is_a_json_404_without_leaking_the_model_name(): void
    {
        $response = $this->get('/api/v1/__test/members/999999');

        $this->assertApiError($response, 404, 'not_found');
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }

    public function test_validation_failure_is_a_json_422_with_field_errors(): void
    {
        $response = $this->post('/api/v1/__test/validate', []);

        $this->assertApiError($response, 422, 'validation_failed');
        $this->assertArrayHasKey('name', $response->json('errors'));
    }

    public function test_server_error_is_generic_when_debug_is_off(): void
    {
        config(['app.debug' => false, 'logging.default' => 'null']);

        $response = $this->get('/api/v1/__test/boom');

        $this->assertApiError($response, 500, 'server_error');
        $this->assertStringNotContainsString('internal detail xyz', $response->getContent());
        $this->assertNull($response->json('debug'));
    }

    public function test_server_error_includes_debug_detail_only_when_debug_is_on(): void
    {
        config(['app.debug' => true, 'logging.default' => 'null']);

        $response = $this->get('/api/v1/__test/boom');

        $this->assertApiError($response, 500, 'server_error');
        $response->assertJsonPath('debug.message', 'internal detail xyz');
    }

    // ── maintenance (php artisan down) ─────────────────────────────────

    /**
     * Real maintenance mode, but with the cache driver on the in-memory array store,
     * so no storage/framework/down file is written and the local site is never taken down.
     */
    private function activateMaintenanceInMemory(array $data): void
    {
        config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
        $this->assertFileDoesNotExist(storage_path('framework/down'), 'a real down file exists — refusing to run');

        $this->app->maintenanceMode()->activate($data + ['time' => now()->getTimestamp()]);
    }

    public function test_api_503_maps_to_maintenance_and_keeps_retry_after(): void
    {
        $response = $this->get('/api/v1/__test/unavailable');

        $this->assertApiError($response, 503, 'maintenance');
        $this->assertSame('120', (string) $response->headers->get('Retry-After'));
        $response->assertJsonPath('retry_after', 120);
    }

    public function test_real_maintenance_mode_gives_the_api_a_json_maintenance_response(): void
    {
        $this->activateMaintenanceInMemory(['retry' => 60]);

        $response = $this->get('/api/v1/health');   // no Accept header, like `php artisan down --retry=60`

        $this->assertApiError($response, 503, 'maintenance');
        $this->assertSame('60', (string) $response->headers->get('Retry-After'));
        $response->assertJsonPath('retry_after', 60);
        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    public function test_web_maintenance_page_is_still_html(): void
    {
        $this->activateMaintenanceInMemory([]);

        $response = $this->get('/login');

        $response->assertStatus(503);
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }

    // ── authentication ─────────────────────────────────────────────────

    public function test_me_without_token_is_a_json_401_not_a_redirect(): void
    {
        $response = $this->get('/api/v1/me');

        $this->assertApiError($response, 401, 'unauthenticated');
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_me_with_a_garbage_token_is_401(): void
    {
        $this->assertApiError($this->withToken('1|not-a-real-token')->get('/api/v1/me'), 401, 'unauthenticated');
    }

    public function test_me_with_an_expired_token_is_401(): void
    {
        $token = $this->tokenFor($this->linkedMemberUser(), now()->subMinute());

        $this->assertApiError($this->withToken($token)->get('/api/v1/me'), 401, 'unauthenticated');
    }

    public function test_me_returns_exactly_the_agreed_fields(): void
    {
        $user = $this->linkedMemberUser();
        $response = $this->withToken($this->tokenFor($user))->get('/api/v1/me');

        $response->assertOk();
        $this->assertSame(
            ['id', 'name', 'login_phone', 'member', 'roles', 'permissions', 'token_expires_at', 'must_change_password'],
            array_keys($response->json('data')),
        );
        $this->assertSame(['id', 'member_code', 'full_name'], array_keys($response->json('data.member')));
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.member.full_name', 'Ama Mensah');

        foreach (['password', 'remember_token', 'email'] as $secret) {
            $this->assertStringNotContainsString('"' . $secret . '"', $response->getContent());
        }
    }

    public function test_me_for_an_inactive_user_is_403_account_inactive(): void
    {
        $token = $this->tokenFor($this->linkedMemberUser(['is_active' => false]));

        $this->assertApiError($this->withToken($token)->get('/api/v1/me'), 403, 'account_inactive');
    }

    // ── member middleware ──────────────────────────────────────────────

    public static function memberLinks(): array
    {
        return [
            'no member link'         => ['none', 403],
            'member soft-deleted'    => ['deleted', 403],
            'linked, active member'  => ['linked', 200],
        ];
    }

    #[DataProvider('memberLinks')]
    public function test_member_middleware(string $link, int $status): void
    {
        $user = match ($link) {
            'none'    => User::factory()->create(),
            'deleted' => tap($this->linkedMemberUser(), fn (User $u) => Member::find($u->member_id)->delete()),
            'linked'  => $this->linkedMemberUser(),
        };

        $response = $this->withToken($this->tokenFor($user))->get('/api/v1/__test/member-only');

        if ($status === 200) {
            $response->assertOk()->assertJsonPath('data.ok', true);
        } else {
            $this->assertApiError($response, 403, 'member_link_required');
        }
    }

    // ── permissions ────────────────────────────────────────────────────

    public function test_missing_permission_is_403_forbidden(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view finance', 'web');

        $token = $this->tokenFor($this->linkedMemberUser());

        $this->assertApiError($this->withToken($token)->get('/api/v1/__test/needs-permission'), 403, 'forbidden');
    }

    // ── rate limits ────────────────────────────────────────────────────

    public function test_guest_api_limit_is_120_per_minute_per_ip(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            $this->get('/api/v1/health')->assertOk();
        }

        $response = $this->get('/api/v1/health');

        $this->assertApiError($response, 429, 'too_many_requests');
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, $response->json('retry_after'));
    }

    public function test_authenticated_api_limit_is_60_per_minute_per_user(): void
    {
        $token = $this->tokenFor($this->linkedMemberUser());

        for ($i = 1; $i <= 60; $i++) {
            $this->withToken($token)->get('/api/v1/me')->assertOk();
        }

        $this->assertApiError($this->withToken($token)->get('/api/v1/me'), 429, 'too_many_requests');
    }

    public function test_auth_limit_is_5_per_minute_per_normalised_phone(): void
    {
        // Five different spellings of the SAME number share one bucket.
        foreach (['0241234567', '+233241234567', '233241234567', '024 123 4567', '00233241234567'] as $spelling) {
            $this->post('/api/v1/__test/auth-limited', ['phone' => $spelling])->assertOk();
        }

        $this->assertApiError($this->post('/api/v1/__test/auth-limited', ['phone' => '024-123-4567']), 429, 'too_many_requests');

        // A different number from the same IP is not blocked by that phone's limit.
        $this->post('/api/v1/__test/auth-limited', ['phone' => '0551234567'])->assertOk();
    }

    public function test_auth_limit_is_100_per_minute_per_ip_across_different_phones(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            $this->post('/api/v1/__test/auth-limited', ['phone' => sprintf('0240000%03d', $i)])->assertOk();
        }

        $this->assertApiError(
            $this->post('/api/v1/__test/auth-limited', ['phone' => '0240000999']),
            429,
            'too_many_requests'
        );
    }

    // ── the web app is unaffected ──────────────────────────────────────

    public function test_web_guest_is_still_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_web_404_is_still_html(): void
    {
        $response = $this->get('/definitely-not-a-page');

        $response->assertNotFound();
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }
}
