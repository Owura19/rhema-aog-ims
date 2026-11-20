<?php

namespace Tests\Feature;

use App\Models\Harvest;
use App\Models\Member;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Guards on the routes that used to be reachable by ANY logged-in user:
 * harvests (incl. Link payments), budget edit/save, visitors, member relationships, loans and
 * biometric devices (45 routes), plus the key-authenticated biometric bridge API (2 routes, own test).
 *
 * The test DB has no production roles, so setUp() recreates them with the
 * exact role -> permission sets that production has today.
 */
class RoutePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const SUPER_ADMIN    = 'Super Admin';
    private const OFFICE_STAFF   = 'Office Staff';
    private const PLEDGE_OFFICER = 'Pledge Officer';
    private const MEMBER         = 'Member';

    /** Mirrors production's role_has_permissions as of this branch. */
    private const ROLE_PERMISSIONS = [
        self::SUPER_ADMIN => [
            'create events', 'create finance', 'create members', 'create visitors',
            'delete events', 'delete finance', 'delete members', 'delete visitors',
            'edit events', 'edit finance', 'edit members', 'edit pledges', 'edit visitors', 'edit vouchers',
            'manage attendance', 'manage cell groups', 'manage pledges', 'manage roles', 'manage settings', 'manage users',
            'view attendance', 'view cell groups', 'view events', 'view finance', 'view members', 'view pledges',
            'view reports', 'view visitors',
        ],
        self::OFFICE_STAFF => [
            'create finance', 'create members', 'edit members',
            'view attendance', 'view cell groups', 'view finance', 'view members',
        ],
        self::PLEDGE_OFFICER => [
            'manage pledges', 'view members', 'view pledges',
        ],
        self::MEMBER => [],
    ];

    /**
     * Every route in scope: name => [HTTP method, route params, roles that must be ALLOWED].
     * Any role not listed must get 403.
     */
    private const ROUTES = [
        // Harvests — view: 'view finance|view pledges' | create/edit/delete: finance-only
        'harvests.index'   => ['GET',    [],          [self::SUPER_ADMIN, self::OFFICE_STAFF, self::PLEDGE_OFFICER]],
        'harvests.create'  => ['GET',    [],          [self::SUPER_ADMIN, self::OFFICE_STAFF]],
        'harvests.store'   => ['POST',   [],          [self::SUPER_ADMIN, self::OFFICE_STAFF]],
        'harvests.show'    => ['GET',    ['harvest'], [self::SUPER_ADMIN, self::OFFICE_STAFF, self::PLEDGE_OFFICER]],
        'harvests.edit'    => ['GET',    ['harvest'], [self::SUPER_ADMIN]],
        'harvests.update'  => ['PUT',    ['harvest'], [self::SUPER_ADMIN]],
        'harvests.destroy' => ['DELETE', ['harvest'], [self::SUPER_ADMIN]],
        // Link payments (Finance confirms harvest links) — 'edit finance'
        'harvests.link-payments'         => ['GET',    [],          [self::SUPER_ADMIN]],
        'harvests.link-payments.preview' => ['POST',   [],          [self::SUPER_ADMIN]],
        'harvests.link-payments.store'   => ['POST',   [],          [self::SUPER_ADMIN]],
        'harvests.link-payments.unlink'  => ['DELETE', ['payment'], [self::SUPER_ADMIN]],

        // Budget — report: 'view finance' | edit/save: 'edit finance'
        'finance.budget.report' => ['GET',  [], [self::SUPER_ADMIN, self::OFFICE_STAFF]],
        'finance.budget.edit'   => ['GET',  [], [self::SUPER_ADMIN]],
        'finance.budget.update' => ['POST', [], [self::SUPER_ADMIN]],

        // Visitors — matching 'visitors' permission; convert: 'create members'
        'visitors.index'   => ['GET',    [],          [self::SUPER_ADMIN]],
        'visitors.create'  => ['GET',    [],          [self::SUPER_ADMIN]],
        'visitors.store'   => ['POST',   [],          [self::SUPER_ADMIN]],
        'visitors.show'    => ['GET',    ['visitor'], [self::SUPER_ADMIN]],
        'visitors.edit'    => ['GET',    ['visitor'], [self::SUPER_ADMIN]],
        'visitors.update'  => ['PUT',    ['visitor'], [self::SUPER_ADMIN]],
        'visitors.destroy' => ['DELETE', ['visitor'], [self::SUPER_ADMIN]],
        'visitors.convert' => ['POST',   ['visitor'], [self::SUPER_ADMIN, self::OFFICE_STAFF]],

        // Member relationships — 'edit members'
        'members.relationships.store'   => ['POST',   ['member'],            [self::SUPER_ADMIN, self::OFFICE_STAFF]],
        'members.relationships.destroy' => ['DELETE', ['member', 'related'], [self::SUPER_ADMIN, self::OFFICE_STAFF]],

        // Loans — view/export: 'view finance' | create/edit/adjust/recalculate: 'edit finance'
        'loans.index'       => ['GET',  [],       [self::SUPER_ADMIN, self::OFFICE_STAFF]],
        'loans.show'        => ['GET',  ['loan'], [self::SUPER_ADMIN, self::OFFICE_STAFF]],
        'loans.export'      => ['GET',  ['loan'], [self::SUPER_ADMIN, self::OFFICE_STAFF]],
        'loans.create'      => ['GET',  [],       [self::SUPER_ADMIN]],
        'loans.preview'     => ['POST', [],       [self::SUPER_ADMIN]],
        'loans.store'       => ['POST', [],       [self::SUPER_ADMIN]],
        'loans.edit'        => ['GET',  ['loan'], [self::SUPER_ADMIN]],
        'loans.update'      => ['PUT',  ['loan'], [self::SUPER_ADMIN]],
        'loans.adjust'      => ['POST', ['loan'], [self::SUPER_ADMIN]],
        'loans.recalculate' => ['POST', ['loan'], [self::SUPER_ADMIN]],

        // Biometric devices — Super Admin only (role middleware; incl. the connection key and reprocess actions)
        'devices.index'        => ['GET',    [],         [self::SUPER_ADMIN]],
        'devices.create'       => ['GET',    [],         [self::SUPER_ADMIN]],
        'devices.store'        => ['POST',   [],         [self::SUPER_ADMIN]],
        'devices.show'         => ['GET',    ['device'], [self::SUPER_ADMIN]],
        'devices.edit'         => ['GET',    ['device'], [self::SUPER_ADMIN]],
        'devices.update'       => ['PUT',    ['device'], [self::SUPER_ADMIN]],
        'devices.destroy'      => ['DELETE', ['device'], [self::SUPER_ADMIN]],
        'devices.test'         => ['POST',   ['device'], [self::SUPER_ADMIN]],
        'devices.key.generate' => ['POST',   ['device'], [self::SUPER_ADMIN]],
        'devices.key.revoke'   => ['DELETE', ['device'], [self::SUPER_ADMIN]],
        'devices.reprocess'    => ['POST',   ['device'], [self::SUPER_ADMIN]],
    ];

    /**
     * The biometric bridge API: NOT permission-based. A device's connection key is the only way in
     * (a logged-in user, whatever their role, is refused), so these can't sit in the role matrix
     * above; they have their own test below.
     */
    private const BRIDGE_ROUTES = [
        'api.v1.bridge.ping'    => 'GET',
        'api.v1.bridge.punches' => 'POST',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS[self::SUPER_ADMIN] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }

    public static function roles(): array
    {
        return [
            self::SUPER_ADMIN    => [self::SUPER_ADMIN],
            self::OFFICE_STAFF   => [self::OFFICE_STAFF],
            self::PLEDGE_OFFICER => [self::PLEDGE_OFFICER],
            self::MEMBER         => [self::MEMBER],
        ];
    }

    /**
     * The table above must match the real routes exactly, so a route added later
     * under these paths can't slip through without a guard and a test row.
     */
    public function test_route_table_matches_registered_routes(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => preg_match('#^(harvests|visitors|budget|loans|devices)(/|$)#', $route->uri())
                || preg_match('#^members/\{member\}/relationships#', $route->uri()))
            ->map(fn ($route) => $route->getName())
            ->sort()->values()->all();

        $expected = collect(array_keys(self::ROUTES))->sort()->values()->all();

        $this->assertCount(45, $expected);
        $this->assertSame($expected, $registered);

        foreach (self::ROUTES as $name => [$method]) {
            $this->assertContains($method, Route::getRoutes()->getByName($name)->methods(), "{$name} should accept {$method}");
        }
    }

    /** The bridge routes: listed exactly, key-authenticated, and carrying no role or permission middleware. */
    public function test_bridge_routes_are_token_authenticated_not_role_based(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => preg_match('#^api/v1/bridge(/|$)#', $route->uri()))
            ->map(fn ($route) => $route->getName())->sort()->values()->all();
        $expected = collect(array_keys(self::BRIDGE_ROUTES))->sort()->values()->all();
        $this->assertSame($expected, $registered);

        $admin = User::factory()->create()->assignRole(self::SUPER_ADMIN);

        foreach (self::BRIDGE_ROUTES as $name => $method) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains($method, $route->methods(), "{$name} should accept {$method}");

            $middleware = $route->gatherMiddleware();
            $this->assertContains('bridge.device', $middleware, "{$name} must check the device key");
            $this->assertContains('throttle:bridge', $middleware, "{$name} must be rate limited");
            foreach ($middleware as $m) {
                $this->assertDoesNotMatchRegularExpression('/^(role|permission|role_or_permission|auth)[:.]|^auth$/', (string) $m, "{$name} must not depend on a login or role ({$m})");
            }

            $url = route($name);
            $this->json($method, $url)->assertUnauthorized();                              // nobody
            $this->actingAs($admin)->json($method, $url)->assertUnauthorized();           // a Super Admin session is not a device key
            $this->json($method, $url, [], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        }
    }

    public function test_guest_is_redirected_to_login_on_every_route(): void
    {
        $failures = [];

        foreach (self::ROUTES as $name => [$method, $params]) {
            $response = $this->call($method, $this->urlFor($name, $params));

            if (! $response->isRedirect(route('login'))) {
                $failures[] = "{$method} {$name}: expected redirect to login, got {$response->status()}";
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    #[DataProvider('roles')]
    public function test_role_access_matches_permission_matrix(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $failures = [];

        foreach (self::ROUTES as $name => [$method, $params, $allowedRoles]) {
            $response = $this->actingAs($user)->call($method, $this->urlFor($name, $params));
            $status   = $response->getStatusCode();   // (works for streamed downloads too)

            if (in_array($role, $allowedRoles, true)) {
                // Allowed: anything except 403 (redirects / validation errors are fine), but never a server error.
                if ($status === 403 || $status >= 500) {
                    $error = $response->exception ? ' — ' . $response->exception->getMessage() : '';
                    $failures[] = "{$method} {$name}: expected ALLOWED (not 403, not 5xx), got {$status}{$error}";
                }
            } elseif ($status !== 403) {
                $failures[] = "{$method} {$name}: expected 403, got {$status}";
            }
        }

        $this->assertSame([], $failures, "[{$role}]\n" . implode("\n", $failures));
    }

    /**
     * Builds the URL with FRESH fixtures for each request, so a delete in one
     * request can't affect the next one.
     */
    private function urlFor(string $name, array $params): string
    {
        $values = [];

        foreach ($params as $param) {
            $values[$param] = match ($param) {
                'harvest' => Harvest::create([
                    'name'          => 'Test Harvest',
                    'year'          => 2026,
                    'target_amount' => 1000,
                    'status'        => 'Active',
                ]),
                'visitor' => Visitor::create([
                    'first_name'       => 'Test',
                    'last_name'        => 'Visitor',
                    'visit_date'       => '2026-11-01',
                    'visit_type'       => 'First Time',
                    'follow_up_status' => 'Pending',
                ]),
                'member', 'related' => Member::create([
                    'first_name' => 'Test',
                    'last_name'  => ucfirst($param),
                ]),
                // No IP: the "Test connection" route must never dial out from a test
                'device' => \App\Models\BiometricDevice::create(['name' => 'Test Device', 'is_active' => true]),
                // A paid-off loan, so fixtures never compete for the same counted types
                'loan' => \App\Models\Loan::create([
                    'lender'                     => 'Test Lender',
                    'liability_account_id'       => \App\Models\Account::firstOrCreate(['code' => '2100'], ['name' => 'Loans Payable', 'type' => 'Liability', 'is_group' => false, 'is_active' => true, 'sort_order' => 2100])->id,
                    'opening_balance'            => 100,
                    'opening_date'               => '2026-11-20',
                    'opening_counter_account_id' => \App\Models\Account::firstOrCreate(['code' => '3200'], ['name' => 'Opening Balance Equity', 'type' => 'Equity', 'is_group' => false, 'is_active' => true, 'sort_order' => 3200])->id,
                    'repayment_start_date'       => '2026-11-21',
                    'counted_types'              => ['Harvest'],
                    'repayment_source'           => 'received',
                    'status'                     => 'Paid off',
                ]),
                'payment' => \App\Models\PledgePayment::create([
                    'pledge_id'      => \App\Models\Pledge::create([
                        'pledger_name'      => 'Test Pledger',
                        'pledge_purpose_id' => \App\Models\PledgePurpose::where('name', 'Harvest')->value('id'),
                        'amount_pledged'    => 100,
                        'date_pledged'      => '2026-11-01',
                        'status'            => 'Active',
                    ])->id,
                    'amount'         => 10,
                    'payment_date'   => '2026-11-01',
                    'payment_method' => 'Cash',
                ]),
            };
        }

        return route($name, $values);
    }
}
