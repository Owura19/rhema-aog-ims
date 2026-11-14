<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/announcements. Now is 2026-11-16 10:00 UTC. */
class AnnouncementApiTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->signInAsMember();
    }

    private function live(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'title'     => 'Harvest Sunday',
            'body'      => 'Bring your harvest offering.',
            'status'    => Announcement::STATUS_PUBLISHED,
            'starts_at' => now()->subHour(),
        ]);
    }

    public function test_access(): void
    {
        $this->getJson('/api/v1/announcements')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');

        $this->token = User::factory()->create(['must_change_password' => true])->createAppToken('Phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->api('GET', '/announcements')->assertForbidden()->assertJsonPath('code', 'password_change_required');

        $this->token = User::factory()->create(['member_id' => null])->createAppToken('Office PC')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->api('GET', '/announcements')->assertOk();   // no member record needed
    }

    public function test_an_announcement_has_exactly_these_fields(): void
    {
        $announcement = $this->live([
            'link_url'  => 'https://rhemaaog-ims.com/harvest',
            'is_pinned' => true,
            'starts_at' => '2026-11-15 08:00:00',
            'ends_at'   => '2026-11-23 12:00:00',
        ]);

        $this->api('GET', '/announcements')->assertOk()->assertExactJson(['data' => [[
            'id'        => $announcement->id,
            'title'     => 'Harvest Sunday',
            'body'      => 'Bring your harvest offering.',
            'link_url'  => 'https://rhemaaog-ims.com/harvest',
            'is_pinned' => true,
            'starts_at' => '2026-11-15T08:00:00+00:00',
            'ends_at'   => '2026-11-23T12:00:00+00:00',
        ]]]);
    }

    public function test_only_live_announcements_are_shown(): void
    {
        $live = $this->live();
        $endsSoon = $this->live(['ends_at' => now()->addMinute()]);
        $this->live(['status' => Announcement::STATUS_DRAFT]);
        $this->live(['starts_at' => now()->addMinute()]);   // scheduled
        $this->live(['ends_at' => now()]);                  // just ended
        $this->live()->delete();                             // deleted

        $ids = array_column($this->api('GET', '/announcements')->assertOk()->json('data'), 'id');

        $this->assertEqualsCanonicalizing([$live->id, $endsSoon->id], $ids);
    }

    public function test_pinned_first_then_newest_start_at_most_10(): void
    {
        $old = $this->live(['starts_at' => now()->subDays(5)]);
        $new = $this->live(['starts_at' => now()->subDay()]);
        $pinnedOld = $this->live(['starts_at' => now()->subDays(9), 'is_pinned' => true]);

        $this->assertSame([$pinnedOld->id, $new->id, $old->id], array_column($this->api('GET', '/announcements')->json('data'), 'id'));

        foreach (range(1, 10) as $i) {
            $this->live(['starts_at' => now()->subMinutes($i)]);
        }
        $this->api('GET', '/announcements')->assertJsonCount(10, 'data')->assertJsonPath('data.0.id', $pinnedOld->id);
    }
}
