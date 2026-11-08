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
 * harvests, budget edit/save, visitors, and member relationships (20 routes).
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
            ->filter(fn ($route) => preg_match('#^(harvests|visitors|budget)(/|$)#', $route->uri())
                || preg_match('#^members/\{member\}/relationships#', $route->uri()))
            ->map(fn ($route) => $route->getName())
            ->sort()->values()->all();

        $expected = collect(array_keys(self::ROUTES))->sort()->values()->all();

        $this->assertCount(20, $expected);
        $this->assertSame($expected, $registered);

        foreach (self::ROUTES as $name => [$method]) {
            $this->assertContains($method, Route::getRoutes()->getByName($name)->methods(), "{$name} should accept {$method}");
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
            $status   = $response->status();

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
            };
        }

        return route($name, $values);
    }
}
