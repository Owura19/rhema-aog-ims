<?php

namespace Tests\Feature\Loans;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Loans\LoanService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Recording, editing and voiding counted transactions in Finance keeps the loan and the ledger in step. */
class LoanSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Loan $loan;
    /** @var array<string, Account> */
    private array $a = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-12-31 10:00:00'));   // reports default to "as of today"
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create()->assignRole('Super Admin');

        $acc = function (string $code, string $name, string $type, ?string $parent = null, bool $group = false) {
            return $this->a[$code] = Account::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_group' => $group, 'is_active' => true,
                'parent_id' => $parent ? $this->a[$parent]->id : null, 'sort_order' => (int) $code]);
        };
        $acc('1000', 'CURRENT ASSETS', 'Asset', null, true);
        $acc('1100', 'Cash on Hand', 'Asset', '1000');
        $acc('1536', 'C/U HARVEST', 'Asset', '1000');
        $acc('2000', 'LIABILITIES', 'Liability', null, true);
        $acc('2100', 'Loans Payable', 'Liability', '2000');
        $acc('3000', 'EQUITY', 'Equity', null, true);
        $acc('4300', 'Harvest', 'Income');
        $acc('4500', 'Project Offering', 'Income');
        $acc('4100', 'Tithe', 'Income');

        $service = app(LoanService::class);
        $this->loan = $service->create([
            'lender' => 'Test Credit Union', 'liability_account_id' => $this->a['2100']->id, 'opening_balance' => 1000,
            'opening_date' => '2026-11-20', 'opening_counter_account_id' => $service->openingEquityAccount()->id,
            'repayment_start_date' => '2026-11-21', 'counted_types' => Loan::DEFAULT_TYPES, 'repayment_source' => Loan::SOURCE_RECEIVED,
        ], $this->admin->id);
    }

    private function record(array $overrides = []): Transaction
    {
        $this->actingAs($this->admin)->post(route('finance.store'), $overrides + [
            'type' => 'Project Offering', 'account_id' => $this->a['4500']->id, 'cash_account_id' => $this->a['1100']->id,
            'category' => 'Income', 'amount' => '300.00', 'transaction_date' => '2026-11-22', 'payment_method' => 'Cash', 'status' => 'Confirmed',
        ])->assertSessionHasNoErrors();

        return Transaction::latest('id')->first();
    }

    private function edit(Transaction $tx, array $changes): void
    {
        $this->actingAs($this->admin)->put(route('finance.update', $tx), $changes + [
            'type' => $tx->type, 'category' => $tx->category, 'amount' => (string) $tx->amount,
            'transaction_date' => $tx->transaction_date->toDateString(), 'payment_method' => $tx->payment_method, 'status' => $tx->status,
        ])->assertSessionHasNoErrors();
    }

    private function net(string $code): float
    {
        return round((float) JournalLine::where('account_id', $this->a[$code]->id)->sum('debit') - (float) JournalLine::where('account_id', $this->a[$code]->id)->sum('credit'), 2);
    }

    private function repaymentEntries(): int
    {
        return JournalEntry::where('source_type', 'loan_repayment')->count();
    }

    public function test_recording_a_counted_transaction_repays_the_loan_from_the_cash_it_came_in_as(): void
    {
        $tx = $this->record();

        $r = LoanRepayment::sole();
        $this->assertSame([$tx->id, '300.00', $this->a['1100']->id, null], [(int) $r->transaction_id, $r->amount_applied, (int) $r->cash_account_id, $r->flag]);
        $this->assertSame(700.0, $this->loan->fresh()->balance());
        $this->assertSame(0.0, $this->net('1100'));          // received 300, paid 300 to the lender
        $this->assertSame(-700.0, $this->net('2100'));       // owed 700
        $this->assertSame(-300.0, $this->net('4500'));       // income stays 300
        $this->assertSame('300.00', (string) Transaction::where('category', 'Income')->sum('amount'));
        $this->assertSame(1, $this->repaymentEntries());
    }

    public function test_other_types_do_not_count(): void
    {
        $this->record(['type' => 'Tithe', 'account_id' => $this->a['4100']->id]);

        $this->assertSame(0, LoanRepayment::count());
        $this->assertSame(1000.0, $this->loan->fresh()->balance());
    }

    public function test_editing_the_amount_moves_the_repayment_and_both_entries(): void
    {
        $tx = $this->record();

        $this->edit($tx, ['amount' => '450.00']);

        $this->assertSame('450.00', LoanRepayment::sole()->amount_applied);
        $this->assertSame(550.0, $this->loan->fresh()->balance());
        $this->assertSame(0.0, $this->net('1100'));
        $this->assertSame(-450.0, $this->net('4500'));
        $this->assertSame(1, $this->repaymentEntries());
        $this->assertSame(1, JournalEntry::where('source_type', 'transaction')->count());
    }

    public function test_type_status_and_date_edits_take_it_off_the_loan(): void
    {
        foreach ([['type' => 'Tithe'], ['status' => 'Pending'], ['transaction_date' => '2026-11-20']] as $change) {
            $tx = $this->record();
            $this->edit($tx, $change);
            $this->assertSame(0, LoanRepayment::where('transaction_id', $tx->id)->count(), json_encode($change));
        }
        $this->assertSame(0, $this->repaymentEntries());
        $this->assertSame(1000.0, $this->loan->fresh()->balance());
    }

    public function test_voiding_removes_the_repayment_and_its_entry(): void
    {
        $tx = $this->record();

        $this->actingAs($this->admin)->delete(route('finance.destroy', $tx))->assertRedirect();

        $this->assertSame(0, LoanRepayment::count());
        $this->assertSame(0, $this->repaymentEntries());
        $this->assertSame(1000.0, $this->loan->fresh()->balance());
        $this->assertSame(0.0, $this->net('1100'));
    }

    public function test_never_counted_twice(): void
    {
        $tx = $this->record();
        $this->edit($tx, ['amount' => '300.00']);
        $this->edit($tx, ['description' => 'note']);
        app(LoanService::class)->rebuild($this->loan);

        $this->assertSame(1, LoanRepayment::count());
        $this->assertSame(1, $this->repaymentEntries());
        $this->assertSame(700.0, $this->loan->fresh()->balance());
    }

    public function test_the_trial_balance_and_balance_sheet_still_balance(): void
    {
        $this->record();
        $this->record(['amount' => '900.00', 'transaction_date' => '2026-11-29']);

        $this->assertSame(0.0, $this->loan->fresh()->balance());
        $this->assertSame('200.00', LoanRepayment::latest('id')->first()->surplus);
        $this->actingAs($this->admin)->get(route('finance.trial-balance'))->assertOk()->assertViewHas('balanced', true);
        $this->actingAs($this->admin)->get(route('finance.balance-sheet'))->assertOk()->assertViewHas('balanced', true);
        $this->assertSame(200.0, $this->net('1100'));        // only the surplus is left in cash
    }

    public function test_the_cash_summary_shows_the_repayments_as_cash_paid_out(): void
    {
        $this->record();

        $this->actingAs($this->admin)->get(route('finance.cash-summary', ['from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk()
            ->assertSee('Loan repayment — Test Credit Union')
            ->assertViewHas('totalReceipts', 300.0)
            ->assertViewHas('totalPayments', 300.0)
            ->assertViewHas('netCash', 0.0);
    }

    public function test_the_transaction_page_shows_the_repayment(): void
    {
        $tx = $this->record();

        $this->actingAs($this->admin)->get(route('finance.show', $tx))->assertOk()
            ->assertSee('Loan repayment')->assertSeeText('GH₵ 300.00 applied to the Test Credit Union loan')
            ->assertSee(route('loans.show', $this->loan), false);
    }

    public function test_a_counted_transaction_without_a_cash_account_is_counted_and_flagged(): void
    {
        $tx = $this->record(['cash_account_id' => null]);

        $this->assertSame(LoanRepayment::FLAG_NO_LEDGER, LoanRepayment::sole()->flag);
        $this->actingAs($this->admin)->get(route('finance.show', $tx))->assertSee('Not in the ledger');

        // Choosing the account on Edit posts both the transaction and its repayment
        $this->edit($tx, ['cash_account_id' => $this->a['1100']->id]);
        $this->assertNull(LoanRepayment::sole()->flag);
        $this->assertSame(1, $this->repaymentEntries());
        $this->assertSame(0.0, $this->net('1100'));
    }
}
