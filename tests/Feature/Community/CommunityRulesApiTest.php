<?php

namespace Tests\Feature\Community;

use App\Models\CommunityBan;
use App\Models\CommunityRuleAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Community\Concerns\CommunityHelpers;
use Tests\TestCase;

/** GET /community/rules, POST /community/rules/accept and who may take part (CommunityAccess). */
class CommunityRulesApiTest extends TestCase
{
    use RefreshDatabase, CommunityHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommunity();
    }

    private function tryToPost(): \Illuminate\Testing\TestResponse
    {
        return $this->api('POST', '/community/posts', ['body' => 'Hello church']);
    }

    public function test_it_needs_a_member_record_and_a_token(): void
    {
        $this->getJson('/api/v1/community/rules')->assertUnauthorized();

        $this->actAs(null);
        $this->api('GET', '/community/rules')->assertForbidden()->assertJsonPath('code', 'member_link_required');
        $this->api('GET', '/community/posts')->assertForbidden()->assertJsonPath('code', 'member_link_required');
    }

    public function test_the_rules_have_exactly_these_fields(): void
    {
        $this->signInAsMember(['date_of_birth' => '1990-05-01']);

        $response = $this->api('GET', '/community/rules')->assertOk();

        $this->assertSame(
            ['version', 'title', 'body', 'accepted', 'accepted_at', 'needs_age_confirmation', 'can_participate', 'blocked_reason'],
            array_keys($response->json('data')),
        );
        $response->assertJsonPath('data.version', '2026-11-draft')
            ->assertJsonPath('data.title', 'Community Guidelines')
            ->assertJsonPath('data.accepted', false)
            ->assertJsonPath('data.accepted_at', null)
            ->assertJsonPath('data.needs_age_confirmation', false)
            ->assertJsonPath('data.can_participate', false)
            ->assertJsonPath('data.blocked_reason', 'community_rules_required');
        $this->assertStringStartsWith('# DRAFT — for the Pastor to approve', $response->json('data.body'));
    }

    public function test_posting_needs_the_rules_accepted_first(): void
    {
        $this->signInAsMember(['date_of_birth' => '1990-05-01']);

        $this->tryToPost()->assertForbidden()->assertExactJson([
            'message'       => 'Please read and accept the community guidelines first.',
            'code'          => 'community_rules_required',
            'rules_version' => '2026-11-draft',
        ]);

        $this->api('POST', '/community/rules/accept', ['version' => '2026-11-draft'])->assertOk()->assertExactJson(['data' => [
            'version'         => '2026-11-draft',
            'accepted_at'     => '2026-11-15T10:00:00+00:00',
            'adult_confirmed' => false,
            'can_participate' => true,
            'blocked_reason'  => null,
        ]]);

        $this->tryToPost()->assertCreated();
        $this->api('GET', '/community/rules')->assertJsonPath('data.accepted', true)->assertJsonPath('data.can_participate', true);
    }

    public function test_an_old_rules_version_is_refused_and_accepting_twice_keeps_one_row(): void
    {
        $this->signInAsMember(['date_of_birth' => '1990-05-01']);

        $this->api('POST', '/community/rules/accept', ['version' => '2025-01'])
            ->assertStatus(409)->assertJsonPath('code', 'rules_outdated')->assertJsonPath('rules_version', '2026-11-draft');
        $this->api('POST', '/community/rules/accept', [])->assertUnprocessable();

        $this->api('POST', '/community/rules/accept', ['version' => '2026-11-draft'])->assertOk();
        $this->travel(1)->hours();
        $this->api('POST', '/community/rules/accept', ['version' => '2026-11-draft'])
            ->assertOk()->assertJsonPath('data.accepted_at', '2026-11-15T10:00:00+00:00');   // the first acceptance time is kept

        $this->assertSame(1, CommunityRuleAcceptance::count());
    }

    public function test_a_new_rules_version_asks_everyone_again(): void
    {
        $this->signInAsParticipant();
        config(['community.rules.version' => '2027-01']);

        $this->tryToPost()->assertForbidden()->assertJsonPath('code', 'community_rules_required')->assertJsonPath('rules_version', '2027-01');
    }

    public function test_under_18s_can_read_but_not_take_part(): void
    {
        $this->signInAsParticipant(['date_of_birth' => '2008-11-16']);   // 18 tomorrow
        $this->makePost($this->other());

        $this->api('GET', '/community/posts')->assertOk()->assertJsonCount(1, 'data');
        $this->tryToPost()->assertForbidden()->assertExactJson([
            'message'                   => 'Posting, commenting and liking are for members aged 18 and over. You can still read the community.',
            'code'                      => 'community_not_allowed',
            'age_confirmation_possible' => false,
        ]);

        // The tick doesn't override a known date of birth.
        $this->api('POST', '/community/rules/accept', ['version' => '2026-11-draft', 'adult' => true])
            ->assertOk()->assertJsonPath('data.adult_confirmed', false)->assertJsonPath('data.blocked_reason', 'community_not_allowed');

        $this->travel(1)->days();   // the 18th birthday
        $this->tryToPost()->assertCreated();
    }

    public function test_an_unknown_date_of_birth_needs_the_18_or_older_tick(): void
    {
        $this->signInAsMember();   // no date of birth

        $this->api('GET', '/community/rules')->assertJsonPath('data.needs_age_confirmation', true);
        $this->api('POST', '/community/rules/accept', ['version' => '2026-11-draft'])
            ->assertOk()->assertJsonPath('data.can_participate', false)->assertJsonPath('data.blocked_reason', 'community_not_allowed');

        $this->tryToPost()->assertForbidden()->assertJsonPath('code', 'community_not_allowed')->assertJsonPath('age_confirmation_possible', true);

        $this->api('POST', '/community/rules/accept', ['version' => '2026-11-draft', 'adult' => true])
            ->assertOk()->assertJsonPath('data.adult_confirmed', true)->assertJsonPath('data.can_participate', true);
        $this->tryToPost()->assertCreated();

        // Accepting again without the tick doesn't take it back.
        $this->api('POST', '/community/rules/accept', ['version' => '2026-11-draft'])->assertJsonPath('data.adult_confirmed', true);
    }

    public function test_a_banned_member_cannot_take_part_until_the_ban_ends(): void
    {
        $this->signInAsParticipant();
        $earlier = $this->makePost($this->me);
        CommunityBan::create(['member_id' => $this->me->id, 'reason' => 'Spam', 'banned_until' => now()->addDays(7)]);

        $this->tryToPost()->assertForbidden()->assertJsonPath('code', 'community_banned')->assertJsonPath('until', '2026-11-22T10:00:00+00:00');
        $this->api('GET', '/community/posts')->assertOk()->assertJsonPath('data.0.id', $earlier->id);   // existing posts stay visible

        $this->travel(8)->days();
        $this->tryToPost()->assertCreated();
    }

    public function test_a_ban_until_lifted_shows_no_end_date(): void
    {
        $this->signInAsParticipant();
        $ban = CommunityBan::create(['member_id' => $this->me->id, 'reason' => 'Abuse']);

        $this->tryToPost()->assertForbidden()->assertJsonPath('code', 'community_banned')->assertJsonPath('until', null);

        $ban->update(['lifted_at' => now()]);
        $this->tryToPost()->assertCreated();
    }
}
