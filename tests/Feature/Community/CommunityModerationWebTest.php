<?php

namespace Tests\Feature\Community;

use App\Models\CommunityBan;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostPhoto;
use App\Models\CommunityReport;
use App\Models\Member;
use App\Models\User;
use App\Support\Community\PhotoProcessor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The web moderation queue ('moderate community': Super Admin and Pastor). */
class CommunityModerationWebTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['Super Admin', 'Pastor', 'Office Staff', 'HOD', 'Member'];

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        Storage::fake('community');
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (self::ROLES as $role) {
            $this->users[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    private function member(string $first = 'Kofi'): Member
    {
        return Member::create(['first_name' => $first, 'last_name' => 'Owusu', 'gender' => 'Male']);
    }

    private function reported(CommunityPost|CommunityComment $item, int $times = 1): void
    {
        foreach (range(1, $times) as $i) {
            CommunityReport::create([
                'reporter_member_id' => $this->member("Reporter{$i}")->id,
                'target_type'        => $item instanceof CommunityPost ? 'post' : 'comment',
                'target_id'          => $item->id,
                'reason'             => 'harassment',
            ]);
        }
    }

    public function test_only_moderators_reach_the_pages(): void
    {
        $author = $this->member();
        $post = CommunityPost::create(['member_id' => $author->id, 'body' => 'Hello']);

        foreach (self::ROLES as $role) {
            $allowed = in_array($role, ['Super Admin', 'Pastor'], true);
            $expected = $allowed ? 200 : 403;
            $this->as($role)->get(route('community-moderation.index'))->assertStatus($expected);
            $this->as($role)->get(route('community-moderation.show', ['post', $post->id]))->assertStatus($expected);
            $this->as($role)->get(route('community-moderation.member', $author))->assertStatus($expected);
            $this->as($role)->post(route('community-moderation.hide', ['post', $post->id]), ['reason' => 'x'])->assertStatus($allowed ? 302 : 403);
        }
    }

    public function test_the_sidebar_link_is_only_for_moderators(): void
    {
        $this->as('Pastor')->get(route('dashboard'))->assertSee(route('community-moderation.index'));
        $this->as('Office Staff')->get(route('dashboard'))->assertDontSee(route('community-moderation.index'));
    }

    public function test_the_queue_lists_reported_items_auto_hidden_first(): void
    {
        $author = $this->member();
        $once = CommunityPost::create(['member_id' => $author->id, 'body' => 'Reported once']);
        $auto = CommunityPost::create(['member_id' => $author->id, 'body' => 'Auto hidden', 'hidden_at' => now(), 'hidden_automatically' => true]);
        $comment = CommunityComment::create(['post_id' => $once->id, 'member_id' => $author->id, 'body' => 'Rude comment']);
        $quiet = CommunityPost::create(['member_id' => $author->id, 'body' => 'Nobody reported this']);
        $this->reported($once);
        $this->reported($auto, 3);
        $this->reported($comment, 2);

        $this->as('Pastor')->get(route('community-moderation.index'))
            ->assertOk()
            ->assertSeeInOrder(['Auto hidden', 'Rude comment', 'Reported once'])
            ->assertSee('Hidden automatically')
            ->assertSee('Harassment or bullying (3)')
            ->assertDontSee('Nobody reported this');
    }

    public function test_hiding_actions_reports_and_unhiding_dismisses_new_ones(): void
    {
        $post = CommunityPost::create(['member_id' => $this->member()->id, 'body' => 'Bad words']);
        $this->reported($post, 2);

        $this->as('Pastor')->post(route('community-moderation.hide', ['post', $post->id]), [])->assertSessionHasErrors('reason');
        $this->as('Pastor')->post(route('community-moderation.hide', ['post', $post->id]), ['reason' => 'Insults'])->assertSessionHas('success');

        $post->refresh();
        $this->assertTrue($post->isHidden());
        $this->assertFalse($post->hidden_automatically);
        $this->assertSame($this->users['Pastor']->id, $post->hidden_by);
        $this->assertSame('Insults', $post->hide_reason);
        $this->assertSame(2, CommunityReport::where('status', 'actioned')->where('reviewed_by', $this->users['Pastor']->id)->count());

        $this->reported($post);   // a later report
        $this->as('Super Admin')->post(route('community-moderation.unhide', ['post', $post->id]))->assertSessionHas('success');

        $post->refresh();
        $this->assertFalse($post->isHidden());
        $this->assertNull($post->hide_reason);
        $this->assertSame(1, CommunityReport::where('status', 'dismissed')->count());
        $this->assertSame(0, CommunityReport::open()->count());
    }

    public function test_dismissing_reports_keeps_the_item_visible(): void
    {
        $comment = CommunityComment::create([
            'post_id'   => CommunityPost::create(['member_id' => $this->member()->id, 'body' => 'P'])->id,
            'member_id' => $this->member('Esi')->id,
            'body'      => 'Fine comment',
        ]);
        $this->reported($comment, 2);

        $this->as('Pastor')->post(route('community-moderation.dismiss', ['comment', $comment->id]))->assertSessionHas('success', '2 report(s) dismissed.');

        $this->assertFalse($comment->fresh()->isHidden());
        $this->assertSame(0, CommunityReport::open()->count());
        $this->as('Pastor')->get(route('community-moderation.index'))->assertDontSee('Fine comment');
    }

    public function test_the_review_page_shows_the_item_photos_and_reports(): void
    {
        $post = CommunityPost::create(['member_id' => $this->member()->id, 'body' => "Line one\n<b>bold</b>"]);
        $stored = app(PhotoProcessor::class)->store(UploadedFile::fake()->image('p.jpg', 300, 200));
        $photo = CommunityPostPhoto::create($stored + ['post_id' => $post->id, 'position' => 0]);
        CommunityReport::create(['reporter_member_id' => $this->member('Esi')->id, 'target_type' => 'post', 'target_id' => $post->id, 'reason' => 'privacy', 'details' => 'My phone number']);

        $this->as('Pastor')->get(route('community-moderation.show', ['post', $post->id]))
            ->assertOk()
            ->assertSee('Line one<br />', false)
            ->assertDontSee('<b>bold</b>', false)
            ->assertSee("Shares someone&#039;s private information", false)
            ->assertSee('My phone number')
            ->assertSee(route('community-moderation.photo', [$photo, 'thumb']));

        $this->as('Pastor')->get(route('community-moderation.photo', [$photo, 'full']))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->as('Office Staff')->get(route('community-moderation.photo', [$photo, 'full']))->assertForbidden();
        $this->as('Pastor')->get(route('community-moderation.show', ['post', 999999]))->assertNotFound();
    }

    public function test_banning_until_a_date_or_until_lifted(): void
    {
        $member = $this->member();

        $this->as('Pastor')->post(route('community-moderation.ban', $member), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->as('Pastor')->post(route('community-moderation.ban', $member), ['reason' => 'Spam', 'until' => '2026-11-15'])->assertSessionHasErrors('until');

        $this->as('Pastor')->post(route('community-moderation.ban', $member), ['reason' => 'Spam links', 'until' => '2026-11-22'])->assertSessionHas('success');
        $ban = CommunityBan::sole();
        $this->assertSame('2026-11-22 23:59:59', $ban->banned_until->format('Y-m-d H:i:s'));
        $this->assertSame($this->users['Pastor']->id, $ban->banned_by);
        $this->assertTrue($ban->isActive());

        $this->as('Pastor')->get(route('community-moderation.member', $member))->assertOk()->assertSee('until 22 Nov 2026');
        $this->as('Pastor')->get(route('community-moderation.index'))->assertSee('Spam links');

        $this->as('Super Admin')->post(route('community-moderation.lift-ban', $ban))->assertSessionHas('success');
        $ban->refresh();
        $this->assertFalse($ban->isActive());
        $this->assertSame($this->users['Super Admin']->id, $ban->lifted_by);

        $this->as('Pastor')->post(route('community-moderation.ban', $member), ['reason' => 'Repeated abuse'])->assertSessionHas('success');
        $this->assertNull(CommunityBan::activeFor($member->id)->banned_until);
    }
}
