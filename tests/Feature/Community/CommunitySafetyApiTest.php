<?php

namespace Tests\Feature\Community;

use App\Models\CommunityBan;
use App\Models\CommunityMemberBlock;
use App\Models\CommunityReport;
use App\Support\Community\Moderation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Community\Concerns\CommunityHelpers;
use Tests\TestCase;

/** POST /community/reports (with auto-hide) and /community/blocks. */
class CommunitySafetyApiTest extends TestCase
{
    use RefreshDatabase, CommunityHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommunity();
        $this->signInAsParticipant();
    }

    private function report(string $type, int $id, string $reason = 'spam', array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->api('POST', '/community/reports', ['type' => $type, 'id' => $id, 'reason' => $reason] + $extra);
    }

    // ── reports ────────────────────────────────────────────────────────

    public function test_a_report_is_received_once_per_member(): void
    {
        $post = $this->makePost($this->other());

        $first = $this->report('post', $post->id, 'harassment', ['details' => '  Rude to a sister.  '])->assertCreated();
        $report = CommunityReport::sole();
        $first->assertExactJson(['data' => ['id' => $report->id, 'status' => 'received']]);
        $this->assertSame('Rude to a sister.', $report->details);
        $this->assertSame(CommunityReport::STATUS_OPEN, $report->status);

        $this->report('post', $post->id, 'hate')->assertOk()->assertExactJson(['data' => ['id' => $report->id, 'status' => 'received']]);
        $this->assertSame(1, CommunityReport::count());
        $this->assertSame(1, $post->fresh()->report_count);
    }

    public function test_report_validation(): void
    {
        $post = $this->makePost($this->other());
        $mine = $this->makePost($this->me);

        $this->report('post', $post->id, 'boring')->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->report('video', $post->id)->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->report('post', $post->id, 'other', ['details' => str_repeat('a', 501)])->assertUnprocessable();
        $this->report('post', $mine->id)->assertUnprocessable()->assertJsonPath('errors.id.0', "You can't report your own post.");
        $this->report('post', 999999)->assertNotFound();

        $this->assertSame(0, CommunityReport::count());
    }

    public function test_three_different_members_hide_an_item_automatically(): void
    {
        $author = $this->other('Kofi');
        $post = $this->makePost($author);
        $comment = $this->makeComment($post, $author);

        foreach (['Esi', 'Yaw', 'Abena'] as $i => $name) {
            $reporter = $this->other($name, ['date_of_birth' => '1990-01-01']);
            $this->actAs($reporter);
            $this->report('comment', $comment->id, 'hate')->assertCreated();   // first: once the post is hidden its comments can't be reached
            $this->report('post', $post->id)->assertCreated();

            $hidden = $i === 2;
            $this->assertSame($hidden, $post->fresh()->isHidden(), "post hidden after report " . ($i + 1));
            $this->assertSame($hidden, $comment->fresh()->isHidden());
        }

        $post->refresh();
        $this->assertTrue($post->hidden_automatically);
        $this->assertNull($post->hidden_by);
        $this->assertSame(Moderation::AUTO_HIDE_REASON, $post->hide_reason);
        $this->assertSame(3, $post->report_count);
        $this->assertSame(3, CommunityReport::open()->for('post', $post->id)->count());   // still open for the moderators

        $this->api('GET', "/community/posts/{$post->id}")->assertNotFound();   // gone for others...
        $this->actAs($author);
        $this->api('GET', "/community/posts/{$post->id}")->assertOk()->assertJsonPath('data.status', 'hidden');   // ...but not its author
    }

    public function test_minors_and_banned_members_can_still_report(): void
    {
        $post = $this->makePost($this->other());
        $minor = $this->other('Kwame', ['date_of_birth' => '2012-01-01']);
        $this->actAs($minor);
        $this->report('post', $post->id)->assertCreated();

        $banned = $this->other('Yaw', ['date_of_birth' => '1980-01-01']);
        CommunityBan::create(['member_id' => $banned->id, 'reason' => 'Spam']);
        $this->actAs($banned);
        $this->report('post', $post->id)->assertCreated();
    }

    public function test_reports_are_rate_limited(): void
    {
        $author = $this->other();
        $posts = array_map(fn () => $this->makePost($author), range(1, 11));

        foreach (array_slice($posts, 0, 10) as $post) {
            $this->report('post', $post->id)->assertCreated();
        }
        $this->report('post', $posts[10]->id)->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    // ── blocks ─────────────────────────────────────────────────────────

    public function test_blocking_and_unblocking(): void
    {
        $kofi = $this->other('Kofi');
        $post = $this->makePost($kofi);

        $this->api('POST', "/community/blocks/{$kofi->id}")->assertCreated()
            ->assertExactJson(['data' => ['member_id' => $kofi->id, 'blocked_at' => '2026-11-15T10:00:00+00:00']]);
        $this->api('POST', "/community/blocks/{$kofi->id}")->assertOk();
        $this->assertSame(1, CommunityMemberBlock::count());

        $this->api('GET', '/community/blocks')->assertOk()->assertExactJson(['data' => [
            ['member_id' => $kofi->id, 'display_name' => 'Kofi O.', 'blocked_at' => '2026-11-15T10:00:00+00:00'],
        ]]);
        $this->api('GET', '/community/posts')->assertJsonCount(0, 'data');

        $this->api('DELETE', "/community/blocks/{$kofi->id}")->assertNoContent();
        $this->api('DELETE', "/community/blocks/{$kofi->id}")->assertNoContent();
        $this->api('GET', '/community/posts')->assertJsonPath('data.0.id', $post->id);
    }

    public function test_the_blocked_member_no_longer_sees_the_blocker(): void
    {
        $kofi = $this->other('Kofi', ['date_of_birth' => '1980-01-01']);
        $mine = $this->makePost($this->me);
        $this->api('POST', "/community/blocks/{$kofi->id}")->assertCreated();

        $this->actAs($kofi);
        $this->api('GET', '/community/posts')->assertJsonCount(0, 'data');
        $this->api('GET', "/community/posts/{$mine->id}")->assertNotFound();
        $this->api('GET', '/community/blocks')->assertExactJson(['data' => []]);   // only blocks they made
    }

    public function test_block_validation(): void
    {
        $this->api('POST', "/community/blocks/{$this->me->id}")->assertUnprocessable()->assertJsonPath('errors.member_id.0', "You can't block yourself.");
        $this->api('POST', '/community/blocks/999999')->assertNotFound();
    }
}
