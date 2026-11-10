<?php

namespace Tests\Feature\Web;

use App\Models\ChurchService;
use App\Models\Pledge;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * ChurchService::forPicker() and the 7 "which service?" dropdowns that use it.
 * Future services (e.g. generated weekly programmes) must never push today's out.
 * Today is Monday 2026-11-09.
 */
class ServicePickerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00'));

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissions = [
            'view finance', 'create finance', 'edit finance',
            'view pledges', 'manage pledges', 'edit pledges',
            'view visitors', 'create visitors', 'edit visitors',
            'view members',
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('Super Admin', 'web')->syncPermissions($permissions);

        $registrar->forgetCachedPermissions();

        $this->admin = User::factory()->create()->assignRole('Super Admin');
    }

    private function service(string $date, string $time = '09:00', string $name = 'Service'): ChurchService
    {
        return ChurchService::create([
            'name' => $name, 'service_type' => 'Sunday Service', 'service_date' => $date,
            'start_time' => $time, 'status' => 'Scheduled',
        ]);
    }

    /**
     * Today's service, one service on each of the 22 days before, and 3 future ones.
     *
     * @return array{today: ChurchService, past: list<ChurchService>, future: list<ChurchService>}
     */
    private function seedServices(): array
    {
        $today = $this->service('2026-11-09', '07:00', 'Today');
        $past = [];
        for ($daysAgo = 1; $daysAgo <= 22; $daysAgo++) {
            $past[] = $this->service(today()->subDays($daysAgo)->toDateString());
        }
        $future = [
            $this->service('2026-11-10', '18:30', 'Tomorrow'),
            $this->service('2026-11-16'),
            $this->service('2026-12-09'),
        ];

        return ['today' => $today, 'past' => $past, 'future' => $future];
    }

    /** @return list<int> */
    private function ids($services): array
    {
        return collect($services)->pluck('id')->all();
    }

    // ── the scope ──────────────────────────────────────────────────────

    public function test_it_lists_the_20_most_recent_services_dated_today_or_earlier(): void
    {
        ['today' => $today, 'past' => $past, 'future' => $future] = $this->seedServices();

        $picked = ChurchService::forPicker()->get();

        $this->assertSame([$today->id, ...$this->ids(array_slice($past, 0, 19))], $this->ids($picked));
        $this->assertEmpty(array_intersect($this->ids($future), $this->ids($picked)));
    }

    public function test_services_on_the_same_day_are_latest_time_first_then_newest_id(): void
    {
        $morning = $this->service('2026-11-08', '07:00');
        $evening = $this->service('2026-11-08', '18:00');
        $evening2 = $this->service('2026-11-08', '18:00');

        $this->assertSame([$evening2->id, $evening->id, $morning->id], $this->ids(ChurchService::forPicker()->get()));
    }

    public function test_an_included_older_service_is_added_after_the_20(): void
    {
        ['today' => $today, 'past' => $past] = $this->seedServices();
        $old = $this->service('2026-05-01', '09:00', 'Old service');

        $picked = ChurchService::forPicker($old->id)->get();

        $this->assertCount(21, $picked);
        $this->assertSame($old->id, $picked->last()->id);
        $this->assertSame([$today->id, ...$this->ids(array_slice($past, 0, 19)), $old->id], $this->ids($picked));
    }

    public function test_an_included_service_already_listed_is_not_duplicated(): void
    {
        ['today' => $today] = $this->seedServices();

        $picked = ChurchService::forPicker($today->id)->get();

        $this->assertCount(20, $picked);
        $this->assertCount(20, array_unique($this->ids($picked)));
    }

    public function test_an_included_future_service_is_kept(): void
    {
        ['future' => $future] = $this->seedServices();

        $picked = $this->ids(ChurchService::forPicker($future[2]->id)->get());

        $this->assertContains($future[2]->id, $picked);
        $this->assertNotContains($future[0]->id, $picked);
        $this->assertCount(21, $picked);
    }

    // ── the 7 dropdowns ────────────────────────────────────────────────

    private function record(string $kind, ?ChurchService $service): ?Model
    {
        $serviceId = $service?->id;

        return match ($kind) {
            'none'        => null,
            'transaction' => Transaction::create([
                'type' => 'Tithe', 'category' => 'Income', 'amount' => '50.00', 'transaction_date' => '2026-11-08',
                'payment_method' => 'Cash', 'status' => 'Confirmed', 'church_service_id' => $serviceId,
            ]),
            'pledge'      => Pledge::create([
                'pledge_purpose_id' => PledgePurpose::firstOrCreate(['name' => 'Building Project'])->id,
                'pledger_name' => 'Kojo Test', 'amount_pledged' => '100.00', 'date_pledged' => '2026-11-08',
                'status' => 'Active', 'church_service_id' => $serviceId,
            ]),
            'visitor'     => Visitor::create([
                'first_name' => 'Kojo', 'last_name' => 'Visitor', 'visit_date' => '2026-11-08', 'church_service_id' => $serviceId,
            ]),
        };
    }

    private function url(string $route, ?Model $record): string
    {
        return $record ? route($route, $record) : route($route);
    }

    public static function pages(): array
    {
        return [
            'finance create'  => ['finance.create', 'none'],
            'finance edit'    => ['finance.edit', 'transaction'],
            'pledges report'  => ['pledges.report', 'none'],
            'pledges create'  => ['pledges.create', 'none'],
            'pledges edit'    => ['pledges.edit', 'pledge'],
            'visitors create' => ['visitors.create', 'none'],
            'visitors edit'   => ['visitors.edit', 'visitor'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_dropdown_offers_only_the_picker_services(string $route, string $kind): void
    {
        ['today' => $today, 'future' => $future] = $this->seedServices();
        $record = $this->record($kind, $today);

        $offered = $this->actingAs($this->admin)->get($this->url($route, $record))
            ->assertOk()
            ->viewData('services');

        $this->assertSame($this->ids(ChurchService::forPicker()->get()), $this->ids($offered));
        $this->assertCount(20, $offered);
        $this->assertEmpty(array_intersect($this->ids($future), $this->ids($offered)));
    }

    public static function editPages(): array
    {
        return [
            'finance edit'  => ['finance.edit', 'transaction'],
            'pledges edit'  => ['pledges.edit', 'pledge'],
            'visitors edit' => ['visitors.edit', 'visitor'],
        ];
    }

    #[DataProvider('editPages')]
    public function test_an_edit_page_keeps_an_old_service_selected(string $route, string $kind): void
    {
        $this->seedServices();
        $old = $this->service('2026-05-01', '09:00', 'Old service');
        $record = $this->record($kind, $old);

        $response = $this->actingAs($this->admin)->get($this->url($route, $record))->assertOk();

        $this->assertContains($old->id, $this->ids($response->viewData('services')));
        $response->assertSee('<option value="' . $old->id . '" selected>', false);
    }

    public function test_the_pledge_report_keeps_an_old_filtered_service_selected(): void
    {
        $this->seedServices();
        $old = $this->service('2026-05-01', '09:00', 'Old service');

        $response = $this->actingAs($this->admin)->get(route('pledges.report', ['service' => $old->id]))->assertOk();

        $this->assertContains($old->id, $this->ids($response->viewData('services')));
        $response->assertSee('<option value="' . $old->id . '" selected>', false);
    }
}
