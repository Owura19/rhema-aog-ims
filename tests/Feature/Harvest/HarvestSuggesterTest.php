<?php

namespace Tests\Feature\Harvest;

use App\Models\Harvest;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Services\Harvest\HarvestSuggester;
use App\Services\Harvest\HarvestSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HarvestSuggesterTest extends TestCase
{
    use RefreshDatabase;

    private Harvest $h2026;
    private Harvest $h2025;
    private Harvest $ministry;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));

        $this->h2026    = Harvest::create(['name' => '2026 End of Year Harvest', 'year' => 2026, 'target_amount' => 1, 'pledge_opens' => '2026-08-01', 'harvest_date' => '2026-12-12', 'status' => 'Active']);
        $this->h2025    = Harvest::create(['name' => 'Harvest 2025', 'year' => 2025, 'target_amount' => 1, 'status' => 'Active']);
        $this->ministry = Harvest::create(['name' => "Men's Ministry Day", 'year' => 2026, 'target_amount' => 1, 'pledge_opens' => '2026-07-19', 'harvest_date' => '2026-08-19', 'status' => 'Active']);
        $this->member   = Member::create(['first_name' => 'Kofi', 'last_name' => 'Mensah']);
    }

    private function tx(array $attrs = []): Transaction
    {
        return Transaction::create($attrs + [
            'type' => 'Harvest', 'category' => 'Income', 'amount' => 100, 'member_id' => $this->member->id,
            'transaction_date' => '2026-10-04', 'payment_method' => 'Cash', 'status' => 'Confirmed', 'description' => '',
        ]);
    }

    private function pledge(Harvest $harvest, float $amount, array $attrs = []): Pledge
    {
        return Pledge::create($attrs + [
            'member_id' => $this->member->id, 'pledge_purpose_id' => PledgePurpose::where('name', 'Harvest')->value('id'),
            'harvest_id' => $harvest->id, 'amount_pledged' => $amount, 'date_pledged' => '2026-08-02', 'status' => 'Active',
        ]);
    }

    private function suggest(Transaction $tx): HarvestSuggestion
    {
        return app(HarvestSuggester::class)->suggest(collect([$tx]))->get($tx->id);
    }

    // ── years in the description ──────────────────────────────────────

    public static function descriptions(): array
    {
        return [
            'four digits'            => ['HARVEST 2025', [2025]],
            'momo date first'        => ['(MOMO 24/07/26) HARVEST 2026', [2026]],
            'two digits'             => ['harvest 25', [2025]],
            'words between'          => ['Harvest pledge payment 2026', [2026]],
            'year before'            => ['2025 harvest balance', [2025]],
            'no year'                => ['HARVEST', []],
            'date only after'        => ['HARVEST 24/07/26', []],
            'no harvest word'        => ['Offering 2026', []],
            'two years'              => ['harvest 2025 and harvest 2026', [2025, 2026]],
            'same year twice'        => ['2026 HARVEST 2026', [2026]],
        ];
    }

    #[DataProvider('descriptions')]
    public function test_it_reads_the_year_next_to_harvest(string $description, array $years): void
    {
        $this->assertSame($years, HarvestSuggester::descriptionYears($description));
    }

    // ── campaign ──────────────────────────────────────────────────────

    public function test_the_description_year_beats_the_date(): void
    {
        // The real case: paid on 2026-08-30 towards the 2025 harvest
        $s = $this->suggest($this->tx(['transaction_date' => '2026-08-30', 'description' => 'HARVEST 2025']));

        $this->assertSame($this->h2025->id, $s->harvest->id);
        $this->assertSame('description', $s->basis);
        $this->assertSame(2025, $s->year);
        $this->assertContains('year_mismatch', $s->flags());
        $this->assertFalse($s->isClean());
    }

    public function test_a_matching_description_year_is_clean(): void
    {
        $this->pledge($this->h2026, 500);

        $s = $this->suggest($this->tx(['description' => '(MOMO 24/07/26) HARVEST 2026']));

        $this->assertSame($this->h2026->id, $s->harvest->id);
        $this->assertSame([], $s->flags());
        $this->assertTrue($s->isClean());
    }

    public function test_without_a_year_it_falls_back_to_the_date_and_says_so(): void
    {
        $s = $this->suggest($this->tx(['description' => 'Harvest']));

        $this->assertSame($this->h2026->id, $s->harvest->id);
        $this->assertSame('date', $s->basis);
        $this->assertContains('by_date', $s->flags());
    }

    public function test_overlapping_campaigns_prefer_the_harvest_and_are_flagged(): void
    {
        $s = $this->suggest($this->tx(['transaction_date' => '2026-08-15']));

        $this->assertSame($this->h2026->id, $s->harvest->id);
        $this->assertEqualsCanonicalizing(['by_date', 'overlap'], array_intersect(['by_date', 'overlap'], $s->flags()));
    }

    public function test_a_year_with_no_campaign_or_two_years_suggests_nothing(): void
    {
        $none = $this->suggest($this->tx(['description' => 'HARVEST 2024']));
        $this->assertNull($none->harvest);
        $this->assertContains('no_campaign', $none->flags());

        $two = $this->suggest($this->tx(['description' => 'harvest 2025 / harvest 2026']));
        $this->assertNull($two->harvest);
        $this->assertContains('two_years', $two->flags());
    }

    // ── pledge ────────────────────────────────────────────────────────

    public function test_the_members_own_pledge_is_suggested(): void
    {
        $p = $this->pledge($this->h2026, 500);

        $s = $this->suggest($this->tx(['description' => 'HARVEST 2026']));

        $this->assertSame((string) $p->id, $s->choice());
        $this->assertSame('member', collect($s->options())->firstWhere('value', (string) $p->id)['match']);
    }

    public function test_a_name_only_pledge_matches_by_name_and_is_flagged(): void
    {
        $p = $this->pledge($this->h2025, 6000, ['member_id' => null, 'pledger_name' => 'Elder MENSAH  Kofi']);
        $this->pledge($this->h2025, 10, ['member_id' => null, 'pledger_name' => 'Kofi Boateng']);   // someone else

        $s = $this->suggest($this->tx(['description' => 'HARVEST 2025']));

        $this->assertSame((string) $p->id, $s->choice());
        $this->assertContains('name_match', $s->flags());
        $this->assertSame(['name'], collect($s->options())->where('match', '!=', 'giver')->pluck('match')->all());
    }

    public function test_the_own_pledge_wins_over_a_name_match(): void
    {
        $own = $this->pledge($this->h2026, 500);
        $this->pledge($this->h2026, 300, ['member_id' => null, 'pledger_name' => 'Kofi Mensah']);

        $s = $this->suggest($this->tx(['description' => 'HARVEST 2026']));

        $this->assertSame((string) $own->id, $s->choice());
        $this->assertNotContains('name_match', $s->flags());
        $this->assertCount(3, $s->options());   // own, name match, giver
    }

    public function test_more_than_one_candidate_is_left_for_finance(): void
    {
        $this->pledge($this->h2026, 100);
        $this->pledge($this->h2026, 200);

        $s = $this->suggest($this->tx(['description' => 'HARVEST 2026']));

        $this->assertNull($s->choice());
        $this->assertContains('multiple_pledges', $s->flags());
    }

    public function test_no_pledge_suggests_a_giver_pledge_or_the_existing_one(): void
    {
        $s = $this->suggest($this->tx(['description' => 'HARVEST 2026']));
        $this->assertSame('giver', $s->choice());
        $this->assertSame('New giver pledge', collect($s->options())->firstWhere('value', 'giver')['label']);

        $giver = $this->pledge($this->h2026, 50, ['notes' => Pledge::GIVER_NOTE, 'status' => 'Fulfilled']);
        $s = $this->suggest($this->tx(['description' => 'HARVEST 2026']));
        $this->assertSame('giver', $s->choice());
        $this->assertSame("Add to giver pledge {$giver->reference}", collect($s->options())->firstWhere('value', 'giver')['label']);
    }

    public function test_overpay_and_no_payer_are_flagged(): void
    {
        $p = $this->pledge($this->h2026, 150);
        PledgePayment::create(['pledge_id' => $p->id, 'amount' => 100, 'payment_date' => '2026-09-01', 'payment_method' => 'Cash']);

        $this->assertContains('overpay', $this->suggest($this->tx(['description' => 'HARVEST 2026']))->flags());

        $s = $this->suggest($this->tx(['member_id' => null, 'payer_name' => null, 'description' => 'HARVEST 2026']));
        $this->assertContains('no_payer', $s->flags());
        $this->assertNull($s->choice());
    }

    public function test_a_payer_name_without_a_member_finds_their_pledge(): void
    {
        $p = $this->pledge($this->h2026, 500, ['member_id' => null, 'pledger_name' => 'Ama Owusu']);

        $s = $this->suggest($this->tx(['member_id' => null, 'payer_name' => 'Mrs Ama Owusu', 'description' => 'HARVEST 2026']));

        $this->assertSame((string) $p->id, $s->choice());
        $this->assertSame([], $s->flags());
    }

    public function test_options_are_given_for_every_campaign(): void
    {
        $this->pledge($this->h2025, 100);

        $s = $this->suggest($this->tx(['description' => 'HARVEST 2026']));

        $this->assertSame('giver', $s->choiceByHarvest[$this->h2026->id]);
        $this->assertNotSame('giver', $s->choiceByHarvest[$this->h2025->id]);
        $this->assertArrayHasKey($this->ministry->id, $s->optionsByHarvest);
    }
}
