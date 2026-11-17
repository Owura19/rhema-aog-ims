<?php

namespace Tests\Feature\Harvest;

use App\Models\Account;
use App\Models\Harvest;
use App\Models\HarvestLinkEvent;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Harvest\FinanceSnapshot;
use App\Services\Harvest\HarvestLinkException;
use App\Services\HarvestLinker;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** HarvestLinker::linkTo / unlinkPayment: Finance-confirmed links, their audit, and their undo. */
class HarvestLinkerTest extends TestCase
{
    use RefreshDatabase;

    private HarvestLinker $linker;
    private Harvest $harvest;
    private Member $member;
    private User $user;
    private Account $income;
    private Account $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->linker  = app(HarvestLinker::class);
        $this->user    = User::factory()->create();
        $this->income  = Account::create(['code' => '4300', 'name' => 'Harvest Income', 'type' => 'Income', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);
        $this->cash    = Account::create(['code' => '1100', 'name' => 'Cash on Hand', 'type' => 'Asset', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);
        $this->harvest = Harvest::create(['name' => 'Harvest 2025', 'year' => 2025, 'target_amount' => 1, 'status' => 'Active']);
        $this->member  = Member::create(['first_name' => 'Kofi', 'last_name' => 'Mensah']);
    }

    private function tx(array $attrs = []): Transaction
    {
        $tx = Transaction::create($attrs + [
            'type' => 'Harvest', 'account_id' => $this->income->id, 'category' => 'Income', 'amount' => 100, 'member_id' => $this->member->id,
            'transaction_date' => '2026-08-30', 'payment_method' => 'Cash', 'status' => 'Confirmed', 'description' => 'HARVEST 2025',
        ]);
        (new LedgerService())->postIncome((float) $tx->amount, $this->income->id, $this->cash->id, '2026-08-30', 'Harvest', 'transaction', $tx->id);

        return $tx;
    }

    private function pledge(float $amount, array $attrs = []): Pledge
    {
        return Pledge::create($attrs + [
            'member_id' => $this->member->id, 'pledge_purpose_id' => PledgePurpose::where('name', 'Harvest')->value('id'),
            'harvest_id' => $this->harvest->id, 'amount_pledged' => $amount, 'date_pledged' => '2025-08-02', 'status' => 'Active',
        ]);
    }

    private function link(Transaction $tx, Pledge|string $target): PledgePayment
    {
        return $this->linker->linkTo($tx, $this->harvest, $target, $this->user->id, HarvestLinkEvent::SOURCE_REVIEW);
    }

    public function test_linking_to_the_members_pledge_adds_a_payment_and_an_audit_row_only(): void
    {
        $pledge = $this->pledge(100);
        $tx = $this->tx();
        $before = FinanceSnapshot::take();

        $payment = $this->link($tx, $pledge);

        $this->assertSame(FinanceSnapshot::take(), $before);
        $this->assertSame($tx->id, (int) $payment->transaction_id);
        $this->assertSame('Fulfilled', $pledge->fresh()->status);
        $event = HarvestLinkEvent::sole();
        $this->assertSame(['link', 'review', $this->user->id, $pledge->id, $this->harvest->id, false, false],
            [$event->action, $event->source, (int) $event->user_id, (int) $event->pledge_id, (int) $event->harvest_id, $event->giver_created, $event->member_claimed]);
    }

    public function test_a_name_only_pledge_is_given_the_member_and_undo_takes_it_off(): void
    {
        $pledge = $this->pledge(6000, ['member_id' => null, 'pledger_name' => 'Elder Mensah Kofi']);
        $tx = $this->tx(['amount' => 6000]);

        $payment = $this->link($tx, $pledge);

        $this->assertSame($this->member->id, (int) $pledge->fresh()->member_id);
        $this->assertTrue(HarvestLinkEvent::sole()->member_claimed);

        $before = FinanceSnapshot::take();
        $this->linker->unlinkPayment($payment, $this->user->id);

        $this->assertSame(FinanceSnapshot::take(), $before);
        $this->assertNull($pledge->fresh()->member_id);
        $this->assertSame(0, PledgePayment::count());
        $this->assertFalse($tx->fresh()->trashed());
        $unlink = HarvestLinkEvent::where('action', 'unlink')->sole();
        $this->assertTrue($unlink->member_claimed);
        $this->assertSame('review', $unlink->source);
    }

    public function test_someone_elses_pledge_is_refused(): void
    {
        $other = $this->pledge(100, ['member_id' => null, 'pledger_name' => 'Ama Owusu']);

        $this->expectException(HarvestLinkException::class);
        $this->link($this->tx(), $other);
    }

    public function test_giver_pledges_are_made_topped_up_and_removed_when_emptied(): void
    {
        $first  = $this->link($this->tx(['amount' => 40]), 'giver');
        $second = $this->link($this->tx(['amount' => 60]), 'giver');

        $giver = Pledge::sole();
        $this->assertTrue($giver->isGiver());
        $this->assertSame('100.00', $giver->amount_pledged);
        $this->assertSame([true, false], HarvestLinkEvent::orderBy('id')->pluck('giver_created')->all());

        $this->linker->unlinkPayment($first, $this->user->id);
        $this->assertSame('60.00', $giver->fresh()->amount_pledged);
        $this->assertSame('Fulfilled', $giver->fresh()->status);

        $this->linker->unlinkPayment($second, $this->user->id);
        $this->assertSame(0, Pledge::count());
        $this->assertSame(2, Transaction::count());
    }

    public function test_a_transaction_is_linked_once(): void
    {
        $pledge = $this->pledge(500);
        $tx = $this->tx();
        $this->link($tx, $pledge);

        try {
            $this->link($tx, $pledge);
            $this->fail('second link allowed');
        } catch (HarvestLinkException $e) {
            $this->assertStringContainsString('already linked', $e->getMessage());
        }
        $this->assertSame(1, PledgePayment::count());
        $this->assertSame(1, HarvestLinkEvent::count());
    }

    public function test_only_confirmed_live_harvest_transactions_to_open_pledges_on_that_harvest(): void
    {
        $pledge = $this->pledge(500);
        $other  = Harvest::create(['name' => 'Harvest 2026', 'year' => 2026, 'target_amount' => 1, 'status' => 'Active']);

        $cases = [
            'pending'       => fn () => $this->link($this->tx(['status' => 'Pending']), $pledge),
            'not harvest'   => fn () => $this->link($this->tx(['type' => 'Offering']), $pledge),
            'voided'        => fn () => $this->link(tap($this->tx())->delete(), $pledge),
            'wrong harvest' => fn () => $this->linker->linkTo($this->tx(), $other, $pledge, $this->user->id, 'review'),
            'cancelled'     => fn () => $this->link($this->tx(), tap($this->pledge(10))->update(['status' => 'Cancelled'])),
        ];
        foreach ($cases as $name => $case) {
            try {
                $case();
                $this->fail("{$name} was linked");
            } catch (HarvestLinkException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, PledgePayment::count());
    }

    public function test_only_review_page_links_can_be_undone_there(): void
    {
        $pledge = $this->pledge(500);
        $formTx = $this->tx();
        $formPayment = $this->linker->linkTo($formTx, $this->harvest, $pledge, $this->user->id, HarvestLinkEvent::SOURCE_FINANCE_FORM);

        $this->expectException(HarvestLinkException::class);
        $this->linker->unlinkPayment($formPayment, $this->user->id);
    }

    public function test_an_undone_link_can_be_made_again(): void
    {
        $pledge = $this->pledge(500);
        $tx = $this->tx();
        $this->linker->unlinkPayment($this->link($tx, $pledge), $this->user->id);

        $again = $this->link($tx, $pledge);

        $this->assertSame(1, PledgePayment::count());
        $this->assertNotNull(HarvestLinkEvent::activeReviewLink($again->id));
        $this->assertSame(['link', 'unlink', 'link'], HarvestLinkEvent::orderBy('id')->pluck('action')->all());
    }
}
