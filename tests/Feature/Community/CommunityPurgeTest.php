<?php

namespace Tests\Feature\Community;

use App\Models\CommunityComment;
use App\Models\CommunityLike;
use App\Models\CommunityPost;
use App\Models\CommunityPostPhoto;
use App\Models\CommunityReport;
use App\Models\Member;
use App\Support\Community\PhotoProcessor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** community:purge-deleted (30 days) and the retired old web Community. */
class CommunityPurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        Storage::fake('community');
    }

    private function member(string $first = 'Ama'): Member
    {
        return Member::create(['first_name' => $first, 'last_name' => 'Mensah', 'gender' => 'Female']);
    }

    private function postWithPhoto(Member $author): array
    {
        $post = CommunityPost::create(['member_id' => $author->id, 'body' => 'Hi']);
        $stored = app(PhotoProcessor::class)->store(UploadedFile::fake()->image('p.jpg', 200, 100));
        $photo = CommunityPostPhoto::create($stored + ['post_id' => $post->id, 'position' => 0]);

        return [$post, $photo];
    }

    public function test_content_deleted_over_30_days_ago_is_removed_for_good(): void
    {
        $author = $this->member();
        $other = $this->member('Kofi');

        [$old, $oldPhoto] = $this->postWithPhoto($author);
        $oldComment = CommunityComment::create(['post_id' => $old->id, 'member_id' => $other->id, 'body' => 'Amen']);
        CommunityLike::create(['post_id' => $old->id, 'member_id' => $other->id]);
        CommunityReport::create(['reporter_member_id' => $other->id, 'target_type' => 'post', 'target_id' => $old->id, 'reason' => 'spam']);
        CommunityReport::create(['reporter_member_id' => $author->id, 'target_type' => 'comment', 'target_id' => $oldComment->id, 'reason' => 'spam']);

        [$recent, $recentPhoto] = $this->postWithPhoto($author);
        [$live, $livePhoto] = $this->postWithPhoto($author);
        $hidden = CommunityPost::create(['member_id' => $author->id, 'body' => 'Hidden long ago', 'hidden_at' => now()->subDays(60)]);
        $oldLoneComment = CommunityComment::create(['post_id' => $live->id, 'member_id' => $other->id, 'body' => 'Old']);
        $recentComment = CommunityComment::create(['post_id' => $live->id, 'member_id' => $other->id, 'body' => 'Recent']);

        $this->travelTo(Carbon::parse('2026-10-15 09:00:00'));   // 31 days ago
        $old->delete();
        $oldLoneComment->delete();
        $this->travelTo(Carbon::parse('2026-10-17 10:00:00'));   // 29 days ago
        $recent->delete();
        $recentComment->delete();
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $this->artisan('community:purge-deleted')->assertSuccessful()
            ->expectsOutputToContain('Purged 1 post(s) and 1 comment(s)');

        $this->assertNull(CommunityPost::withTrashed()->find($old->id));
        $this->assertNull(CommunityComment::withTrashed()->find($oldComment->id));
        $this->assertNull(CommunityComment::withTrashed()->find($oldLoneComment->id));
        $this->assertSame(0, CommunityLike::count());
        $this->assertSame(0, CommunityReport::count());
        $this->assertNull(CommunityPostPhoto::find($oldPhoto->id));
        Storage::disk('community')->assertMissing([$oldPhoto->path, $oldPhoto->thumb_path]);

        $this->assertNotNull(CommunityPost::withTrashed()->find($recent->id));
        $this->assertNotNull(CommunityComment::withTrashed()->find($recentComment->id));
        $this->assertNotNull(CommunityPost::find($hidden->id));
        Storage::disk('community')->assertExists([$recentPhoto->path, $livePhoto->path]);
    }

    public function test_it_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'community:purge-deleted'));

        $this->assertCount(1, $events);
        $this->assertSame('30 2 * * *', $events->first()->expression);
    }

    public function test_the_old_web_community_is_retired(): void
    {
        foreach (['community.index', 'community.store', 'community.destroy', 'community.like', 'community.comment', 'community.delete-comment', 'community.pin'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} should be gone");
        }

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)->get('/community')->assertNotFound();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('fa-globe', false);
    }
}
