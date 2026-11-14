<?php

namespace Tests\Feature\Community;

use App\Models\CommunityBan;
use App\Models\CommunityComment;
use App\Models\CommunityLike;
use App\Models\CommunityMemberBlock;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Models\CommunityRuleAcceptance;
use App\Models\Member;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The community_* tables, their unique rules and the models' helpers. */
class CommunitySchemaTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $first = 'Ama'): Member
    {
        return Member::create(['first_name' => $first, 'last_name' => 'Mensah', 'gender' => 'Female']);
    }

    public function test_a_member_likes_a_post_once(): void
    {
        $member = $this->member();
        $post = CommunityPost::create(['member_id' => $member->id, 'body' => 'Hello']);
        CommunityLike::create(['post_id' => $post->id, 'member_id' => $member->id]);

        $this->expectException(QueryException::class);
        CommunityLike::create(['post_id' => $post->id, 'member_id' => $member->id]);
    }

    public function test_a_member_reports_an_item_once(): void
    {
        $member = $this->member();
        $attributes = ['reporter_member_id' => $member->id, 'target_type' => 'post', 'target_id' => 5, 'reason' => 'spam'];
        CommunityReport::create($attributes);

        $this->expectException(QueryException::class);
        CommunityReport::create(['reason' => 'hate'] + $attributes);
    }

    public function test_a_block_pair_is_stored_once_and_hides_both_ways(): void
    {
        [$a, $b, $c] = [$this->member('A'), $this->member('B'), $this->member('C')];
        CommunityMemberBlock::create(['blocker_member_id' => $a->id, 'blocked_member_id' => $b->id]);
        CommunityMemberBlock::create(['blocker_member_id' => $c->id, 'blocked_member_id' => $a->id]);

        $hidden = CommunityMemberBlock::hiddenFrom($a->id);
        sort($hidden);
        $this->assertSame([$b->id, $c->id], $hidden);
        $this->assertSame([$a->id], CommunityMemberBlock::hiddenFrom($b->id));

        $this->expectException(QueryException::class);
        CommunityMemberBlock::create(['blocker_member_id' => $a->id, 'blocked_member_id' => $b->id]);
    }

    public function test_rules_are_accepted_once_per_version(): void
    {
        $member = $this->member();
        CommunityRuleAcceptance::create(['member_id' => $member->id, 'rules_version' => 'v1', 'accepted_at' => now()]);
        CommunityRuleAcceptance::create(['member_id' => $member->id, 'rules_version' => 'v2', 'accepted_at' => now()]);

        $this->expectException(QueryException::class);
        CommunityRuleAcceptance::create(['member_id' => $member->id, 'rules_version' => 'v1', 'accepted_at' => now()]);
    }

    public function test_active_bans(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        $member = $this->member();

        $this->assertNull(CommunityBan::activeFor($member->id));

        CommunityBan::create(['member_id' => $member->id, 'reason' => 'Over', 'banned_until' => now()->subDay()]);
        CommunityBan::create(['member_id' => $member->id, 'reason' => 'Lifted', 'lifted_at' => now()]);
        $this->assertNull(CommunityBan::activeFor($member->id));

        $week = CommunityBan::create(['member_id' => $member->id, 'reason' => 'Week', 'banned_until' => now()->addWeek()]);
        $this->assertSame($week->id, CommunityBan::activeFor($member->id)->id);

        $forever = CommunityBan::create(['member_id' => $member->id, 'reason' => 'Forever']);
        $this->assertSame($forever->id, CommunityBan::activeFor($member->id)->id);
        $this->assertTrue($forever->isActive());
    }

    public function test_deleting_a_post_soft_deletes_and_comments_belong_to_posts(): void
    {
        $member = $this->member();
        $post = CommunityPost::create(['member_id' => $member->id, 'body' => 'Hello']);
        $comment = CommunityComment::create(['post_id' => $post->id, 'member_id' => $member->id, 'body' => 'Amen']);

        $post->delete();

        $this->assertNull(CommunityPost::find($post->id));
        $this->assertTrue(CommunityPost::withTrashed()->find($post->id)->trashed());
        $this->assertSame($post->id, $comment->fresh()->post->id);   // withTrashed
    }

    public function test_the_rules_file_exists_with_the_configured_version(): void
    {
        $rules = file_get_contents(config('community.rules.path'));

        $this->assertStringStartsWith('# DRAFT — for the Pastor to approve', $rules);
        $this->assertStringContainsString(config('community.rules.version'), $rules);
        $this->assertSame('2026-11-draft', config('community.rules.version'));
    }
}
