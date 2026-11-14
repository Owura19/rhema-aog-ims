<?php

namespace Tests\Feature\DayBorn;

use App\Models\Account;
use App\Models\DayBornOfferingChange;
use App\Models\DayBornWeek;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The day-born offering pages. Today is Monday 2026-11-16; the last Sunday was 2026-11-15. */
class DayBornWebTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['Super Admin', 'Finance Officer', 'Office Staff', 'Usher', 'Pastor', 'HOD'];

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (self::ROLES as $role) {
            $this->users[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'service_date' => '2026-11-15',
            'amounts'      => [0 => '120.00', 1 => '80.50', 2 => '0', 3 => '45', 4 => '60.25', 5 => '150', 6 => '99.99'],
        ];
    }

    private function week(array $overrides = []): DayBornWeek
    {
        $this->as('Usher')->post(route('day-born.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return DayBornWeek::where('service_date', $overrides['service_date'] ?? '2026-11-15')->firstOrFail();
    }

    /** @return array{0: Account, 1: Account} income account, cash account */
    private function accounts(): array
    {
        return [
            Account::create(['code' => '4100', 'name' => 'General Offering', 'type' => 'Income', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]),
            Account::create(['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'Asset', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]),
        ];
    }

    // ── access ─────────────────────────────────────────────────────────

    public static function routes(): array
    {
        return [
            'index'     => ['GET', 'day-born.index', false, ['Super Admin', 'Finance Officer', 'Office Staff', 'Usher', 'Pastor']],
            'show'      => ['GET', 'day-born.show', true, ['Super Admin', 'Finance Officer', 'Office Staff', 'Usher', 'Pastor']],
            'create'    => ['GET', 'day-born.create', false, ['Super Admin', 'Finance Officer', 'Office Staff', 'Usher']],
            'store'     => ['POST', 'day-born.store', false, ['Super Admin', 'Finance Officer', 'Office Staff', 'Usher']],
            'edit'      => ['GET', 'day-born.edit', true, ['Super Admin', 'Finance Officer', 'Office Staff', 'Usher']],
            'update'    => ['PUT', 'day-born.update', true, ['Super Admin', 'Finance Officer', 'Office Staff', 'Usher']],
            'destroy'   => ['DELETE', 'day-born.destroy', true, ['Super Admin', 'Finance Officer', 'Office Staff', 'Usher']],
            'publish'   => ['POST', 'day-born.publish', true, ['Super Admin', 'Finance Officer']],
            'unpublish' => ['POST', 'day-born.unpublish', true, ['Super Admin', 'Finance Officer']],
        ];
    }

    #[DataProvider('routes')]
    public function test_route_access_per_role(string $method, string $route, bool $needsWeek, array $allowed): void
    {
        foreach (self::ROLES as $i => $role) {
            $date = Carbon::parse('2026-01-04')->addWeeks($i)->toDateString();   // a fresh Sunday per role
            $week = $needsWeek ? $this->week(['service_date' => $date]) : null;
            $data = in_array($method, ['POST', 'PUT'], true) ? $this->payload(['service_date' => $method === 'POST' && ! $week ? Carbon::parse('2026-06-07')->addWeeks($i)->toDateString() : $date]) : [];

            $response = $this->as($role)->call($method, $week ? route($route, $week) : route($route), $data);

            if (in_array($role, $allowed, true)) {
                $this->assertContains($response->getStatusCode(), [200, 302], "{$role} should be allowed {$route}");
            } else {
                $this->assertSame(403, $response->getStatusCode(), "{$role} should be refused {$route}");
            }
        }
    }

    public function test_the_sidebar_link(): void
    {
        $this->as('Usher')->get(route('dashboard'))->assertSee(route('day-born.index'));
        $this->as('Finance Officer')->get(route('dashboard'))->assertSee(route('day-born.index'));
        $this->as('HOD')->get(route('dashboard'))->assertDontSee(route('day-born.index'));
    }

    // ── entry ──────────────────────────────────────────────────────────

    public function test_entering_a_week_saves_a_draft_and_logs_each_group(): void
    {
        $this->as('Usher')->post(route('day-born.store'), $this->payload(['notes' => ' Counted by the ushers '] ))
            ->assertRedirect(route('day-born.show', '2026-11-15'));

        $week = DayBornWeek::with('offerings')->sole();
        $this->assertSame(DayBornWeek::STATUS_DRAFT, $week->status);
        $this->assertSame('Counted by the ushers', $week->notes);
        $this->assertSame($this->users['Usher']->id, $week->recorded_by);
        $this->assertSame('555.74', $week->total());
        $this->assertSame(7, DayBornOfferingChange::where('action', 'created')->count());
        $this->assertNull($week->transaction_id);
        $this->assertSame(0, Transaction::count());   // a separate tally
    }

    public function test_the_entry_page_and_an_existing_date_redirect(): void
    {
        $this->as('Usher')->get(route('day-born.create'))->assertOk()
            ->assertSee('value="2026-11-15"', false)   // defaults to the last Sunday
            ->assertSee('Kwasi / Akosua')->assertSee('Kofi / Afua')->assertSee('Kwame / Amma');

        $week = $this->week();
        $this->as('Usher')->get(route('day-born.create', ['date' => '2026-11-15']))->assertRedirect(route('day-born.edit', $week));
    }

    public function test_validation(): void
    {
        $this->as('Usher')->post(route('day-born.store'), ['service_date' => '2026-11-15'])->assertSessionHasErrors(['amounts']);
        $this->as('Usher')->post(route('day-born.store'), $this->payload(['amounts' => [0 => '1', 1 => '1', 2 => '1', 3 => '1', 4 => '1', 5 => '1']]))
            ->assertSessionHasErrors(['amounts.6' => 'Enter an amount for Saturday (0 if none).']);
        $this->as('Usher')->post(route('day-born.store'), $this->payload(['amounts' => [0 => '-5', 1 => '1', 2 => '1', 3 => '1', 4 => '1', 5 => '1.234', 6 => 'ten']]))
            ->assertSessionHasErrors(['amounts.0', 'amounts.5', 'amounts.6']);
        $this->as('Usher')->post(route('day-born.store'), $this->payload(['service_date' => '2026-11-18']))   // a Wednesday
            ->assertSessionHasErrors(['service_date' => 'Pick a Sunday, or tick "Special service" for another day.']);
        $this->assertSame(0, DayBornWeek::count());

        $this->as('Usher')->post(route('day-born.store'), $this->payload(['service_date' => '2026-11-18', 'special_service' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue(DayBornWeek::sole()->special_service);

        $this->week();
        $this->as('Usher')->post(route('day-born.store'), $this->payload())->assertSessionHasErrors(['service_date' => 'A week for this date already exists.']);
    }

    public function test_editing_a_draft_logs_only_changed_groups(): void
    {
        $week = $this->week();

        $this->as('Office Staff')->put(route('day-born.update', $week), $this->payload(['amounts' => [0 => '120', 1 => '80.50', 2 => '10', 3 => '45', 4 => '60.25', 5 => '150', 6 => '99.99']]))
            ->assertSessionHasNoErrors();

        $change = DayBornOfferingChange::where('action', 'updated')->sole();
        $this->assertSame([2, '0.00', '10.00', $this->users['Office Staff']->id], [$change->weekday, $change->old_amount, $change->new_amount, $change->changed_by]);
    }

    // ── publish, edit after publishing, unpublish ──────────────────────

    public function test_publishing_without_finance_keeps_it_a_separate_tally(): void
    {
        $week = $this->week();

        $this->as('Finance Officer')->post(route('day-born.publish', $week))->assertSessionHas('success');

        $week->refresh();
        $this->assertTrue($week->isPublished());
        $this->assertSame($this->users['Finance Officer']->id, $week->published_by);
        $this->assertNull($week->transaction_id);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(1, DayBornOfferingChange::where('action', 'published')->count());
    }

    public function test_only_finance_can_change_a_published_week_and_must_give_a_reason(): void
    {
        $week = $this->week();
        $this->as('Finance Officer')->post(route('day-born.publish', $week));

        $this->as('Usher')->get(route('day-born.edit', $week))->assertForbidden();
        $this->as('Usher')->put(route('day-born.update', $week), $this->payload())->assertForbidden();
        $this->as('Usher')->delete(route('day-born.destroy', $week))->assertSessionHas('error');

        $changed = $this->payload(['amounts' => [0 => '130', 1 => '80.50', 2 => '0', 3 => '45', 4 => '60.25', 5 => '150', 6 => '99.99']]);
        $this->as('Finance Officer')->put(route('day-born.update', $week), $changed)->assertSessionHasErrors(['reason' => 'Say why a published week is being changed.']);
        $this->as('Finance Officer')->put(route('day-born.update', $week), $changed + ['reason' => 'Recount'])->assertSessionHasNoErrors();

        $change = DayBornOfferingChange::where('action', 'updated')->sole();
        $this->assertSame([0, '120.00', '130.00', 'Recount'], [$change->weekday, $change->old_amount, $change->new_amount, $change->reason]);
        $this->assertTrue($week->fresh()->isPublished());
    }

    public function test_post_to_finance_creates_one_linked_offering_kept_in_step(): void
    {
        [$income, $cash] = $this->accounts();
        $week = $this->week();

        $this->as('Finance Officer')->post(route('day-born.publish', $week), ['post_to_finance' => '1'])
            ->assertSessionHasErrors(['finance_account_id', 'cash_account_id']);
        $this->assertFalse($week->fresh()->isPublished());

        $this->as('Finance Officer')->post(route('day-born.publish', $week), ['post_to_finance' => '1', 'finance_account_id' => $income->id, 'cash_account_id' => $cash->id])
            ->assertSessionHas('success');

        $transaction = Transaction::sole();
        $this->assertSame(['Offering', 'Day-born Offering', 'Income', '555.74', '2026-11-15', 'Confirmed', null], [
            $transaction->type, $transaction->subcategory, $transaction->category, $transaction->amount, $transaction->transaction_date->toDateString(), $transaction->status, $transaction->member_id,
        ]);
        $this->assertSame($transaction->id, $week->fresh()->transaction_id);
        $this->assertSame(1, JournalEntry::where('source_type', 'transaction')->where('source_id', $transaction->id)->count());

        // An edit moves the transaction and its journal with it: still one of each.
        $this->as('Super Admin')->put(route('day-born.update', $week), $this->payload([
            'amounts' => [0 => '200', 1 => '80.50', 2 => '0', 3 => '45', 4 => '60.25', 5 => '150', 6 => '99.99'],
            'reason'  => 'Late count',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, Transaction::count());
        $this->assertSame('635.74', $transaction->fresh()->amount);
        $entries = JournalEntry::with('lines')->where('source_type', 'transaction')->where('source_id', $transaction->id)->get();
        $this->assertCount(1, $entries);
        $this->assertSame([635.74, 635.74], [(float) $entries[0]->lines->sum('debit'), (float) $entries[0]->lines->sum('credit')]);
        $this->assertSame(1, DayBornOfferingChange::where('action', 'finance_updated')->count());

        // Unpublishing voids it; publishing again without the tick doesn't post again.
        $this->as('Finance Officer')->post(route('day-born.unpublish', $week), ['reason' => 'Wrong week'])->assertSessionHas('success');
        $this->assertSoftDeleted($transaction);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, JournalEntry::where('source_type', 'transaction')->where('source_id', $transaction->id)->count());
        $this->assertNull($week->fresh()->transaction_id);

        $this->as('Finance Officer')->post(route('day-born.publish', $week))->assertSessionHas('success');
        $this->assertSame(0, Transaction::count());
    }

    public function test_a_zero_week_cannot_be_posted_to_finance(): void
    {
        [$income, $cash] = $this->accounts();
        $week = $this->week(['amounts' => array_fill(0, 7, '0')]);

        $this->as('Finance Officer')->post(route('day-born.publish', $week), ['post_to_finance' => '1', 'finance_account_id' => $income->id, 'cash_account_id' => $cash->id])
            ->assertSessionHas('error', 'A week with a total of 0.00 cannot be posted to finance.');

        $this->assertFalse($week->fresh()->isPublished());   // rolled back
        $this->assertSame(0, Transaction::count());
    }

    public function test_deleting_a_draft_keeps_its_history(): void
    {
        $week = $this->week();

        $this->as('Usher')->delete(route('day-born.destroy', $week))->assertRedirect(route('day-born.index'));

        $this->assertSame(0, DayBornWeek::count());
        $deleted = DayBornOfferingChange::where('action', 'deleted')->sole();
        $this->assertNull($deleted->week_id);
        $this->assertSame('2026-11-15', $deleted->service_date->toDateString());
        $this->assertSame(7, DayBornOfferingChange::where('action', 'created')->count());
    }

    public function test_the_results_page_ranks_with_ties(): void
    {
        $week = $this->week(['amounts' => [0 => '50', 1 => '20', 2 => '50', 3 => '0', 4 => '0', 5 => '70', 6 => '10']]);

        $this->as('Pastor')->get(route('day-born.show', $week))->assertOk()
            ->assertSeeInOrder(['1st', 'Friday', 'Kofi/Afua', '70.00', '2nd', 'Sunday', '50.00', '2nd', 'Tuesday', '50.00', '4th', 'Monday', '20.00'])
            ->assertSee('200.00')
            ->assertDontSee('Publish</button>', false);   // the Pastor can view, not publish

        $this->as('Pastor')->get(route('day-born.index'))->assertOk()->assertSee('Friday (Kofi/Afua)');
    }
}
