<?php

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Editing a transaction re-posts its own ledger entry (it used to keep the original one). */
class TransactionRepostTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Account $income;
    private Account $expense;
    private Account $cash;
    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create()->assignRole('Super Admin');

        $group         = Account::create(['code' => '1000', 'name' => 'CURRENT ASSETS', 'type' => 'Asset', 'is_group' => true, 'is_active' => true, 'sort_order' => 1000]);
        $this->cash    = Account::create(['code' => '1100', 'name' => 'Cash on Hand', 'type' => 'Asset', 'parent_id' => $group->id, 'is_group' => false, 'is_active' => true, 'sort_order' => 1110]);
        $this->bank    = Account::create(['code' => '1200', 'name' => 'Bank Account', 'type' => 'Asset', 'parent_id' => $group->id, 'is_group' => false, 'is_active' => true, 'sort_order' => 1120]);
        $this->income  = Account::create(['code' => '4500', 'name' => 'Project Offering', 'type' => 'Income', 'is_group' => false, 'is_active' => true, 'sort_order' => 4500]);
        $this->expense = Account::create(['code' => '5100', 'name' => 'General Expense', 'type' => 'Expense', 'is_group' => false, 'is_active' => true, 'sort_order' => 5100]);
    }

    private function record(array $overrides = []): Transaction
    {
        $this->actingAs($this->admin)->post(route('finance.store'), $overrides + [
            'type' => 'Project Offering', 'account_id' => $this->income->id, 'cash_account_id' => $this->cash->id, 'category' => 'Income',
            'amount' => '100.00', 'transaction_date' => '2026-11-15', 'payment_method' => 'Cash', 'status' => 'Confirmed',
        ])->assertSessionHasNoErrors();

        return Transaction::latest('id')->first();
    }

    private function edit(Transaction $tx, array $changes): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('finance.update', $tx), $changes + [
            'type' => $tx->type, 'category' => $tx->category, 'amount' => (string) $tx->amount,
            'transaction_date' => $tx->transaction_date->toDateString(), 'payment_method' => $tx->payment_method, 'status' => $tx->status,
        ]);
    }

    /** @return array<int, array{0: float, 1: float}> account id => [debit, credit] of the transaction's entry */
    private function lines(Transaction $tx): array
    {
        $entries = JournalEntry::where('source_type', 'transaction')->where('source_id', $tx->id)->get();
        $this->assertLessThanOrEqual(1, $entries->count(), 'never more than one entry per transaction');

        return JournalLine::whereIn('journal_entry_id', $entries->pluck('id'))->get()
            ->mapWithKeys(fn ($l) => [(int) $l->account_id => [(float) $l->debit, (float) $l->credit]])->all();
    }

    private function assertBooksBalance(): void
    {
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);
    }

    public function test_editing_the_amount_and_date_re_posts_the_entry(): void
    {
        $tx = $this->record();

        $this->edit($tx, ['amount' => '250.00', 'transaction_date' => '2026-11-18'])->assertSessionHasNoErrors();

        $this->assertSame([$this->cash->id => [250.0, 0.0], $this->income->id => [0.0, 250.0]], $this->lines($tx));
        $this->assertSame('2026-11-18', JournalEntry::where('source_id', $tx->id)->sole()->entry_date->toDateString());
        $this->assertBooksBalance();
    }

    public function test_it_keeps_the_cash_account_it_was_received_into(): void
    {
        $tx = $this->record(['cash_account_id' => $this->bank->id]);

        $this->edit($tx, ['amount' => '120.00'])->assertSessionHasNoErrors();

        $this->assertSame([$this->bank->id => [120.0, 0.0], $this->income->id => [0.0, 120.0]], $this->lines($tx));
    }

    public function test_the_edit_form_can_move_it_to_another_cash_account(): void
    {
        $tx = $this->record();

        $this->actingAs($this->admin)->get(route('finance.edit', $tx))->assertOk()
            ->assertSee('<option value="' . $this->cash->id . '" selected>1100 — Cash on Hand</option>', false);

        $this->edit($tx, ['cash_account_id' => $this->bank->id])->assertSessionHasNoErrors();

        $this->assertSame([$this->bank->id => [100.0, 0.0], $this->income->id => [0.0, 100.0]], $this->lines($tx));
    }

    public function test_income_to_expense_flips_the_entry(): void
    {
        $tx = $this->record(['type' => 'Expense', 'account_id' => $this->expense->id, 'category' => 'Expense']);
        $this->assertSame([$this->expense->id => [100.0, 0.0], $this->cash->id => [0.0, 100.0]], $this->lines($tx));

        $this->edit($tx, ['amount' => '40.00'])->assertSessionHasNoErrors();

        $this->assertSame([$this->expense->id => [40.0, 0.0], $this->cash->id => [0.0, 40.0]], $this->lines($tx));
        $this->assertBooksBalance();
    }

    public function test_not_confirmed_has_no_entry_and_confirming_posts_it_again(): void
    {
        $tx = $this->record();

        $this->edit($tx, ['status' => 'Pending'])->assertSessionHasNoErrors();
        $this->assertSame([], $this->lines($tx));

        // The entry is gone, so the cash account comes from the form (prefilled in real use)
        $this->edit($tx->fresh(), ['status' => 'Confirmed', 'cash_account_id' => $this->cash->id])->assertSessionHasNoErrors();
        $this->assertSame([$this->cash->id => [100.0, 0.0], $this->income->id => [0.0, 100.0]], $this->lines($tx));
    }

    public function test_a_transaction_never_posted_can_be_posted_from_the_edit_form(): void
    {
        $tx = $this->record(['cash_account_id' => null]);
        $this->assertSame([], $this->lines($tx));

        $this->actingAs($this->admin)->get(route('finance.edit', $tx))->assertSee('Not in the ledger yet: choose to post it');
        $this->edit($tx, ['cash_account_id' => $this->cash->id])->assertSessionHasNoErrors();

        $this->assertSame([$this->cash->id => [100.0, 0.0], $this->income->id => [0.0, 100.0]], $this->lines($tx));
    }

    public function test_journal_references_are_never_reused(): void
    {
        $a = $this->record();
        $this->record(['amount' => '5.00']);
        $this->edit($a, ['amount' => '7.00']);   // removes and re-posts

        $refs = JournalEntry::pluck('reference');
        $this->assertSame($refs->count(), $refs->unique()->count());
    }

    public function test_editing_a_pledge_payment_re_posts_its_transaction(): void
    {
        $pledge = Pledge::create(['pledger_name' => 'Test Pledger', 'pledge_purpose_id' => PledgePurpose::value('id'),
            'amount_pledged' => 500, 'date_pledged' => '2026-11-01', 'status' => 'Active']);
        $this->cash->update(['code' => '1100']);
        $this->actingAs($this->admin)->post(route('pledges.payments.store', $pledge),
            ['amount' => '60.00', 'payment_date' => '2026-11-10', 'payment_method' => 'Cash'])->assertSessionHasNoErrors();
        $payment = PledgePayment::sole();
        $tx = $payment->transaction;
        $tx->update(['account_id' => $this->income->id]);
        app(\App\Services\TransactionLedger::class)->repost($tx, $this->cash->id);   // as recorded with an account

        $this->actingAs($this->admin)->patch(route('pledges.payments.update', [$pledge, $payment]),
            ['amount' => '75.00', 'payment_date' => '2026-11-12', 'payment_method' => 'Cash'])->assertSessionHasNoErrors();

        $this->assertSame('75.00', $tx->fresh()->amount);
        $this->assertSame([$this->cash->id => [75.0, 0.0], $this->income->id => [0.0, 75.0]], $this->lines($tx));
        $this->assertBooksBalance();
    }

    public function test_voiding_still_removes_the_entry(): void
    {
        $tx = $this->record();

        $this->actingAs($this->admin)->delete(route('finance.destroy', $tx))->assertRedirect();

        $this->assertSame([], $this->lines($tx));
    }
}
