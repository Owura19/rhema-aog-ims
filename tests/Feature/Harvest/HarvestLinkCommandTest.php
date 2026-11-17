<?php

namespace Tests\Feature\Harvest;

use App\Models\Account;
use App\Models\Harvest;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** harvest:link-transactions — the one-time repair of Finance "Harvest" transactions. */
class HarvestLinkCommandTest extends TestCase
{
    use RefreshDatabase;

    private Harvest $harvest;
    private Account $income;
    private Account $cash;
    private int $purpose;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        Storage::fake('local');

        $this->income  = Account::create(['code' => '4300', 'name' => 'Harvest Income', 'type' => 'Income', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);
        $this->cash    = Account::create(['code' => '1100', 'name' => 'Cash on Hand', 'type' => 'Asset', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);
        $this->purpose = PledgePurpose::where('name', 'Harvest')->value('id');

        $this->harvest = Harvest::create(['name' => 'Year End Harvest', 'year' => 2026, 'target_amount' => 10000, 'pledge_opens' => '2026-08-01', 'harvest_date' => '2026-12-12', 'status' => 'Active']);
        // A smaller campaign whose dates sit inside the big one (like Men's Ministry Day)
        Harvest::create(['name' => 'Ministry Day', 'year' => 2026, 'target_amount' => 500, 'pledge_opens' => '2026-08-10', 'harvest_date' => '2026-08-20', 'status' => 'Active']);
    }

    private function member(string $first): Member
    {
        return Member::create(['first_name' => $first, 'last_name' => 'Zed']);
    }

    private function pledge(Member $member, float $amount, array $extra = []): Pledge
    {
        return Pledge::create($extra + [
            'member_id' => $member->id, 'pledge_purpose_id' => $this->purpose, 'harvest_id' => $this->harvest->id,
            'amount_pledged' => $amount, 'date_pledged' => '2026-08-02', 'status' => 'Active',
        ]);
    }

    /** A Finance "Harvest" transaction with its ledger entry, as Record Transaction made them. */
    private function harvestTx(?Member $member, float $amount, string $date = '2026-10-04', array $extra = []): Transaction
    {
        $tx = Transaction::create($extra + [
            'type' => 'Harvest', 'account_id' => $this->income->id, 'category' => 'Income', 'amount' => $amount,
            'member_id' => $member?->id, 'transaction_date' => $date, 'payment_method' => 'Cash', 'status' => 'Confirmed',
        ]);
        (new LedgerService())->postIncome($amount, $this->income->id, $this->cash->id, $date, 'Harvest', 'transaction', $tx->id);

        return $tx;
    }

    private function runCommand(bool $apply = false): array
    {
        $code = Artisan::call('harvest:link-transactions', ['--harvest' => $this->harvest->id] + ($apply ? ['--apply' => true] : []));

        return [$code, Artisan::output()];
    }

    private function state(): array
    {
        return [
            'pledges'       => Pledge::withTrashed()->count(),
            'payments'      => PledgePayment::withTrashed()->count(),
            'transactions'  => Transaction::withTrashed()->count(),
            'income'        => (string) Transaction::where('category', 'Income')->sum('amount'),
            'journals'      => JournalEntry::count(),
            'journal_lines' => JournalLine::count(),
            'pledged'       => (string) Pledge::sum('amount_pledged'),
        ];
    }

    private function csv(string $suffix): array
    {
        $files = collect(Storage::disk('local')->files('harvest-link'))->filter(fn ($f) => str_ends_with($f, "-{$suffix}.csv"))->values();
        $this->assertCount(1, $files, "one {$suffix} CSV");
        $this->assertMatchesRegularExpression('#^harvest-link/' . $this->harvest->id . '-\d{8}-\d{6}-' . $suffix . '\.csv$#', $files[0]);

        $lines = array_map('str_getcsv', array_filter(explode("\n", Storage::disk('local')->get($files[0]))));

        return collect(array_slice($lines, 1))->keyBy(0)->all();   // by transaction reference
    }

    /** The cases the command must sort, built once per test that needs them. */
    private function scenario(): array
    {
        $single = $this->member('Single');
        $p = $this->pledge($single, 500);
        $s = [
            'single'   => $this->harvestTx($single, 200),
            'single2'  => $this->harvestTx($single, 100, '2026-11-01'),
            'pledge'   => $p,
            'giver'    => $giverMember = $this->member('Giver'),
            'giver1'   => $this->harvestTx($giverMember, 50),
            'giver2'   => $this->harvestTx($giverMember, 25, '2026-10-11'),
            'walkin'   => $this->harvestTx(null, 40, '2026-10-05', ['payer_name' => 'Walk In']),
            'nopayer'  => $this->harvestTx(null, 10, '2026-10-06'),
            'overlap'  => $this->harvestTx($single, 60, '2026-08-15'),
        ];

        $multi = $this->member('Multi');
        $this->pledge($multi, 100);
        $this->pledge($multi, 100);
        $s['multi'] = $this->harvestTx($multi, 30);

        $over = $this->member('Over');
        $s['overPledge'] = $this->pledge($over, 100);
        $s['over1'] = $this->harvestTx($over, 80);
        $s['over2'] = $this->harvestTx($over, 40, '2026-10-20');

        // Not candidates: pending, voided, outside the period, other types, already linked
        $this->harvestTx($single, 5, '2026-10-04', ['status' => 'Pending']);
        $this->harvestTx($single, 5)->delete();
        $this->harvestTx($single, 5, '2026-12-20');
        $this->harvestTx($single, 5, '2026-10-04', ['type' => 'Offering']);
        $linked = $this->harvestTx($single, 5);
        PledgePayment::create(['pledge_id' => $p->id, 'amount' => 5, 'payment_date' => '2026-10-04', 'payment_method' => 'Cash', 'transaction_id' => $linked->id]);

        return $s;
    }

    public function test_it_needs_an_existing_harvest(): void
    {
        $this->assertSame(1, Artisan::call('harvest:link-transactions', ['--harvest' => 999]));
        $this->assertSame(1, Artisan::call('harvest:link-transactions'));
    }

    public function test_a_dry_run_changes_nothing_and_writes_a_csv(): void
    {
        $s = $this->scenario();
        $before = $this->state();

        [$code, $output] = $this->runCommand();

        $this->assertSame(0, $code);
        $this->assertSame($before, $this->state());
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Transactions considered: 10', $output);
        $this->assertStringContainsString("would be linked to the payer's pledge: 2 (pledges: 1)", $output);
        $this->assertStringContainsString('would be recorded as harvest givers:   3 (givers: 2)', $output);
        $this->assertStringContainsString('skipped for review:              5', $output);

        $rows = $this->csv('dry-run');
        $this->assertSame('(new giver pledge)', $rows[$s['giver1']->reference][5]);
        $this->assertSame('Giver Zed', $rows[$s['giver1']->reference][2]);    // names belong in the CSV...
    }

    public function test_the_console_shows_no_names_or_amounts(): void
    {
        $this->scenario();

        foreach ([false, true] as $apply) {
            [, $output] = $this->runCommand($apply);
            foreach (['Single', 'Giver', 'Walk In', 'Multi', 'Over', 'Zed'] as $name) {
                $this->assertStringNotContainsString($name, $output);    // ...never on the console
            }
            $this->assertDoesNotMatchRegularExpression('/\d+\.\d{2}\b/', $output);
        }
    }

    public function test_apply_links_makes_givers_and_skips_the_rest(): void
    {
        $s = $this->scenario();

        [$code, $output] = $this->runCommand(apply: true);
        $this->assertSame(0, $code, $output);

        // linked: both of Single's in-period transactions, onto the one pledge
        foreach (['single', 'single2'] as $key) {
            $payment = PledgePayment::where('transaction_id', $s[$key]->id)->sole();
            $this->assertSame($s['pledge']->id, (int) $payment->pledge_id);
            $this->assertSame("Linked from Finance transaction {$s[$key]->reference}", $payment->notes);
            $this->assertSame($s[$key]->amount, $payment->amount);
        }
        $this->assertSame(305.0, $s['pledge']->fresh()->total_paid);

        // giver: one giver pledge for the member, pledged = the sum; one for the walk-in name
        $giver = Pledge::where('member_id', $s['giver']->id)->sole();
        $this->assertTrue($giver->isGiver());
        $this->assertSame('75.00', $giver->amount_pledged);
        $this->assertSame('Fulfilled', $giver->status);
        $this->assertSame(2, $giver->payments()->count());
        $walkIn = Pledge::whereNull('member_id')->where('pledger_name', 'Walk In')->sole();
        $this->assertSame('40.00', $walkIn->amount_pledged);

        // skipped: overlap, no payer, multiple pledges, overpay (both of that pledge's transactions)
        foreach (['overlap', 'nopayer', 'multi', 'over1', 'over2'] as $key) {
            $this->assertSame(0, PledgePayment::where('transaction_id', $s[$key]->id)->count(), "{$key} stays unlinked");
        }
        $this->assertSame(0.0, $s['overPledge']->fresh()->total_paid);

        $rows = $this->csv('applied');
        $this->assertSame(['linked', $s['pledge']->reference], [$rows[$s['single']->reference][4], $rows[$s['single']->reference][5]]);
        $this->assertSame(['giver', $giver->reference], [$rows[$s['giver2']->reference][4], $rows[$s['giver2']->reference][5]]);
        $this->assertStringStartsWith('date is also inside another harvest', $rows[$s['overlap']->reference][6]);
        $this->assertSame('no member and no payer name', $rows[$s['nopayer']->reference][6]);
        $this->assertSame('more than one pledge on this harvest', $rows[$s['multi']->reference][6]);
        $this->assertSame('would take the pledge over its pledged amount', $rows[$s['over2']->reference][6]);
    }

    public function test_apply_never_touches_finance_totals_or_the_journal(): void
    {
        $this->scenario();
        $before = $this->state();

        $this->runCommand(apply: true);

        $after = $this->state();
        foreach (['transactions', 'income', 'journals', 'journal_lines'] as $key) {
            $this->assertSame($before[$key], $after[$key], $key);
        }
        $this->assertSame(0, Transaction::where('type', 'Pledge')->count());
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->scenario();
        $this->runCommand(apply: true);
        $afterFirst = $this->state();

        [$code, $output] = $this->runCommand(apply: true);

        $this->assertSame(0, $code);
        $this->assertSame($afterFirst, $this->state());
        $this->assertStringContainsString("linked to the payer's pledge: 0", $output);
        $this->assertStringContainsString('recorded as harvest givers:   0', $output);
    }

    public function test_it_rolls_everything_back_if_finance_totals_would_move(): void
    {
        $this->scenario();
        $before = $this->state();

        // Simulate a bug that posts money while linking: the command must refuse and undo it all.
        PledgePayment::created(function (PledgePayment $payment) {
            Transaction::create(['type' => 'Other', 'category' => 'Income', 'amount' => 1, 'transaction_date' => '2026-10-04', 'payment_method' => 'Cash', 'status' => 'Confirmed']);
        });

        [$code, $output] = $this->runCommand(apply: true);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Stopped, nothing was changed', $output);
        $this->assertSame($before, $this->state());
        $this->assertSame([], Storage::disk('local')->files('harvest-link'));
    }

    public function test_it_lists_harvest_pledges_that_have_no_harvest(): void
    {
        $loose = Pledge::create(['member_id' => $this->member('Loose')->id, 'pledge_purpose_id' => $this->purpose, 'amount_pledged' => 10, 'date_pledged' => '2026-08-02', 'status' => 'Active']);

        [, $output] = $this->runCommand();

        $this->assertStringContainsString('Harvest-purpose pledges with no harvest set (fix by hand): 1', $output);
        $this->assertStringContainsString($loose->reference, $output);
    }
}
