<?php

namespace Tests\Feature\Loans;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanAdjustment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Loans\LoanService;
use App\Services\TransactionLedger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Finance → Loans pages, the dashboard card and who may use them. */
class LoanPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $u = [];
    /** @var array<string, Account> */
    private array $a = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        foreach (['Super Admin', 'Finance Officer', 'Pastor', 'Usher'] as $role) {
            $this->u[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }

        $acc = function (string $code, string $name, string $type, ?string $parent = null, bool $group = false) {
            return $this->a[$code] = Account::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_group' => $group, 'is_active' => true,
                'parent_id' => $parent ? $this->a[$parent]->id : null, 'sort_order' => (int) $code]);
        };
        $acc('1000', 'CURRENT ASSETS', 'Asset', null, true);
        $acc('1100', 'Cash on Hand', 'Asset', '1000');
        $acc('1536', 'C/U HARVEST - 100100299', 'Asset', '1000');
        $acc('2000', 'LIABILITIES', 'Liability', null, true);
        $acc('2100', 'Loans Payable', 'Liability', '2000');
        $acc('3000', 'EQUITY', 'Equity', null, true);
        $acc('4500', 'Project Offering', 'Income');
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->u[$role]);
    }

    private function input(array $overrides = []): array
    {
        return $overrides + [
            'lender' => 'Test Credit Union', 'description' => 'Building loan', 'liability_account_id' => $this->a['2100']->id,
            'opening_balance' => '50000.00', 'opening_date' => '2026-11-20', 'opening_counter_account_id' => 'opening_equity',
            'repayment_start_date' => '2026-11-21', 'counted_types' => Loan::DEFAULT_TYPES, 'repayment_source' => 'received',
        ];
    }

    private function counted(float $amount = 300, string $date = '2026-11-21'): Transaction
    {
        $tx = Transaction::create(['type' => 'Project Offering', 'category' => 'Income', 'amount' => $amount, 'transaction_date' => $date,
            'payment_method' => 'Cash', 'status' => 'Confirmed', 'account_id' => $this->a['4500']->id]);
        app(TransactionLedger::class)->repost($tx, $this->a['1100']->id);

        return $tx;
    }

    private function makeLoan(array $overrides = []): Loan
    {
        $this->as('Finance Officer')->post(route('loans.store'), $this->input($overrides) + ['confirmed' => '1'])->assertSessionHasNoErrors();

        return Loan::latest('id')->first();
    }

    // ── access ────────────────────────────────────────────────────────

    public function test_view_finance_sees_loans_and_edit_finance_manages_them(): void
    {
        $loan = $this->makeLoan();

        foreach (Role::pluck('name') as $role) {
            $user = User::factory()->create()->assignRole($role);
            $view = $user->can('view finance');
            $edit = $user->can('edit finance');

            $this->actingAs($user)->get(route('loans.index'))->assertStatus($view ? 200 : 403);
            $this->actingAs($user)->get(route('loans.show', $loan))->assertStatus($view ? 200 : 403);
            $this->actingAs($user)->get(route('loans.create'))->assertStatus($edit ? 200 : 403);
            $this->actingAs($user)->get(route('loans.edit', $loan))->assertStatus($edit ? 200 : 403);
            if (! $edit) {
                $this->actingAs($user)->post(route('loans.adjust', $loan), ['kind' => 'owed_more', 'amount' => 1, 'adjustment_date' => '2026-11-20', 'reason' => 'nope nope', 'counter_account_id' => 'opening_equity'])->assertForbidden();
                $this->actingAs($user)->post(route('loans.recalculate', $loan))->assertForbidden();
            }
        }
        $this->assertSame(0, LoanAdjustment::count());
    }

    // ── create ────────────────────────────────────────────────────────

    public function test_the_form_starts_with_the_agreed_defaults(): void
    {
        $this->as('Finance Officer')->get(route('loans.create'))->assertOk()
            ->assertSee('<option value="' . $this->a['2100']->id . '" selected>2100 — Loans Payable</option>', false)
            ->assertSee('<option value="opening_equity" selected>3200 — Opening Balance Equity (recommended; created on save)</option>', false)
            ->assertSee('value="2026-11-21"', false)
            ->assertSee('value="Project Offering" checked', false)
            ->assertSee('value="Harvest" checked', false)
            ->assertSee('Choose a fixed account only if Finance records moving the money into it')
            ->assertSee('<option value="' . $this->a['1536']->id . '" selected>1536 — C/U HARVEST - 100100299</option>', false);
    }

    public function test_the_check_step_shows_the_entry_and_existing_payments_and_saves_nothing(): void
    {
        $this->counted(300, '2026-11-21');
        $this->counted(999, '2026-11-20');   // before the start date

        $this->as('Finance Officer')->post(route('loans.preview'), $this->input())->assertOk()
            ->assertSee('3200 — Opening Balance Equity (created now)')
            ->assertSee('2100 — Loans Payable')
            ->assertSee('GH₵ 50,000.00')
            ->assertSee('Payments that already count (1)')
            ->assertSee('Confirm and record the loan');

        $this->assertSame(0, Loan::count());
        $this->assertNull(Account::where('code', '3200')->first());
    }

    public function test_confirming_records_the_loan_and_counts_existing_payments(): void
    {
        $this->counted(300, '2026-11-21');

        $this->as('Finance Officer')->post(route('loans.store'), $this->input())->assertSessionHasErrors('confirmed');
        $loan = $this->makeLoan();

        $this->assertSame(['Test Credit Union', 49700.0, 1], [$loan->lender, $loan->balance(), $loan->repayments()->count()]);
        $this->assertSame('3200', $loan->counterAccount->code);
        $this->as('Finance Officer')->get(route('loans.show', $loan))->assertOk()
            ->assertSee('GH₵ 49,700.00')->assertSee('GH₵ 300.00')->assertSee('0.6%')
            ->assertSee('the account each payment was received into')
            ->assertSee('ledger matches');
    }

    public function test_validation(): void
    {
        $this->as('Finance Officer')->post(route('loans.preview'), $this->input(['repayment_source' => 'fixed', 'repayment_account_id' => null]))
            ->assertSessionHasErrors(['repayment_account_id' => 'Choose the account the repayments are paid from.']);
        $this->as('Finance Officer')->post(route('loans.preview'), $this->input(['counted_types' => []]))->assertSessionHasErrors('counted_types');
        $this->as('Finance Officer')->post(route('loans.preview'), $this->input(['counted_types' => ['Expense']]))->assertSessionHasErrors('counted_types.0');

        $this->makeLoan();
        $this->as('Finance Officer')->post(route('loans.preview'), $this->input())->assertSessionHasErrors('liability_account_id');
        $other = Account::create(['code' => '2110', 'name' => 'Loan 2', 'type' => 'Liability', 'is_group' => false, 'is_active' => true, 'sort_order' => 2110]);
        $this->as('Finance Officer')->post(route('loans.store'), $this->input(['liability_account_id' => $other->id, 'counted_types' => ['Harvest']]) + ['confirmed' => '1'])
            ->assertSessionHasErrors('counted_types');
        $this->assertSame(1, Loan::count());
    }

    public function test_a_fixed_paid_from_account_is_shown(): void
    {
        $loan = $this->makeLoan(['repayment_source' => 'fixed', 'repayment_account_id' => $this->a['1536']->id]);

        $this->as('Pastor')->get(route('loans.show', $loan))->assertOk()
            ->assertSee('1536 C/U HARVEST - 100100299 (Finance records moving the money into it)');
    }

    // ── edit, adjust, recalculate, export ─────────────────────────────

    public function test_editing_the_opening_balance(): void
    {
        $loan = $this->makeLoan();

        $this->as('Finance Officer')->put(route('loans.update', $loan), $this->input(['opening_balance' => '45000.00', 'opening_counter_account_id' => (string) $loan->opening_counter_account_id]))
            ->assertRedirect(route('loans.show', $loan))->assertSessionHas('success');

        $this->assertSame(45000.0, $loan->fresh()->balance());
        $this->assertSame(45000.0, app(LoanService::class)->ledgerBalance($loan));
    }

    public function test_adjustments_need_a_reason_and_are_listed(): void
    {
        $loan = $this->makeLoan();

        $this->as('Finance Officer')->post(route('loans.adjust', $loan), ['kind' => 'owed_less', 'amount' => '500', 'adjustment_date' => '2026-11-25', 'reason' => '', 'counter_account_id' => (string) $loan->opening_counter_account_id])
            ->assertSessionHasErrors(['reason' => 'Give the reason for this adjustment.']);
        $this->as('Finance Officer')->post(route('loans.adjust', $loan), ['kind' => 'owed_less', 'amount' => '500', 'adjustment_date' => '2026-11-25', 'reason' => 'Statement correction', 'counter_account_id' => (string) $loan->opening_counter_account_id])
            ->assertSessionHasNoErrors();

        $this->assertSame(49500.0, $loan->fresh()->balance());
        $this->as('Pastor')->get(route('loans.show', $loan))->assertSee('Correction: owed less')->assertSee('Statement correction')->assertSee('Finance Officer User');
    }

    public function test_recalculate_and_export(): void
    {
        $loan = $this->makeLoan();
        $this->counted(300, '2026-11-22');

        $this->as('Finance Officer')->post(route('loans.recalculate', $loan))->assertSessionHas('success', 'Recalculated: 0 added, 0 changed, 0 removed.');

        $response = $this->as('Pastor')->get(route('loans.export', $loan))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('loan-test-credit-union-2026-11-20.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    // ── dashboards ────────────────────────────────────────────────────

    public function test_the_dashboard_card_for_pastor_super_admin_and_finance_officer(): void
    {
        $loan = $this->makeLoan();

        foreach (['Pastor', 'Super Admin', 'Finance Officer'] as $role) {
            $this->as($role)->get(route('dashboard'))->assertOk()
                ->assertSee('Loan: Test Credit Union')->assertSee(route('loans.show', $loan), false);
        }
        $this->as('Usher')->get(route('dashboard'))->assertOk()->assertDontSee('Loan: Test Credit Union');

        $this->as('Super Admin')->get(route('dashboard'))->assertSee(route('loans.index'), false);   // sidebar
    }
}
