<?php

namespace Tests\Feature\Loans;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\LoanRepayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Loans\LoanService;
use App\Services\TransactionLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $loans;
    private User $user;
    /** @var array<string, Account> by code */
    private array $a = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));
        $this->loans = app(LoanService::class);
        $this->user  = User::factory()->create();

        $acc = function (string $code, string $name, string $type, ?string $parent = null, bool $group = false) {
            return $this->a[$code] = Account::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_group' => $group, 'is_active' => true,
                'parent_id' => $parent ? $this->a[$parent]->id : null, 'sort_order' => (int) $code]);
        };
        $acc('1000', 'CURRENT ASSETS', 'Asset', null, true);
        $acc('1100', 'Cash on Hand', 'Asset', '1000');
        $acc('1200', 'Bank Account', 'Asset', '1000');
        $acc('1536', 'C/U HARVEST', 'Asset', '1000');
        $acc('2000', 'LIABILITIES', 'Liability', null, true);
        $acc('2100', 'Loans Payable', 'Liability', '2000');
        $acc('3000', 'EQUITY', 'Equity', null, true);
        $acc('3100', 'Accumulated Fund', 'Equity', '3000');
        $acc('4300', 'Harvest', 'Income', null);
        $acc('4500', 'Project Offering', 'Income', null);
    }

    /** A recorded transaction with its own income entry (received into $cash). */
    private function tx(string $type, float $amount, string $date, array $attrs = [], ?string $cash = '1100'): Transaction
    {
        $tx = Transaction::create($attrs + [
            'type' => $type, 'category' => 'Income', 'amount' => $amount, 'transaction_date' => $date,
            'payment_method' => 'Cash', 'status' => 'Confirmed',
            'account_id' => $this->a[$type === 'Harvest' ? '4300' : '4500']->id,
        ]);
        if ($cash) {
            app(TransactionLedger::class)->repost($tx, $this->a[$cash]->id);
        }

        return $tx;
    }

    private function loan(float $opening = 1000, array $attrs = []): Loan
    {
        return $this->loans->create($attrs + [
            'lender' => 'Test Credit Union', 'liability_account_id' => $this->a['2100']->id, 'opening_balance' => $opening,
            'opening_date' => '2026-11-20', 'opening_counter_account_id' => $this->loans->openingEquityAccount()->id,
            'repayment_start_date' => '2026-11-21', 'counted_types' => Loan::DEFAULT_TYPES, 'repayment_source' => Loan::SOURCE_RECEIVED,
        ], $this->user->id);
    }

    /** debit/credit totals of an account across all entries */
    private function net(string $code): float
    {
        return round((float) JournalLine::where('account_id', $this->a[$code]->id ?? Account::where('code', $code)->value('id'))->sum('debit')
            - (float) JournalLine::where('account_id', $this->a[$code]->id ?? Account::where('code', $code)->value('id'))->sum('credit'), 2);
    }

    private function assertBooksBalance(): void
    {
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);
    }

    private function income(): string
    {
        return (string) Transaction::where('category', 'Income')->sum('amount');
    }

    // ── opening ────────────────────────────────────────────────────────

    public function test_the_opening_balance_is_owed_to_the_lender_against_opening_balance_equity(): void
    {
        $loan = $this->loan(50000);

        $equity = Account::where('code', '3200')->sole();
        $this->assertSame(['Opening Balance Equity', 'Equity', $this->a['3000']->id], [$equity->name, $equity->type, (int) $equity->parent_id]);
        $this->assertSame(-50000.0, $this->net('2100'));   // credit balance: owed
        $this->assertSame(50000.0, round((float) JournalLine::where('account_id', $equity->id)->sum('debit'), 2));
        $this->assertSame(50000.0, $loan->balance());
        $this->assertSame(50000.0, $this->loans->ledgerBalance($loan));
        $this->assertSame('loan_opening', JournalEntry::find($loan->opening_journal_entry_id)->source_type);
        $this->assertBooksBalance();
    }

    public function test_editing_the_opening_balance_re_posts_one_entry(): void
    {
        $loan = $this->loan(50000);

        $this->loans->update($loan, ['opening_balance' => 45000], $this->user->id);

        $this->assertSame(1, JournalEntry::where('source_type', 'loan_opening')->count());
        $this->assertSame(-45000.0, $this->net('2100'));
    }

    // ── repayments ─────────────────────────────────────────────────────

    public function test_counted_transactions_repay_the_loan_and_income_does_not_change(): void
    {
        $this->tx('Harvest', 999, '2026-11-20');                              // before the start date
        $this->tx('Offering', 999, '2026-11-22');                             // not a counted type
        $this->tx('Harvest', 999, '2026-11-22', ['status' => 'Pending']);     // not Confirmed
        $harvest = $this->tx('Harvest', 300, '2026-11-22');
        $project = $this->tx('Project Offering', 200, '2026-11-21');
        $income = $this->income();

        $loan = $this->loan(1000);

        $this->assertSame($income, $this->income());
        $this->assertEqualsCanonicalizing([$harvest->id, $project->id], $loan->repayments()->pluck('transaction_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(500.0, $loan->balance());
        $this->assertSame(500.0, $this->loans->ledgerBalance($loan));
        $entry = JournalEntry::where('source_type', 'loan_repayment')->where('source_id', LoanRepayment::where('transaction_id', $harvest->id)->value('id'))->sole();
        $this->assertSame('2026-11-22', $entry->entry_date->toDateString());
        $lines = $entry->lines->mapWithKeys(fn ($l) => [(int) $l->account_id => [(float) $l->debit, (float) $l->credit]])->all();
        $this->assertSame([$this->a['2100']->id => [300.0, 0.0], $this->a['1100']->id => [0.0, 300.0]], $lines);
        $this->assertBooksBalance();
    }

    public function test_the_last_payment_is_applied_in_part_and_later_ones_are_not_counted(): void
    {
        $loan = $this->loan(400);
        $first  = $this->tx('Harvest', 300, '2026-11-22');
        $second = $this->tx('Harvest', 300, '2026-11-29');
        $third  = $this->tx('Harvest', 300, '2026-12-06');
        foreach ([$first, $second, $third] as $t) {
            $this->loans->syncTransaction($t, $this->user->id);
        }

        $loan->refresh();
        $this->assertSame(['Paid off', '2026-11-29'], [$loan->status, $loan->paid_off_at->toDateString()]);
        $last = LoanRepayment::where('transaction_id', $second->id)->sole();
        $this->assertSame(['100.00', '200.00'], [$last->amount_applied, $last->surplus]);
        $this->assertSame(0, LoanRepayment::where('transaction_id', $third->id)->count());
        $this->assertSame(0.0, $loan->balance());
        $this->assertSame(200.0, $loan->surplus());
        $this->assertSame(0.0, $this->loans->ledgerBalance($loan));
        $this->assertSame(1, LoanEvent::where('action', 'paid_off')->count());
    }

    public function test_voiding_an_earlier_payment_reopens_and_moves_the_partial_payment(): void
    {
        $loan = $this->loan(400);
        $txs = [$this->tx('Harvest', 300, '2026-11-22'), $this->tx('Harvest', 300, '2026-11-29'), $this->tx('Harvest', 300, '2026-12-06')];
        $this->loans->rebuild($loan);

        $txs[0]->delete();   // voided
        $this->loans->syncTransaction($txs[0]);

        $loan->refresh();
        $this->assertSame(0, LoanRepayment::where('transaction_id', $txs[0]->id)->count());
        $this->assertSame('300.00', LoanRepayment::where('transaction_id', $txs[1]->id)->value('amount_applied'));
        $last = LoanRepayment::where('transaction_id', $txs[2]->id)->sole();
        $this->assertSame(['100.00', '200.00'], [$last->amount_applied, $last->surplus]);
        $this->assertSame(['Paid off', '2026-12-06'], [$loan->status, $loan->paid_off_at->toDateString()]);
        $this->assertSame(0.0, $this->loans->ledgerBalance($loan));
        $this->assertSame(0, JournalEntry::where('source_type', 'loan_repayment')->whereNotIn('source_id', LoanRepayment::pluck('id'))->count());
        $this->assertBooksBalance();
    }

    public function test_a_balance_back_above_zero_reopens_the_loan(): void
    {
        $loan = $this->loan(300);
        $tx = $this->tx('Harvest', 300, '2026-11-22');
        $this->loans->rebuild($loan);
        $this->assertSame('Paid off', $loan->fresh()->status);

        $tx->update(['amount' => 250]);
        $this->loans->syncTransaction($tx);

        $this->assertSame(['Active', null], [$loan->fresh()->status, $loan->fresh()->paid_off_at]);
        $this->assertSame(50.0, $loan->fresh()->balance());
        $this->assertSame(1, LoanEvent::where('action', 'reopened')->count());
    }

    public function test_status_type_and_date_changes_remove_the_repayment(): void
    {
        $loan = $this->loan(1000);
        $tx = $this->tx('Harvest', 100, '2026-11-22');
        $this->loans->syncTransaction($tx);

        foreach ([['status' => 'Pending'], ['type' => 'Offering'], ['transaction_date' => '2026-11-01']] as $change) {
            $tx->update($change);
            $this->loans->syncTransaction($tx);
            $this->assertSame(0, LoanRepayment::count(), json_encode($change));
            $this->assertSame(0, JournalEntry::where('source_type', 'loan_repayment')->count());
            $tx->update(['status' => 'Confirmed', 'type' => 'Harvest', 'transaction_date' => '2026-11-22']);
            $this->loans->syncTransaction($tx);
            $this->assertSame(1, LoanRepayment::count());
        }
        $this->assertSame(900.0, $loan->fresh()->balance());
    }

    public function test_repayments_can_be_paid_from_a_fixed_account(): void
    {
        $loan = $this->loan(1000, ['repayment_source' => Loan::SOURCE_FIXED, 'repayment_account_id' => $this->a['1536']->id]);
        $tx = $this->tx('Harvest', 300, '2026-11-22');
        $this->loans->syncTransaction($tx);

        $entry = JournalEntry::where('source_type', 'loan_repayment')->sole();
        $this->assertSame([$this->a['2100']->id, $this->a['1536']->id],
            [(int) $entry->lines->firstWhere('debit', '>', 0)->account_id, (int) $entry->lines->firstWhere('credit', '>', 0)->account_id]);
        $this->assertSame(300.0, $this->net('1100'));          // the cash it came in as is untouched here
        $this->assertSame(-300.0, $this->net('1536'));
    }

    public function test_a_counted_transaction_without_a_ledger_entry_is_counted_and_flagged(): void
    {
        $loan = $this->loan(1000);
        $tx = $this->tx('Harvest', 100, '2026-11-22', [], cash: null);
        $this->loans->syncTransaction($tx);

        $r = LoanRepayment::sole();
        $this->assertSame([LoanRepayment::FLAG_NO_LEDGER, null, '100.00'], [$r->flag, $r->journal_entry_id, $r->amount_applied]);
        $this->assertSame(900.0, $loan->balance());
        $this->assertSame(1000.0, $this->loans->ledgerBalance($loan));   // the page shows this difference

        // Once the transaction is posted, its repayment is posted too
        app(TransactionLedger::class)->repost($tx, $this->a['1100']->id);
        $this->loans->syncTransaction($tx);
        $this->assertNull(LoanRepayment::sole()->flag);
        $this->assertSame(900.0, $this->loans->ledgerBalance($loan));
    }

    public function test_rebuilding_again_changes_nothing(): void
    {
        $loan = $this->loan(400);
        $this->tx('Harvest', 300, '2026-11-22');
        $this->tx('Harvest', 300, '2026-11-29');
        $this->loans->rebuild($loan);
        $entries = JournalEntry::pluck('id')->all();

        $this->assertSame(['added' => 0, 'changed' => 0, 'removed' => 0], $this->loans->rebuild($loan));
        $this->assertSame($entries, JournalEntry::pluck('id')->all());
    }

    // ── one loan per type ──────────────────────────────────────────────

    public function test_two_active_loans_cannot_count_the_same_type(): void
    {
        $this->loan(1000);

        $this->expectException(ValidationException::class);
        $this->loan(500, ['counted_types' => ['Harvest'], 'liability_account_id' => Account::create(['code' => '2110', 'name' => 'Loan 2', 'type' => 'Liability', 'is_group' => false, 'is_active' => true, 'sort_order' => 2110])->id]);
    }

    public function test_a_transaction_counts_for_one_loan_only(): void
    {
        $first = $this->loan(100);
        $this->tx('Harvest', 100, '2026-11-22');
        $this->loans->rebuild($first);
        $this->assertSame('Paid off', $first->fresh()->status);

        $second = $this->loan(500, ['liability_account_id' => Account::create(['code' => '2110', 'name' => 'Loan 2', 'type' => 'Liability', 'is_group' => false, 'is_active' => true, 'sort_order' => 2110])->id]);
        $later = $this->tx('Harvest', 50, '2026-11-29');
        $this->loans->syncTransaction($later);

        $this->assertSame(1, LoanRepayment::where('loan_id', $first->id)->count());
        $this->assertSame([$later->id], $second->repayments()->pluck('transaction_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame(450.0, $second->fresh()->balance());
    }

    // ── adjustments ────────────────────────────────────────────────────

    public function test_corrections_and_manual_repayments_are_posted_and_audited(): void
    {
        $loan = $this->loan(1000);

        $this->loans->adjust($loan, 'correction', 250, '2026-11-25', 'Statement shows more owed', $this->loans->openingEquityAccount()->id, $this->user->id);
        $this->loans->adjust($loan, 'repayment', 100, '2026-11-26', 'Paid from the bank', $this->a['1200']->id, $this->user->id);

        $loan->refresh();
        $this->assertSame(1250.0, $loan->owedTotal());
        $this->assertSame(100.0, $loan->repaidTotal());
        $this->assertSame(1150.0, $loan->balance());
        $this->assertSame(1150.0, $this->loans->ledgerBalance($loan));
        $this->assertSame(-100.0, $this->net('1200'));
        $this->assertSame(['corrected', 'manual_repayment'], LoanEvent::whereIn('action', ['corrected', 'manual_repayment'])->orderBy('id')->pluck('action')->all());
        $this->assertSame('Paid from the bank', LoanEvent::where('action', 'manual_repayment')->value('note'));
        $this->assertBooksBalance();
    }

    public function test_existing_counted_transactions_are_listed_before_creating(): void
    {
        $this->tx('Harvest', 10, '2026-11-21');
        $this->tx('Harvest', 10, '2026-11-20');

        $this->assertCount(1, $this->loans->existingCounted(Loan::DEFAULT_TYPES, '2026-11-21'));
    }
}
