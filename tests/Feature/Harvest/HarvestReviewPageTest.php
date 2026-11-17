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
use App\Services\HarvestLinker;
use App\Services\LedgerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Harvests → Link payments: Finance confirms each link; the system only suggests. */
class HarvestReviewPageTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;
    private Harvest $h2026;
    private Harvest $h2025;
    private Account $income;
    private Account $cash;
    /** @var array<string, mixed> */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->finance = User::factory()->create()->assignRole('Finance Officer');
        $this->income  = Account::create(['code' => '4300', 'name' => 'Harvest Income', 'type' => 'Income', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);
        $this->cash    = Account::create(['code' => '1100', 'name' => 'Cash on Hand', 'type' => 'Asset', 'is_group' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->h2026 = Harvest::create(['name' => '2026 End of Year Harvest', 'year' => 2026, 'target_amount' => 1, 'pledge_opens' => '2026-08-01', 'harvest_date' => '2026-12-12', 'status' => 'Active']);
        $this->h2025 = Harvest::create(['name' => 'Harvest 2025', 'year' => 2025, 'target_amount' => 1, 'status' => 'Active']);
        Harvest::create(['name' => "Men's Ministry Day", 'year' => 2026, 'target_amount' => 1, 'pledge_opens' => '2026-07-19', 'harvest_date' => '2026-08-19', 'status' => 'Active']);

        $a = Member::create(['first_name' => 'Abena', 'last_name' => 'Asante']);
        $b = Member::create(['first_name' => 'Kofi', 'last_name' => 'Mensah']);
        $c = Member::create(['first_name' => 'Yaw', 'last_name' => 'Boateng']);
        $this->s = [
            'a' => $a, 'b' => $b, 'c' => $c,
            'pledgeA' => $this->pledge($this->h2026, 500, ['member_id' => $a->id]),
            'pledgeB' => $this->pledge($this->h2025, 6000, ['pledger_name' => 'Elder MENSAH Kofi']),   // imported, name only
            'a1' => $this->tx($a, 300, '2026-10-04', 'HARVEST 2026'),                  // clean
            'b1' => $this->tx($b, 6000, '2026-08-30', 'HARVEST 2025'),                 // year mismatch + name match
            'c1' => $this->tx($c, 100, '2026-10-11', '(MOMO 24/07/26) HARVEST 2026'),   // clean, new giver
            'c2' => $this->tx($c, 50, '2026-10-18', 'harvest 26'),                     // clean, same giver
            'd1' => $this->tx($a, 20, '2026-08-15', 'Harvest'),                        // by date + overlap
        ];
    }

    private function pledge(Harvest $harvest, float $amount, array $attrs): Pledge
    {
        return Pledge::create($attrs + [
            'pledge_purpose_id' => PledgePurpose::where('name', 'Harvest')->value('id'),
            'harvest_id' => $harvest->id, 'amount_pledged' => $amount, 'date_pledged' => '2025-08-02', 'status' => 'Active',
        ]);
    }

    private function tx(Member $member, float $amount, string $date, string $description): Transaction
    {
        $tx = Transaction::create([
            'type' => 'Harvest', 'account_id' => $this->income->id, 'category' => 'Income', 'amount' => $amount, 'member_id' => $member->id,
            'transaction_date' => $date, 'payment_method' => 'Cash', 'status' => 'Confirmed', 'description' => $description,
        ]);
        (new LedgerService())->postIncome($amount, $this->income->id, $this->cash->id, $date, 'Harvest', 'transaction', $tx->id);

        return $tx;
    }

    /** Form input for the given rows: key => [harvest, pledge value]. */
    private function input(array $rows, bool $confirmed = false): array
    {
        $data = ['selected' => [], 'rows' => []];
        foreach ($rows as $key => [$harvest, $pledge]) {
            $id = $this->s[$key]->id;
            $data['selected'][] = $id;
            $data['rows'][$id] = ['harvest_id' => $harvest?->id, 'pledge' => $pledge];
        }

        return $confirmed ? $data + ['confirmed' => '1'] : $data;
    }

    private function cleanRows(): array
    {
        return [
            'a1' => [$this->h2026, (string) $this->s['pledgeA']->id],
            'c1' => [$this->h2026, 'giver'],
            'c2' => [$this->h2026, 'giver'],
        ];
    }

    // ── access ────────────────────────────────────────────────────────

    public function test_only_edit_finance_can_use_the_page(): void
    {
        $payment = app(HarvestLinker::class)->linkTo($this->s['a1'], $this->h2026, $this->s['pledgeA'], null, HarvestLinkEvent::SOURCE_REVIEW);

        foreach (Role::pluck('name') as $role) {
            $user = User::factory()->create()->assignRole($role);
            $allowed = $user->can('edit finance');

            $this->actingAs($user)->get(route('harvests.link-payments'))->assertStatus($allowed ? 200 : 403);
            if (! $allowed) {
                $this->actingAs($user)->post(route('harvests.link-payments.preview'), $this->input($this->cleanRows()))->assertForbidden();
                $this->actingAs($user)->post(route('harvests.link-payments.store'), $this->input($this->cleanRows(), true))->assertForbidden();
                $this->actingAs($user)->delete(route('harvests.link-payments.unlink', $payment))->assertForbidden();
            }
        }
        $this->assertSame(1, PledgePayment::count());
    }

    // ── the list ──────────────────────────────────────────────────────

    public function test_it_lists_unlinked_payments_with_suggestions_and_flags(): void
    {
        $page = $this->actingAs($this->finance)->get(route('harvests.link-payments'))->assertOk()
            ->assertSee('Waiting to be linked (5, of which 3 without flags)')
            ->assertSee('Select all rows without flags')
            ->assertSee('Year in the description differs from the date')
            ->assertSee('Pledge matched by name: check')
            ->assertSee('Campaign from the date: check');

        $html = $page->getContent();
        foreach (['a1' => 1, 'c1' => 1, 'c2' => 1, 'b1' => 0, 'd1' => 0] as $key => $clean) {
            $this->assertMatchesRegularExpression('/value="' . $this->s[$key]->id . '" class="row-check" data-clean="' . $clean . '"/', $html, $key);
        }
        // B1 is suggested to the 2025 campaign (description), not 2026 (its date)
        $this->assertMatchesRegularExpression('/name="rows\[' . $this->s['b1']->id . '\]\[harvest_id\]"[^>]*>\s*<option value="">Choose…<\/option>(?:\s*<option[^>]*>[^<]*<\/option>)*?\s*<option value="' . $this->h2025->id . '" selected>/', $html);
    }

    public function test_filters(): void
    {
        $get = fn (array $q) => $this->actingAs($this->finance)->get(route('harvests.link-payments', $q))->assertOk()->getContent();

        $mismatch = $get(['flag' => 'year_mismatch']);
        $this->assertStringContainsString($this->s['b1']->reference, $mismatch);
        $this->assertStringNotContainsString($this->s['a1']->reference, $mismatch);

        $clean = $get(['flag' => 'clean']);
        $this->assertStringContainsString($this->s['c2']->reference, $clean);
        $this->assertStringNotContainsString($this->s['d1']->reference, $clean);

        $h2025 = $get(['harvest' => (string) $this->h2025->id]);
        $this->assertStringContainsString($this->s['b1']->reference, $h2025);
        $this->assertStringNotContainsString($this->s['c1']->reference, $h2025);
    }

    // ── link selected ─────────────────────────────────────────────────

    public function test_the_confirmation_summarises_per_campaign_and_changes_nothing(): void
    {
        $this->actingAs($this->finance)->post(route('harvests.link-payments.preview'), $this->input($this->cleanRows()))
            ->assertOk()
            ->assertSee('Confirm: link 3 payments')
            ->assertSeeInOrder(['2026 End of Year Harvest', '3', 'GH₵ 450.00', '1'])
            ->assertSee('Confirm and link 3');

        $this->assertSame(0, PledgePayment::count());
        $this->assertSame(0, HarvestLinkEvent::count());
    }

    public function test_linking_keeps_finance_and_the_journal_unchanged(): void
    {
        $before = FinanceSnapshot::take();

        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'), $this->input($this->cleanRows(), true))
            ->assertRedirect(route('harvests.link-payments'))->assertSessionHas('success', '3 payments linked.');

        $this->assertSame($before, FinanceSnapshot::take());
        $this->assertSame(3, PledgePayment::count());
        $this->assertSame(300.0, $this->s['pledgeA']->fresh()->total_paid);
        $giver = Pledge::where('member_id', $this->s['c']->id)->sole();
        $this->assertTrue($giver->isGiver());
        $this->assertSame('150.00', $giver->amount_pledged);
        $this->assertSame(3, HarvestLinkEvent::where('source', 'review')->where('user_id', $this->finance->id)->count());
        $this->assertSame(450.0, $this->h2026->fresh()->total_paid);
    }

    public function test_it_must_be_confirmed(): void
    {
        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'), $this->input($this->cleanRows()))
            ->assertSessionHasErrors('confirmed');

        $this->assertSame(0, PledgePayment::count());
    }

    public function test_a_confirmed_name_match_links_to_the_right_campaign_and_gives_the_pledge_the_member(): void
    {
        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'),
            $this->input(['b1' => [$this->h2025, (string) $this->s['pledgeB']->id]], true))->assertSessionHasNoErrors();

        $this->assertSame($this->s['b']->id, (int) $this->s['pledgeB']->fresh()->member_id);
        $this->assertSame(6000.0, $this->h2025->fresh()->total_paid);
        $this->assertSame(0.0, $this->h2026->fresh()->total_paid);
        $this->assertTrue(HarvestLinkEvent::sole()->member_claimed);
    }

    public function test_dont_link_rows_are_left_and_all_dont_link_is_refused(): void
    {
        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'),
            $this->input(['a1' => [$this->h2026, (string) $this->s['pledgeA']->id], 'd1' => [$this->h2026, 'none']], true))->assertSessionHasNoErrors();
        $this->assertSame(1, PledgePayment::count());

        $this->actingAs($this->finance)->post(route('harvests.link-payments.preview'), $this->input(['d1' => [$this->h2026, 'none']]))
            ->assertSessionHasErrors('selected');
    }

    public function test_one_bad_row_stops_the_whole_batch(): void
    {
        $rows = $this->cleanRows() + ['b1' => [$this->h2025, (string) $this->s['pledgeA']->id]];   // someone else's pledge

        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'), $this->input($rows, true))
            ->assertRedirect(route('harvests.link-payments'))->assertSessionHasErrors('selected');

        $this->assertSame(0, PledgePayment::count());
        $this->assertSame(0, HarvestLinkEvent::count());
    }

    public function test_sending_the_same_batch_twice_links_once(): void
    {
        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'), $this->input($this->cleanRows(), true))->assertSessionHasNoErrors();
        $after = [PledgePayment::count(), Pledge::sum('amount_pledged'), HarvestLinkEvent::count()];

        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'), $this->input($this->cleanRows(), true))
            ->assertSessionHasErrors('selected');

        $this->assertSame($after, [PledgePayment::count(), Pledge::sum('amount_pledged'), HarvestLinkEvent::count()]);
    }

    public function test_a_payment_voided_after_the_preview_is_not_linked(): void
    {
        $input = $this->input($this->cleanRows(), true);
        $this->s['c2']->delete();

        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'), $input)->assertSessionHasErrors('selected');

        $this->assertSame(0, PledgePayment::count());
    }

    // ── unlink ────────────────────────────────────────────────────────

    public function test_unlink_keeps_the_transaction_and_reduces_the_giver_pledge(): void
    {
        $this->actingAs($this->finance)->post(route('harvests.link-payments.store'), $this->input($this->cleanRows(), true));
        $payment = PledgePayment::where('transaction_id', $this->s['c1']->id)->sole();
        $before = FinanceSnapshot::take();

        $this->actingAs($this->finance)->get(route('harvests.link-payments'))->assertSee('Linked on this page (3)');
        $this->actingAs($this->finance)->get(route('finance.show', $this->s['c1']))->assertSee(route('harvests.link-payments.unlink', $payment), false);

        $this->actingAs($this->finance)->delete(route('harvests.link-payments.unlink', $payment))->assertSessionHas('success');

        $this->assertSame($before, FinanceSnapshot::take());
        $this->assertFalse($this->s['c1']->fresh()->trashed());
        $this->assertSame('50.00', Pledge::where('member_id', $this->s['c']->id)->sole()->amount_pledged);
        $this->assertSame(['link', 'link', 'link', 'unlink'], HarvestLinkEvent::orderBy('id')->pluck('action')->all());
        $this->actingAs($this->finance)->get(route('harvests.link-payments'))->assertSee($this->s['c1']->reference)->assertSee('Linked on this page (2)');
    }

    public function test_links_from_the_finance_form_have_no_unlink_button(): void
    {
        $payment = app(HarvestLinker::class)->linkTo($this->s['a1'], $this->h2026, $this->s['pledgeA'], $this->finance->id, HarvestLinkEvent::SOURCE_FINANCE_FORM);

        $this->actingAs($this->finance)->get(route('finance.show', $this->s['a1']))->assertOk()
            ->assertSee($this->s['pledgeA']->reference)
            ->assertDontSee(route('harvests.link-payments.unlink', $payment), false);
        $this->actingAs($this->finance)->delete(route('harvests.link-payments.unlink', $payment))->assertSessionHas('error');
        $this->assertSame(1, PledgePayment::count());
    }

    // ── checks ────────────────────────────────────────────────────────

    public function test_the_checks_list_pledges_whose_status_does_not_match(): void
    {
        $paid = $this->pledge($this->h2025, 100, ['member_id' => $this->s['a']->id]);
        \DB::table('pledge_payments')->insert(['pledge_id' => $paid->id, 'amount' => 100, 'payment_date' => '2025-12-01', 'payment_method' => 'Cash', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->finance)->get(route('harvests.link-payments'))->assertOk()
            ->assertSee('Active but fully paid: 1')
            ->assertSee($paid->reference);
        $this->assertSame('Active', $paid->fresh()->status);   // report only
    }
}
