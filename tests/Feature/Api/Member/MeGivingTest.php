<?php

namespace Tests\Feature\Api\Member;

use App\Models\Member;
use App\Models\Pledge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/me/giving */
class MeGivingTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    private Member $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();   // 2026-11-09
        $this->signInAsMember();
        $this->other = $this->member(['first_name' => 'Kofi', 'last_name' => 'Boateng']);
    }

    public function test_it_lists_only_my_confirmed_income_newest_first(): void
    {
        $tithe    = $this->gift($this->me, ['amount' => '200.00', 'transaction_date' => '2026-03-01']);
        $offering = $this->gift($this->me, ['type' => 'Offering', 'amount' => '50.5', 'transaction_date' => '2026-05-01', 'payment_method' => 'Mobile Money', 'mobile_money_number' => '0241234567', 'description' => 'Staff remark', 'payer_name' => 'Ama M.']);

        $this->gift($this->other);                                                     // someone else's
        $this->gift($this->me, ['type' => 'Expense', 'category' => 'Expense']);       // not income
        $this->gift($this->me, ['status' => 'Pending']);
        $this->gift($this->me, ['status' => 'Cancelled']);
        $this->gift($this->me)->delete();                                              // voided (soft-deleted)

        $response = $this->api('GET', '/me/giving')->assertOk();

        $this->assertSame([$offering->id, $tithe->id], $this->ids($response));
        $this->assertSame(['id', 'source', 'reference', 'receipt_number', 'date', 'type', 'amount', 'currency', 'payment_method'], array_keys($response->json('data.0')));
        $response->assertJsonPath('data.0', [
            'id'             => $offering->id,
            'source'         => 'transaction',
            'reference'      => $offering->reference,
            'receipt_number' => null,
            'date'           => '2026-05-01',
            'type'           => 'Offering',
            'amount'         => '50.50',
            'currency'       => 'GHS',
            'payment_method' => 'Mobile Money',
        ]);

        foreach (['Staff remark', 'Ama M.', '0241234567'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $response->getContent());
        }
    }

    public function test_year_and_type_filters_and_the_summary_ignores_the_type_filter(): void
    {
        $march   = $this->gift($this->me, ['amount' => '100.00', 'transaction_date' => '2026-03-01']);
        $april   = $this->gift($this->me, ['amount' => '150.00', 'transaction_date' => '2026-04-01']);
        $this->gift($this->me, ['type' => 'Offering', 'amount' => '30.00', 'transaction_date' => '2026-05-01']);
        $lastYear = $this->gift($this->me, ['amount' => '500.00', 'transaction_date' => '2025-12-28']);

        $response = $this->api('GET', '/me/giving?year=2026&type=Tithe')->assertOk();

        $this->assertSame([$april->id, $march->id], $this->ids($response));
        $this->assertSame([
            'year'           => 2026,
            'month'          => '2026-11',
            'month_total'    => '0.00',
            'year_total'     => '280.00',
            'by_type'        => [
                ['type' => 'Tithe', 'total' => '250.00', 'count' => 2],
                ['type' => 'Offering', 'total' => '30.00', 'count' => 1],
            ],
            'all_time_total' => '780.00',
            'years'          => [2026, 2025],
        ], $response->json('summary'));

        $response = $this->api('GET', '/me/giving?year=2025')->assertOk();

        $this->assertSame([$lastYear->id], $this->ids($response));
        $response->assertJsonPath('summary.year', 2025)
            ->assertJsonPath('summary.month', null)          // an earlier year: no month figure
            ->assertJsonPath('summary.month_total', null)
            ->assertJsonPath('summary.year_total', '500.00')
            ->assertJsonPath('summary.by_type', [['type' => 'Tithe', 'total' => '500.00', 'count' => 1]]);
    }

    public function test_the_summary_defaults_to_the_current_year(): void
    {
        $lastYear = $this->gift($this->me, ['amount' => '500.00', 'transaction_date' => '2025-12-28']);

        $response = $this->api('GET', '/me/giving')->assertOk();

        $this->assertSame([$lastYear->id], $this->ids($response));   // no year filter: every year listed
        $this->assertSame([
            'year'           => 2026,
            'month'          => '2026-11',
            'month_total'    => '0.00',
            'year_total'     => '0.00',
            'by_type'        => [],
            'all_time_total' => '500.00',
            'years'          => [2025],
        ], $response->json('summary'));
    }

    public function test_it_paginates(): void
    {
        foreach (['2026-01-04', '2026-01-11', '2026-01-18'] as $date) {
            $this->gift($this->me, ['transaction_date' => $date]);
        }

        $first = $this->api('GET', '/me/giving?per_page=2')->assertOk();
        $this->assertCount(2, $first->json('data'));
        $first->assertJsonPath('meta.total', 3)->assertJsonPath('meta.per_page', 2)->assertJsonPath('meta.last_page', 2);

        $second = $this->api('GET', '/me/giving?per_page=2&page=2')->assertOk();
        $this->assertCount(1, $second->json('data'));
        $second->assertJsonPath('summary.year_total', '300.00');   // the summary isn't paginated
    }

    public static function badFilters(): array
    {
        return [
            'expense is not a gift type' => ['type=Expense', 'type'],
            'type is case-sensitive'     => ['type=tithe', 'type'],
            'year too early'             => ['year=1999', 'year'],
            'year too far ahead'         => ['year=2028', 'year'],
            'per_page 0'                 => ['per_page=0', 'per_page'],
            'per_page 51'                => ['per_page=51', 'per_page'],
        ];
    }

    #[DataProvider('badFilters')]
    public function test_bad_filters_are_422(string $query, string $field): void
    {
        $this->api('GET', "/me/giving?{$query}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrorFor($field);
    }

    public function test_a_member_with_no_gifts_gets_empty_lists(): void
    {
        $this->gift($this->other);

        $response = $this->api('GET', '/me/giving')->assertOk();

        $this->assertSame([], $response->json('data'));
        $response->assertJsonPath('meta.total', 0);
        $this->assertSame([
            'year'           => 2026,
            'month'          => '2026-11',
            'month_total'    => '0.00',
            'year_total'     => '0.00',
            'by_type'        => [],
            'all_time_total' => '0.00',
            'years'          => [],
        ], $response->json('summary'));
    }

    public function test_a_pledge_payment_appears_as_a_pledge_gift(): void
    {
        $this->pay($this->pledge($this->me), '250.00', '2026-06-01', 'Mobile Money');

        $this->api('GET', '/me/giving')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'Pledge')
            ->assertJsonPath('data.0.amount', '250.00')
            ->assertJsonPath('data.0.date', '2026-06-01')
            ->assertJsonPath('summary.by_type', [['type' => 'Pledge', 'total' => '250.00', 'count' => 1]]);
    }

    /** An older pledge payment that never became a transaction (as imported data has). */
    private function unlinkedPayment(Pledge $pledge, string $amount, string $date, array $attributes = []): int
    {
        return DB::table('pledge_payments')->insertGetId($attributes + [
            'pledge_id'      => $pledge->id,
            'amount'         => $amount,
            'payment_date'   => $date,
            'payment_method' => 'Cash',
            'transaction_id' => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function test_pledge_payments_without_a_transaction_are_included_once_as_pledge(): void
    {
        $pledge = $this->pledge($this->me);
        $linked = $this->pay($pledge, '100.00', '2026-06-01');                 // made its own Pledge transaction
        $unlinked = $this->unlinkedPayment($pledge, '40.00', '2026-02-01');
        $this->unlinkedPayment($pledge, '5.00', '2026-02-02', ['deleted_at' => now()]);   // deleted: left out
        $deletedPledge = $this->pledge($this->me);
        $this->unlinkedPayment($deletedPledge, '7.00', '2026-02-03');
        $deletedPledge->delete();                                                // its payments are left out
        $this->unlinkedPayment($this->pledge($this->other), '999.00', '2026-02-04');   // someone else's

        $response = $this->api('GET', '/me/giving')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.source', 'transaction')
            ->assertJsonPath('data.0.id', $linked->transaction_id)
            ->assertJsonPath('data.1', [
                'id'             => $unlinked,
                'source'         => 'pledge_payment',
                'reference'      => $pledge->reference,
                'receipt_number' => null,
                'date'           => '2026-02-01',
                'type'           => 'Pledge',
                'amount'         => '40.00',
                'currency'       => 'GHS',
                'payment_method' => 'Cash',
            ])
            ->assertJsonPath('summary.year_total', '140.00')
            ->assertJsonPath('summary.by_type', [['type' => 'Pledge', 'total' => '140.00', 'count' => 2]])
            ->assertJsonPath('summary.all_time_total', '140.00');

        $this->api('GET', '/me/giving?type=Pledge')->assertJsonCount(2, 'data');
        $this->api('GET', '/me/giving?type=Tithe')->assertJsonCount(0, 'data');
    }

    public function test_month_total_is_the_current_calendar_month(): void
    {
        $this->gift($this->me, ['amount' => '10.10', 'transaction_date' => '2026-11-01']);
        $this->gift($this->me, ['type' => 'Offering', 'amount' => '0.20', 'transaction_date' => '2026-11-09']);
        $this->unlinkedPayment($this->pledge($this->me), '5.00', '2026-11-03');
        $this->gift($this->me, ['amount' => '99.00', 'transaction_date' => '2026-10-31']);   // last month
        $this->gift($this->me, ['amount' => '77.00', 'transaction_date' => '2025-11-05']);   // November last year

        $this->api('GET', '/me/giving')->assertOk()
            ->assertJsonPath('summary.month', '2026-11')
            ->assertJsonPath('summary.month_total', '15.30')
            ->assertJsonPath('summary.year_total', '114.30');
    }

    public function test_receipt_numbers_are_shown(): void
    {
        $this->gift($this->me, ['receipt_number' => 'RCPT-0042']);
        $this->gift($this->me, ['receipt_number' => '']);

        $receipts = array_column($this->api('GET', '/me/giving')->assertOk()->json('data'), 'receipt_number');

        $this->assertEqualsCanonicalizing(['RCPT-0042', null], $receipts);
    }

    public function test_nobody_can_see_another_members_giving(): void
    {
        $theirGift = $this->gift($this->other, ['amount' => '321.00', 'receipt_number' => 'THEIRS-1']);
        $theirPledge = $this->pledge($this->other);
        $this->pay($theirPledge, '45.00', '2026-04-01');
        $this->unlinkedPayment($theirPledge, '12.00', '2026-04-02');
        $mine = $this->gift($this->me, ['amount' => '1.00']);

        foreach ([
            '/me/giving',
            "/me/giving?member_id={$this->other->id}",
            "/me/giving?member={$this->other->id}",
            "/me/giving?user_id={$this->other->id}",
            "/me/giving?type=Pledge&member_id={$this->other->id}",
        ] as $uri) {
            $response = $this->api('GET', $uri)->assertOk();
            $this->assertNotContains($theirGift->id, $this->ids($response), $uri);
            $this->assertStringNotContainsString('THEIRS-1', $response->getContent(), $uri);
            $this->assertStringNotContainsString($theirPledge->reference, $response->getContent(), $uri);
            $response->assertJsonPath('summary.all_time_total', '1.00');
        }

        $this->assertSame([$mine->id], $this->ids($this->api('GET', '/me/giving')));
    }

    public function test_a_login_without_a_member_record_is_refused(): void
    {
        $this->gift($this->other);
        $this->token = User::factory()->create(['member_id' => null])->createAppToken('Office PC')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->api('GET', '/me/giving')->assertForbidden()->assertJsonPath('code', 'member_link_required');
    }
}
