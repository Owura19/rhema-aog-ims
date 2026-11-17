<?php

namespace Tests\Feature\Harvest;

use App\Models\Account;
use App\Models\Harvest;
use App\Models\JournalEntry;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Harvest money recorded in Finance lands on the payer's harvest pledge. */
class HarvestLinkingWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Harvest $harvest;
    private Harvest $other;
    private Account $income;
    private Account $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin  = User::factory()->create()->assignRole('Super Admin');
        $this->income = Account::create(['code' => '4300', 'name' => 'Harvest Income', 'type' => 'Income', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);
        $this->cash   = Account::create(['code' => '1100', 'name' => 'Cash on Hand', 'type' => 'Asset', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->harvest = Harvest::create(['name' => 'Year End Harvest', 'year' => 2026, 'target_amount' => 10000, 'pledge_opens' => '2026-08-01', 'harvest_date' => '2026-12-12', 'status' => 'Active']);
        $this->other   = Harvest::create(['name' => 'Second Harvest', 'year' => 2026, 'target_amount' => 500, 'status' => 'Active']);
    }

    private function member(string $last = 'Member'): Member
    {
        return Member::create(['first_name' => 'Test', 'last_name' => $last]);
    }

    private function pledge(?Member $member, float $amount, array $extra = []): Pledge
    {
        return Pledge::create($extra + [
            'member_id'         => $member?->id,
            'pledge_purpose_id' => PledgePurpose::where('name', 'Harvest')->value('id'),
            'harvest_id'        => $this->harvest->id,
            'amount_pledged'    => $amount,
            'date_pledged'      => '2026-08-10',
            'status'            => 'Active',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'type'             => 'Harvest',
            'account_id'       => $this->income->id,
            'cash_account_id'  => $this->cash->id,
            'category'         => 'Income',
            'amount'           => '100.00',
            'transaction_date' => '2026-11-15',
            'payment_method'   => 'Cash',
            'status'           => 'Confirmed',
            'harvest_id'       => $this->harvest->id,
        ];
    }

    private function record(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('finance.store'), $this->payload($overrides));
    }

    private function updateTx(Transaction $tx, array $overrides): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('finance.update', $tx), $overrides + [
            'type'             => $tx->type,
            'category'         => 'Income',
            'amount'           => (string) $tx->amount,
            'member_id'        => $tx->member_id,
            'payer_name'       => $tx->payer_name,
            'transaction_date' => $tx->transaction_date->toDateString(),
            'payment_method'   => $tx->payment_method,
            'status'           => $tx->status,
            'harvest_id'       => $this->harvest->id,
        ]);
    }

    // ── the form ───────────────────────────────────────────────────────

    public function test_harvest_money_needs_a_harvest_campaign(): void
    {
        $this->record(['harvest_id' => null, 'member_id' => $this->member()->id])->assertSessionHasErrors('harvest_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_other_types_do_not_need_a_harvest(): void
    {
        $this->record(['type' => 'Offering', 'harvest_id' => null])->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, PledgePayment::count());
    }

    public function test_only_active_harvests_are_offered_and_accepted(): void
    {
        $closed = Harvest::create(['name' => 'Closed Harvest', 'year' => 2025, 'target_amount' => 1, 'status' => 'Completed']);

        $this->actingAs($this->admin)->get(route('finance.create'))
            ->assertOk()->assertSee('Harvest campaign')->assertSee('Year End Harvest')->assertDontSee('Closed Harvest');

        $this->record(['harvest_id' => $closed->id, 'member_id' => $this->member()->id])->assertSessionHasErrors('harvest_id');
    }

    public function test_the_pledge_picker_lists_the_payers_pledges(): void
    {
        $one = $this->member('One');
        $two = $this->member('Two');
        $p = $this->pledge($one, 500);
        PledgePayment::create(['pledge_id' => $p->id, 'amount' => 200, 'payment_date' => '2026-09-01', 'payment_method' => 'Cash']);
        $this->pledge($two, 300);
        $this->pledge($two, 400);

        $url = fn (array $q) => route('finance.harvest-pledges', $q + ['harvest_id' => $this->harvest->id]);

        $this->actingAs($this->admin)->getJson($url(['member_id' => $one->id]))
            ->assertOk()->assertJsonPath('rule', 'single')
            ->assertJsonPath('pledges.0.reference', $p->reference)
            ->assertJsonPath('pledges.0.pledged', 500)
            ->assertJsonPath('pledges.0.paid', 200)
            ->assertJsonPath('pledges.0.balance', 300);
        $this->actingAs($this->admin)->getJson($url(['member_id' => $two->id]))->assertJsonPath('rule', 'choose')->assertJsonCount(2, 'pledges');
        $this->actingAs($this->admin)->getJson($url(['member_id' => $this->member('None')->id]))->assertJsonPath('rule', 'giver')->assertJsonCount(0, 'pledges');

        $usher = User::factory()->create()->assignRole('Usher');
        $this->actingAs($usher)->getJson($url(['member_id' => $one->id]))->assertForbidden();
    }

    // ── saving ─────────────────────────────────────────────────────────

    public function test_saving_makes_one_transaction_one_ledger_entry_and_one_linked_payment(): void
    {
        $member = $this->member();
        $pledge = $this->pledge($member, 100);

        $this->record(['member_id' => $member->id])->assertSessionHasNoErrors();

        $tx = Transaction::sole();
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(1, JournalEntry::where('source_type', 'transaction')->where('source_id', $tx->id)->count());
        $payment = PledgePayment::sole();
        $this->assertSame($tx->id, (int) $payment->transaction_id);
        $this->assertSame($pledge->id, (int) $payment->pledge_id);
        $this->assertSame('100.00', $payment->amount);
        $this->assertSame('2026-11-15', $payment->payment_date->toDateString());
        $this->assertSame('Fulfilled', $pledge->fresh()->status);
        $this->assertSame(100.0, $this->harvest->fresh()->total_paid);
    }

    public function test_more_than_one_pledge_needs_a_choice(): void
    {
        $member = $this->member();
        $this->pledge($member, 300);
        $second = $this->pledge($member, 400);

        $this->record(['member_id' => $member->id])->assertSessionHasErrors('pledge_id');
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, JournalEntry::count());

        $this->record(['member_id' => $member->id, 'pledge_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame($second->id, (int) PledgePayment::sole()->pledge_id);
    }

    public function test_a_pledge_that_is_not_the_payers_is_refused(): void
    {
        $someoneElse = $this->pledge($this->member('Else'), 300);

        $this->record(['member_id' => $this->member()->id, 'pledge_id' => $someoneElse->id])->assertSessionHasErrors('pledge_id');
        $this->assertSame(0, Transaction::count());
    }

    public function test_no_pledge_makes_a_giver_pledge_that_later_gifts_top_up(): void
    {
        $member = $this->member();

        $this->record(['member_id' => $member->id, 'amount' => '120.00'])->assertSessionHasNoErrors();
        $giver = Pledge::sole();
        $this->assertSame(Pledge::GIVER_NOTE, $giver->notes);
        $this->assertSame($this->harvest->id, (int) $giver->harvest_id);
        $this->assertSame((int) $member->id, (int) $giver->member_id);
        $this->assertSame('Harvest', $giver->purpose->name);
        $this->assertSame('120.00', $giver->amount_pledged);
        $this->assertSame('Fulfilled', $giver->status);

        $this->record(['member_id' => $member->id, 'amount' => '30.50', 'transaction_date' => '2026-11-16'])->assertSessionHasNoErrors();
        $this->assertSame(1, Pledge::count());
        $this->assertSame('150.50', $giver->fresh()->amount_pledged);
        $this->assertSame('Fulfilled', $giver->fresh()->status);
        $this->assertSame(2, $giver->payments()->count());
        $this->assertSame(2, Transaction::count());
        $this->assertSame(2, JournalEntry::count());
    }

    public function test_a_payer_name_without_a_member_works_the_same(): void
    {
        $this->record(['payer_name' => 'Walk In Giver'])->assertSessionHasNoErrors();
        $this->record(['payer_name' => '  walk in   GIVER '])->assertSessionHasNoErrors();

        $giver = Pledge::sole();
        $this->assertNull($giver->member_id);
        $this->assertSame('200.00', $giver->amount_pledged);

        $this->record(['member_id' => null, 'payer_name' => ''])->assertSessionHasErrors('pledge_id');
    }

    public function test_pending_harvest_money_is_linked_once_confirmed(): void
    {
        $member = $this->member();
        $this->pledge($member, 500);

        $this->record(['member_id' => $member->id, 'status' => 'Pending'])->assertSessionHasNoErrors();
        $tx = Transaction::sole();
        $this->assertSame(0, PledgePayment::count());

        $this->updateTx($tx, ['status' => 'Confirmed'])->assertSessionHasNoErrors();
        $this->assertSame($tx->id, (int) PledgePayment::sole()->transaction_id);
    }

    // ── editing ────────────────────────────────────────────────────────

    public function test_editing_keeps_the_payment_in_step(): void
    {
        $member = $this->member();
        $pledge = $this->pledge($member, 500);
        $this->record(['member_id' => $member->id])->assertSessionHasNoErrors();
        $tx = Transaction::sole();

        $this->updateTx($tx, ['amount' => '250.00', 'transaction_date' => '2026-11-10', 'payment_method' => 'Mobile Money'])->assertSessionHasNoErrors();

        $payment = PledgePayment::sole();
        $this->assertSame('250.00', $payment->amount);
        $this->assertSame('2026-11-10', $payment->payment_date->toDateString());
        $this->assertSame('Mobile Money', $payment->payment_method);
        $this->assertSame($pledge->id, (int) $payment->pledge_id);
        $this->assertSame(1, Transaction::count());
    }

    public function test_a_giver_pledge_follows_an_edited_amount(): void
    {
        $member = $this->member();
        $this->record(['member_id' => $member->id, 'amount' => '100.00'])->assertSessionHasNoErrors();

        $this->updateTx(Transaction::sole(), ['amount' => '80.00'])->assertSessionHasNoErrors();

        $giver = Pledge::sole();
        $this->assertSame('80.00', $giver->amount_pledged);
        $this->assertSame('Fulfilled', $giver->status);
    }

    public function test_changing_the_member_or_harvest_moves_the_payment(): void
    {
        $first  = $this->member('First');
        $second = $this->member('Second');
        $old = $this->pledge($first, 100);
        $new = $this->pledge($second, 300);
        $this->record(['member_id' => $first->id])->assertSessionHasNoErrors();
        $tx = Transaction::sole();
        $this->assertSame('Fulfilled', $old->fresh()->status);

        $this->updateTx($tx, ['member_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame($new->id, (int) PledgePayment::sole()->pledge_id);
        $this->assertSame(0, $old->payments()->count());
        $this->assertSame('Active', $old->fresh()->status);

        $this->updateTx($tx->fresh(), ['harvest_id' => $this->other->id])->assertSessionHasNoErrors();
        $moved = PledgePayment::sole()->pledge;
        $this->assertSame($this->other->id, (int) $moved->harvest_id);
        $this->assertTrue($moved->isGiver());
        $this->assertSame(0, $new->payments()->count());
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_no_longer_harvest_money_removes_the_payment_but_keeps_the_transaction(): void
    {
        $member = $this->member();
        $pledge = $this->pledge($member, 500);
        $this->record(['member_id' => $member->id])->assertSessionHasNoErrors();
        $tx = Transaction::sole();

        $this->updateTx($tx, ['type' => 'Offering', 'harvest_id' => null])->assertSessionHasNoErrors();

        $this->assertSame(0, PledgePayment::count());
        $this->assertSame(1, PledgePayment::withTrashed()->count());
        $this->assertSame('Offering', $tx->fresh()->type);
        $this->assertFalse($tx->fresh()->trashed());
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(0.0, $pledge->fresh()->total_paid);
    }

    public function test_the_edit_form_keeps_harvest_types_and_shows_the_link(): void
    {
        $member = $this->member();
        $pledge = $this->pledge($member, 500);
        $this->record(['member_id' => $member->id])->assertSessionHasNoErrors();
        $tx = Transaction::sole();

        $this->actingAs($this->admin)->get(route('finance.edit', $tx))->assertOk()
            ->assertSee('<option value="Harvest" selected>Harvest</option>', false)
            ->assertSee('<option value="Project Offering"', false)
            ->assertSee('data-selected="' . $pledge->id . '"', false);

        $this->actingAs($this->admin)->get(route('finance.show', $tx))->assertOk()->assertSee($pledge->reference);
    }

    // ── deleting ───────────────────────────────────────────────────────

    public function test_voiding_the_transaction_removes_its_payment_once(): void
    {
        $member = $this->member();
        $pledge = $this->pledge($member, 100);
        $this->record(['member_id' => $member->id])->assertSessionHasNoErrors();
        $tx = Transaction::sole();

        $this->actingAs($this->admin)->delete(route('finance.destroy', $tx))->assertRedirect();

        $this->assertSame(0, Transaction::count());
        $this->assertSame(1, Transaction::withTrashed()->count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, PledgePayment::count());
        $this->assertSame(1, PledgePayment::withTrashed()->count());
        $this->assertSame('Active', $pledge->fresh()->status);
        $this->assertSame(0.0, $this->harvest->fresh()->total_paid);
    }

    public function test_deleting_the_payment_from_the_pledge_voids_the_transaction_once(): void
    {
        $member = $this->member();
        $pledge = $this->pledge($member, 100);
        $this->record(['member_id' => $member->id])->assertSessionHasNoErrors();
        $payment = PledgePayment::sole();

        $this->actingAs($this->admin)->delete(route('pledges.payments.destroy', [$pledge, $payment]))->assertRedirect();

        $this->assertSame(0, Transaction::count());
        $this->assertSame(1, Transaction::withTrashed()->count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(1, PledgePayment::withTrashed()->count());
    }

    public function test_voiding_the_last_gift_removes_an_empty_giver_pledge(): void
    {
        $member = $this->member();
        $this->record(['member_id' => $member->id, 'amount' => '50.00'])->assertSessionHasNoErrors();
        $this->record(['member_id' => $member->id, 'amount' => '70.00'])->assertSessionHasNoErrors();
        [$first, $second] = Transaction::orderBy('id')->get()->all();

        $this->actingAs($this->admin)->delete(route('finance.destroy', $first));
        $giver = Pledge::sole();
        $this->assertSame('70.00', $giver->amount_pledged);
        $this->assertSame('Fulfilled', $giver->status);

        $this->actingAs($this->admin)->delete(route('finance.destroy', $second));
        $this->assertSame(0, Pledge::count());
        $this->assertSame(0, PledgePayment::count());
    }

    // ── harvest page and pledge form ───────────────────────────────────

    public function test_the_harvest_page_leaves_out_cancelled_pledges_in_both_totals(): void
    {
        $keep = $this->pledge($this->member('Keep'), 300);
        $gone = $this->pledge($this->member('Gone'), 200);
        PledgePayment::create(['pledge_id' => $keep->id, 'amount' => 100, 'payment_date' => '2026-09-01', 'payment_method' => 'Cash']);
        PledgePayment::create(['pledge_id' => $gone->id, 'amount' => 50, 'payment_date' => '2026-09-01', 'payment_method' => 'Cash']);
        $gone->update(['status' => 'Cancelled']);

        $harvest = $this->harvest->fresh();
        $this->assertSame(300.0, $harvest->total_pledged);
        $this->assertSame(100.0, $harvest->total_paid);
        $this->assertSame(200.0, $harvest->outstanding);

        $this->actingAs($this->admin)->get(route('harvests.show', $harvest))->assertOk()
            ->assertSee('GH₵ 300.00')->assertSee('GH₵ 100.00')->assertSee('GH₵ 200.00');
    }

    public function test_a_harvest_purpose_pledge_must_name_the_harvest(): void
    {
        $member  = $this->member();
        $harvest = PledgePurpose::where('name', 'Harvest')->value('id');
        $other   = PledgePurpose::where('name', '!=', 'Harvest')->value('id');
        $data = fn (array $o) => $o + ['pledger_type' => 'member', 'member_id' => $member->id, 'amount_pledged' => 100, 'date_pledged' => '2026-11-01'];

        $this->actingAs($this->admin)->post(route('pledges.store'), $data(['pledge_purpose_id' => $harvest]))
            ->assertSessionHasErrors(['harvest_id' => 'Choose the Harvest campaign: this pledge is for the harvest.']);
        $this->actingAs($this->admin)->post(route('pledges.store'), $data(['pledge_purpose_id' => $other]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('pledges.store'), $data(['pledge_purpose_id' => $harvest, 'harvest_id' => $this->harvest->id]))->assertSessionHasNoErrors();

        $pledge = Pledge::where('pledge_purpose_id', $other)->sole();
        $this->actingAs($this->admin)->patch(route('pledges.update', $pledge), $data(['pledge_purpose_id' => $harvest, 'status' => 'Active']))
            ->assertSessionHasErrors('harvest_id');
    }
}
