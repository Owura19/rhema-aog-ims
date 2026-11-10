<?php

namespace Tests\Feature\Api\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame(['id', 'reference', 'date', 'type', 'amount', 'currency', 'payment_method'], array_keys($response->json('data.0')));
        $response->assertJsonPath('data.0', [
            'id'             => $offering->id,
            'reference'      => $offering->reference,
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
}
