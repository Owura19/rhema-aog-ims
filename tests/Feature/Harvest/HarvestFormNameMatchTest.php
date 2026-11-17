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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Record Transaction: pledges imported with only a name are offered, never picked silently. */
class HarvestFormNameMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Harvest $harvest;
    private Member $member;
    private Pledge $byName;
    private Account $income;
    private Account $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin   = User::factory()->create()->assignRole('Super Admin');
        $this->income  = Account::create(['code' => '4300', 'name' => 'Harvest Income', 'type' => 'Income', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);
        $this->cash    = Account::create(['code' => '1100', 'name' => 'Cash on Hand', 'type' => 'Asset', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);
        $this->harvest = Harvest::create(['name' => 'Harvest 2025', 'year' => 2025, 'target_amount' => 1, 'status' => 'Active']);
        $this->member  = Member::create(['first_name' => 'Kofi', 'last_name' => 'Mensah']);
        $this->byName  = $this->pledge(['pledger_name' => 'M/M Kofi Mensah']);
    }

    private function pledge(array $attrs): Pledge
    {
        return Pledge::create($attrs + [
            'pledge_purpose_id' => PledgePurpose::where('name', 'Harvest')->value('id'),
            'harvest_id' => $this->harvest->id, 'amount_pledged' => 1000, 'date_pledged' => '2025-08-02', 'status' => 'Active',
        ]);
    }

    private function record(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('finance.store'), $overrides + [
            'type' => 'Harvest', 'account_id' => $this->income->id, 'cash_account_id' => $this->cash->id, 'category' => 'Income',
            'amount' => '200.00', 'transaction_date' => '2026-08-30', 'payment_method' => 'Cash', 'status' => 'Confirmed',
            'harvest_id' => $this->harvest->id, 'member_id' => $this->member->id, 'description' => 'HARVEST 2025',
        ]);
    }

    public function test_the_picker_offers_the_name_match_for_a_person_to_choose(): void
    {
        $this->actingAs($this->admin)->getJson(route('finance.harvest-pledges', ['harvest_id' => $this->harvest->id, 'member_id' => $this->member->id]))
            ->assertOk()
            ->assertJsonPath('rule', 'choose')
            ->assertJsonPath('pledges.0.reference', $this->byName->reference)
            ->assertJsonPath('pledges.0.match', 'name');
    }

    public function test_a_name_match_is_never_used_silently(): void
    {
        $this->record()->assertSessionHasErrors(['pledge_id' => 'No pledge is recorded under this member, but ' . $this->byName->reference
            . ' matches their name. Choose it to confirm (it will be put under the member), or choose "record as a harvest giver".']);

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Pledge::where('notes', Pledge::GIVER_NOTE)->count());
    }

    public function test_choosing_the_name_match_puts_the_pledge_under_the_member(): void
    {
        $this->record(['pledge_id' => (string) $this->byName->id])->assertSessionHasNoErrors();

        $this->assertSame($this->byName->id, (int) PledgePayment::sole()->pledge_id);
        $this->assertSame($this->member->id, (int) $this->byName->fresh()->member_id);
        $event = HarvestLinkEvent::sole();
        $this->assertSame(['finance_form', true, $this->admin->id], [$event->source, $event->member_claimed, (int) $event->user_id]);

        // From now on it is the member's own pledge: picked without asking
        $this->record(['amount' => '100.00'])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->byName->payments()->count());
    }

    public function test_choosing_giver_leaves_the_name_match_alone(): void
    {
        $this->record(['pledge_id' => 'giver'])->assertSessionHasNoErrors();

        $this->assertNull($this->byName->fresh()->member_id);
        $giver = Pledge::where('notes', Pledge::GIVER_NOTE)->sole();
        $this->assertSame($this->member->id, (int) $giver->member_id);
        $this->assertSame('200.00', $giver->amount_pledged);
    }

    public function test_the_members_own_pledge_still_wins_without_asking(): void
    {
        $own = $this->pledge(['member_id' => $this->member->id]);

        $this->record()->assertSessionHasNoErrors();

        $this->assertSame($own->id, (int) PledgePayment::sole()->pledge_id);
        $this->assertNull($this->byName->fresh()->member_id);
    }

    public function test_a_payer_name_with_a_title_finds_their_name_only_pledge(): void
    {
        $owusu = $this->pledge(['pledger_name' => 'Ama Owusu']);

        $this->record(['member_id' => null, 'payer_name' => 'Mrs. AMA  Owusu'])->assertSessionHasNoErrors();

        $this->assertSame($owusu->id, (int) PledgePayment::sole()->pledge_id);
    }

    public function test_someone_elses_name_only_pledge_cannot_be_chosen(): void
    {
        $other = $this->pledge(['pledger_name' => 'Yaw Boateng']);

        $this->record(['pledge_id' => (string) $other->id])->assertSessionHasErrors('pledge_id');
        $this->assertSame(0, Transaction::count());
    }
}
