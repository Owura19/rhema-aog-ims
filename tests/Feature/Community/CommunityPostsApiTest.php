<?php

namespace Tests\Feature\Community;

use App\Events\Community\CommunityPostCommented;
use App\Events\Community\CommunityPostLiked;
use App\Models\CommunityComment;
use App\Models\CommunityLike;
use App\Models\CommunityMemberBlock;
use App\Models\CommunityPost;
use App\Models\CommunityPostPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Community\Concerns\CommunityHelpers;
use Tests\TestCase;

/** The feed, posts, likes and comments. */
class CommunityPostsApiTest extends TestCase
{
    use RefreshDatabase, CommunityHelpers;

    private const POST_KEYS = [
        'id', 'author', 'body', 'photos', 'created_at', 'edited_at', 'like_count', 'comment_count', 'liked_by_me', 'mine', 'status',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommunity();
        $this->signInAsParticipant(['first_name' => 'Ama', 'last_name' => 'Mensah']);
    }

    /** @return list<int> */
    private function postIds(string $query = ''): array
    {
        return array_column($this->api('GET', '/community/posts' . $query)->assertOk()->json('data'), 'id');
    }

    // ── posting ────────────────────────────────────────────────────────

    public function test_a_text_post_has_exactly_these_fields_and_no_contact_details(): void
    {
        $response = $this->api('POST', '/community/posts', ['body' => "  Thank God for Sunday!\nSee you all.  "])->assertCreated();

        $post = CommunityPost::sole();
        $response->assertExactJson(['data' => [
            'id'            => $post->id,
            'author'        => ['id' => $this->me->id, 'display_name' => 'Ama M.', 'initials' => 'AM'],
            'body'          => "Thank God for Sunday!\nSee you all.",
            'photos'        => [],
            'created_at'    => '2026-11-15T10:00:00+00:00',
            'edited_at'     => null,
            'like_count'    => 0,
            'comment_count' => 0,
            'liked_by_me'   => false,
            'mine'          => true,
            'status'        => 'visible',
        ]]);
        $this->assertStringNotContainsString('0241234567', $response->getContent());
        $this->assertStringNotContainsString('+233', $response->getContent());
    }

    public function test_photos_are_processed_ordered_and_served_by_signed_url(): void
    {
        $response = $this->api('POST', '/community/posts', [
            'photos' => [UploadedFile::fake()->image('a.jpg', 2400, 1800), UploadedFile::fake()->image('b.png', 400, 300)],
        ])->assertCreated();

        $response->assertJsonPath('data.body', null)
            ->assertJsonPath('data.photos.0.width', 1600)
            ->assertJsonPath('data.photos.0.height', 1200)
            ->assertJsonPath('data.photos.1.width', 400);
        $this->assertSame(['id', 'width', 'height', 'url', 'thumb_url'], array_keys($response->json('data.photos.0')));
        $this->assertSame([0, 1], CommunityPostPhoto::orderBy('id')->pluck('position')->all());

        $this->get($response->json('data.photos.0.url'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get($response->json('data.photos.1.thumb_url'))->assertOk();
        $this->assertCount(4, Storage::disk('community')->allFiles());
    }

    public function test_post_validation(): void
    {
        config(['community.rate_limits.posts' => [100, 10]]);   // every attempt counts towards the limit
        $this->api('POST', '/community/posts', [])->assertUnprocessable()->assertJsonPath('errors.body.0', 'Write something or add a photo.');
        $this->api('POST', '/community/posts', ['body' => '   '])->assertUnprocessable();
        $this->api('POST', '/community/posts', ['body' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('body');

        $five = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg", 50, 50), range(1, 5));
        $this->api('POST', '/community/posts', ['photos' => $five])->assertUnprocessable()->assertJsonPath('errors.photos.0', 'A post can have at most 4 photos.');

        $this->api('POST', '/community/posts', ['photos' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]])
            ->assertUnprocessable()->assertJsonPath('errors', ['photos.0' => ['Photos must be JPEG, PNG or WebP images.']]);
        $this->api('POST', '/community/posts', ['photos' => [UploadedFile::fake()->image('big.jpg', 50, 50)->size(9000)]])
            ->assertUnprocessable()->assertJsonPath('errors', ['photos.0' => ['Each photo must be 8 MB or smaller.']]);

        $this->assertSame(0, CommunityPost::count());
        $this->assertSame([], Storage::disk('community')->allFiles());
    }

    public function test_a_bad_photo_leaves_nothing_behind(): void
    {
        config(['community.photos.max_megapixels' => 1]);

        $this->api('POST', '/community/posts', [
            'body'   => 'Two photos',
            'photos' => [UploadedFile::fake()->image('ok.jpg', 100, 100), UploadedFile::fake()->image('huge.jpg', 1100, 1000)],
        ])->assertUnprocessable()->assertJsonPath('errors', ['photos.1' => ['This photo is too large. Please choose a smaller one.']]);

        $this->assertSame(0, CommunityPost::count());
        $this->assertSame([], Storage::disk('community')->allFiles());   // the first photo's files were removed
    }

    public function test_posting_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->api('POST', '/community/posts', ['body' => "Post {$i}"])->assertCreated();
        }

        $this->api('POST', '/community/posts', ['body' => 'Post 6'])->assertStatus(429)->assertJsonPath('code', 'too_many_requests');

        $this->travel(11)->minutes();
        $this->api('POST', '/community/posts', ['body' => 'Post 7'])->assertCreated();
    }

    // ── the feed ───────────────────────────────────────────────────────

    public function test_the_feed_is_newest_first_with_a_cursor(): void
    {
        $other = $this->other();
        $ids = array_map(fn ($i) => $this->makePost($other, ['body' => "Post {$i}"])->id, range(1, 5));

        $first = $this->api('GET', '/community/posts?limit=2')->assertOk();
        $this->assertSame([$ids[4], $ids[3]], array_column($first->json('data'), 'id'));
        $first->assertJsonPath('meta.next_before_id', $ids[3]);
        $this->assertSame(self::POST_KEYS, array_keys($first->json('data.0')));

        $last = $this->api("GET", "/community/posts?limit=2&before_id={$ids[1]}")->assertOk();
        $this->assertSame([$ids[0]], array_column($last->json('data'), 'id'));
        $last->assertJsonPath('meta.next_before_id', null);

        $this->api('GET', '/community/posts?limit=31')->assertUnprocessable();
        $this->api('GET', '/community/posts?before_id=abc')->assertUnprocessable();
    }

    public function test_hidden_posts_show_only_to_their_author(): void
    {
        $other = $this->other();
        $mineHidden = $this->makePost($this->me, ['hidden_at' => now()]);
        $theirsHidden = $this->makePost($other, ['hidden_at' => now()]);
        $visible = $this->makePost($other);

        $this->assertSame([$visible->id, $mineHidden->id], $this->postIds());
        $this->api('GET', "/community/posts/{$mineHidden->id}")->assertOk()->assertJsonPath('data.status', 'hidden');
        $this->api('GET', "/community/posts/{$theirsHidden->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_blocks_hide_posts_both_ways(): void
    {
        $blockedByMe = $this->other('Kofi');
        $blockedMe = $this->other('Yaw');
        $fine = $this->other('Esi');
        $a = $this->makePost($blockedByMe);
        $b = $this->makePost($blockedMe);
        $c = $this->makePost($fine);
        CommunityMemberBlock::create(['blocker_member_id' => $this->me->id, 'blocked_member_id' => $blockedByMe->id]);
        CommunityMemberBlock::create(['blocker_member_id' => $blockedMe->id, 'blocked_member_id' => $this->me->id]);

        $this->assertSame([$c->id], $this->postIds());
        $this->api('GET', "/community/posts/{$a->id}")->assertNotFound();
        $this->api('GET', "/community/posts/{$b->id}")->assertNotFound();
    }

    public function test_deleted_posts_and_posts_of_deleted_members_are_gone(): void
    {
        $leaver = $this->other('Kofi');
        $theirs = $this->makePost($leaver);
        $deleted = $this->makePost($this->other('Esi'));
        $deleted->delete();
        $leaver->delete();

        $this->assertSame([], $this->postIds());
        $this->api('GET', "/community/posts/{$theirs->id}")->assertNotFound();
    }

    // ── deleting ───────────────────────────────────────────────────────

    public function test_members_delete_only_their_own_posts(): void
    {
        $mine = $this->makePost($this->me);
        $theirs = $this->makePost($this->other());

        $this->api('DELETE', "/community/posts/{$theirs->id}")->assertForbidden();
        $this->api('DELETE', "/community/posts/{$mine->id}")->assertNoContent();
        $this->api('DELETE', "/community/posts/{$mine->id}")->assertNotFound();
        $this->api('DELETE', '/community/posts/999999')->assertNotFound();

        $this->assertTrue(CommunityPost::withTrashed()->find($mine->id)->trashed());
        $this->assertSame([$theirs->id], $this->postIds());
    }

    // ── likes ──────────────────────────────────────────────────────────

    public function test_likes_are_safe_to_repeat_and_counted(): void
    {
        Event::fake([CommunityPostLiked::class]);
        $post = $this->makePost($this->other());

        $this->api('POST', "/community/posts/{$post->id}/like")->assertOk()->assertExactJson(['data' => ['liked' => true, 'like_count' => 1]]);
        $this->api('POST', "/community/posts/{$post->id}/like")->assertOk()->assertExactJson(['data' => ['liked' => true, 'like_count' => 1]]);
        Event::assertDispatchedTimes(CommunityPostLiked::class, 1);

        $this->api('GET', "/community/posts/{$post->id}")->assertJsonPath('data.liked_by_me', true)->assertJsonPath('data.like_count', 1);

        $this->api('DELETE', "/community/posts/{$post->id}/like")->assertOk()->assertExactJson(['data' => ['liked' => false, 'like_count' => 0]]);
        $this->api('DELETE', "/community/posts/{$post->id}/like")->assertOk()->assertExactJson(['data' => ['liked' => false, 'like_count' => 0]]);
        $this->assertSame(0, CommunityLike::count());
    }

    public function test_liking_needs_access_but_unliking_does_not(): void
    {
        $post = $this->makePost($this->other());
        $this->api('POST', "/community/posts/{$post->id}/like")->assertOk();

        $minor = $this->other('Kwame', ['date_of_birth' => '2012-01-01']);
        $this->accept($minor);
        $this->actAs($minor);
        $this->api('POST', "/community/posts/{$post->id}/like")->assertForbidden()->assertJsonPath('code', 'community_not_allowed');
        $this->api('DELETE', "/community/posts/{$post->id}/like")->assertOk()->assertJsonPath('data.like_count', 1);
    }

    public function test_you_cannot_like_what_you_cannot_see(): void
    {
        $post = $this->makePost($this->other(), ['hidden_at' => now()]);

        $this->api('POST', "/community/posts/{$post->id}/like")->assertNotFound();
    }

    // ── comments ───────────────────────────────────────────────────────

    public function test_comments_are_added_counted_and_announced(): void
    {
        Event::fake([CommunityPostCommented::class]);
        $post = $this->makePost($this->other());

        $response = $this->api('POST', "/community/posts/{$post->id}/comments", ['body' => '  Amen!  '])->assertCreated();

        $comment = CommunityComment::sole();
        $response->assertExactJson(['data' => [
            'id'         => $comment->id,
            'post_id'    => $post->id,
            'author'     => ['id' => $this->me->id, 'display_name' => 'Ama M.', 'initials' => 'AM'],
            'body'       => 'Amen!',
            'created_at' => '2026-11-15T10:00:00+00:00',
            'mine'       => true,
            'status'     => 'visible',
        ]]);
        $this->assertSame(1, $post->fresh()->comment_count);
        Event::assertDispatched(CommunityPostCommented::class, fn ($e) => $e->comment->is($comment));

        $this->api('POST', "/community/posts/{$post->id}/comments", ['body' => ' '])->assertUnprocessable();
        $this->api('POST', "/community/posts/{$post->id}/comments", ['body' => str_repeat('a', 1001)])->assertUnprocessable();
    }

    public function test_a_post_shows_its_latest_comments_oldest_first_and_pages_back(): void
    {
        config(['community.feed.comments_on_post' => 2]);
        $post = $this->makePost($this->other());
        $ids = array_map(fn ($i) => $this->makeComment($post, $this->me, ['body' => "C{$i}"])->id, range(1, 3));

        $show = $this->api('GET', "/community/posts/{$post->id}")->assertOk();
        $this->assertSame([$ids[1], $ids[2]], array_column($show->json('data.comments.data'), 'id'));
        $show->assertJsonPath('data.comments.next_before_id', $ids[1])->assertJsonPath('data.comment_count', 3);

        $older = $this->api('GET', "/community/posts/{$post->id}/comments?before_id={$ids[1]}")->assertOk();
        $this->assertSame([$ids[0]], array_column($older->json('data'), 'id'));
        $older->assertJsonPath('meta.next_before_id', null);
    }

    public function test_hidden_and_blocked_comments_are_left_out(): void
    {
        $post = $this->makePost($this->other('Esi'));
        $blocked = $this->other('Kofi');
        CommunityMemberBlock::create(['blocker_member_id' => $this->me->id, 'blocked_member_id' => $blocked->id]);
        $fromBlocked = $this->makeComment($post, $blocked);
        $hiddenTheirs = $this->makeComment($post, $this->other('Yaw'), ['hidden_at' => now()]);
        $hiddenMine = $this->makeComment($post, $this->me, ['hidden_at' => now()]);
        $fine = $this->makeComment($post, $this->other('Abena'));

        $comments = $this->api('GET', "/community/posts/{$post->id}")->json('data.comments.data');

        $this->assertSame([$hiddenMine->id, $fine->id], array_column($comments, 'id'));
        $this->assertSame('hidden', $comments[0]['status']);
        $this->assertNotContains($fromBlocked->id, array_column($comments, 'id'));
        $this->assertNotContains($hiddenTheirs->id, array_column($comments, 'id'));
    }

    public function test_members_delete_only_their_own_comments(): void
    {
        $post = $this->makePost($this->other());
        $mine = $this->makeComment($post, $this->me);
        $theirs = $this->makeComment($post, $this->other('Esi'));

        $this->api('DELETE', "/community/comments/{$theirs->id}")->assertForbidden();
        $this->api('DELETE', "/community/comments/{$mine->id}")->assertNoContent();
        $this->api('DELETE', "/community/comments/{$mine->id}")->assertNotFound();

        $this->assertSame(1, $post->fresh()->comment_count);
        $this->assertTrue(CommunityComment::withTrashed()->find($mine->id)->trashed());
    }

    public function test_commenting_needs_access(): void
    {
        $post = $this->makePost($this->other());
        $newcomer = $this->other('Yaw', ['date_of_birth' => '1980-01-01']);
        $this->actAs($newcomer);

        $this->api('POST', "/community/posts/{$post->id}/comments", ['body' => 'Hi'])->assertForbidden()->assertJsonPath('code', 'community_rules_required');
        $this->api('GET', "/community/posts/{$post->id}")->assertOk();   // reading is fine
    }
}
