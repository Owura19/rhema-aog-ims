<?php

namespace Tests\Feature\Api\Member;

use App\Models\Harvest;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/me/pledges */
class MePledgesTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    private Member $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->signInAsMember();
        $this->other = $this->member(['first_name' => 'Kofi', 'last_name' => 'Boateng']);
    }

    public function test_it_lists_only_my_pledges_newest_first(): void
    {
        $february = $this->pledge($this->me, ['date_pledged' => '2026-02-01']);
        $june     = $this->pledge($this->me, ['date_pledged' => '2026-06-01']);

        $this->pledge($this->other);
        $this->pledge(null, ['pledger_name' => 'Ama Mensah']);           // a name-only pledge is not linked to me
        $this->pledge($this->me, ['date_pledged' => '2026-07-01'])->delete();

        $this->assertSame([$june->id, $february->id], $this->ids($this->api('GET', '/me/pledges')->assertOk()));
    }

    public function test_paid_balance_and_progress_count_live_payments_only(): void
    {
        $harvest = Harvest::create(['name' => 'Harvest 2026', 'year' => 2026]);
        $pledge = $this->pledge($this->me, [
            'harvest_id'  => $harvest->id,
            'target_date' => '2026-12-31',
            'notes'       => 'Staff note on this pledge',
        ]);
        $march = $this->pay($pledge, '200.00', '2026-03-01');
        $april = $this->pay($pledge, '100.00', '2026-04-01', 'Mobile Money');
        $this->pay($pledge, '500.00', '2026-05-01')->delete();             // removed payment: not counted

        $response = $this->api('GET', '/me/pledges')->assertOk();

        $response->assertJsonPath('data.0', [
            'id'               => $pledge->id,
            'reference'        => $pledge->reference,
            'purpose'          => 'Building Project',
            'harvest'          => 'Harvest 2026',
            'date_pledged'     => '2026-02-01',
            'target_date'      => '2026-12-31',
            'status'           => 'Active',
            'currency'         => 'GHS',
            'amount_pledged'   => '1000.00',
            'paid'             => '300.00',
            'balance'          => '700.00',
            'overpaid'         => '0.00',
            'progress_percent' => 30,
            'payments'         => [
                ['id' => $april->id, 'date' => '2026-04-01', 'amount' => '100.00', 'payment_method' => 'Mobile Money', 'receipt' => $april->fresh()->transaction->reference],
                ['id' => $march->id, 'date' => '2026-03-01', 'amount' => '200.00', 'payment_method' => 'Cash', 'receipt' => $march->fresh()->transaction->reference],
            ],
        ]);

        $this->assertStringNotContainsString('Staff note on this pledge', $response->getContent());
    }

    public function test_an_overpaid_pledge_shows_the_overpaid_amount(): void
    {
        $pledge = $this->pledge($this->me, ['amount_pledged' => '500.00']);
        $this->pay($pledge, '600.00');

        $this->api('GET', '/me/pledges')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'Fulfilled')
            ->assertJsonPath('data.0.paid', '600.00')
            ->assertJsonPath('data.0.balance', '0.00')
            ->assertJsonPath('data.0.overpaid', '100.00')
            ->assertJsonPath('data.0.progress_percent', 100);
    }

    public function test_the_summary_leaves_out_cancelled_pledges_and_never_counts_overpayment_as_owing(): void
    {
        $active = $this->pledge($this->me, ['amount_pledged' => '1000.00', 'date_pledged' => '2026-03-01']);
        $this->pay($active, '300.00');

        $overpaid = $this->pledge($this->me, ['amount_pledged' => '500.00', 'date_pledged' => '2026-02-01']);
        $this->pay($overpaid, '600.00');

        $cancelled = $this->pledge($this->me, ['amount_pledged' => '400.00', 'date_pledged' => '2026-01-01', 'status' => 'Cancelled']);
        $this->pay($cancelled, '100.00');

        $response = $this->api('GET', '/me/pledges')->assertOk();

        $this->assertSame([$active->id, $overpaid->id, $cancelled->id], $this->ids($response));   // cancelled still listed
        $response->assertJsonPath('data.2.status', 'Cancelled');
        $this->assertSame(['pledged' => '1500.00', 'paid' => '900.00', 'outstanding' => '700.00'], $response->json('summary'));
    }

    public function test_the_status_filter_narrows_the_list_but_not_the_summary(): void
    {
        $this->pledge($this->me, ['amount_pledged' => '1000.00']);
        $fulfilled = $this->pledge($this->me, ['amount_pledged' => '200.00', 'date_pledged' => '2026-01-01']);
        $this->pay($fulfilled, '200.00');

        $response = $this->api('GET', '/me/pledges?status=Fulfilled')->assertOk();

        $this->assertSame([$fulfilled->id], $this->ids($response));
        $this->assertSame(['pledged' => '1200.00', 'paid' => '200.00', 'outstanding' => '1000.00'], $response->json('summary'));
    }

    public function test_a_member_with_no_pledges_gets_an_empty_list(): void
    {
        $this->pledge($this->other);

        $response = $this->api('GET', '/me/pledges')->assertOk();

        $this->assertSame([], $response->json('data'));
        $this->assertSame(['pledged' => '0.00', 'paid' => '0.00', 'outstanding' => '0.00'], $response->json('summary'));
    }

    public function test_a_bad_status_is_422(): void
    {
        $this->api('GET', '/me/pledges?status=Paid')->assertStatus(422)->assertJsonValidationErrorFor('status');
    }
}
