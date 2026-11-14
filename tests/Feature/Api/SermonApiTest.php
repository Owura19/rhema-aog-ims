<?php

namespace Tests\Feature\Api;

use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/sermons, /api/v1/sermons/{id} and /api/v1/sermon-series. Now is Monday 2026-11-09 10:00. */
class SermonApiTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    /** Every key of one sermon, in order. Nothing else may appear. */
    private const KEYS = [
        'id', 'title', 'preacher', 'preached_on', 'scripture_reference', 'series', 'summary',
        'youtube', 'audio_url', 'thumbnail_url', 'published_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->signInAsMember();
    }

    private function published(array $attributes = []): Sermon
    {
        return Sermon::create($attributes + [
            'title'            => 'Faith That Moves',
            'preacher_name'    => 'Rev. Mensah',
            'preached_on'      => '2026-11-08',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'status'           => Sermon::STATUS_PUBLISHED,
            'published_at'     => now()->subHour(),
        ]);
    }

    // ── access ─────────────────────────────────────────────────────────

    public function test_it_needs_a_token(): void
    {
        $this->getJson('/api/v1/sermons')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->getJson('/api/v1/sermon-series')->assertUnauthorized();
    }

    public function test_a_deactivated_login_is_refused(): void
    {
        $this->token = User::factory()->create(['is_active' => false])->createAppToken('Phone')->plainTextToken;

        $this->api('GET', '/sermons')->assertForbidden()->assertJsonPath('code', 'account_inactive');
    }

    public function test_a_temporary_password_must_be_changed_first(): void
    {
        $this->token = User::factory()->create(['must_change_password' => true])->createAppToken('Phone')->plainTextToken;

        $this->api('GET', '/sermons')->assertForbidden()->assertJsonPath('code', 'password_change_required');
    }

    public function test_staff_without_a_member_record_can_read_sermons(): void
    {
        $sermon = $this->published();
        $this->token = User::factory()->create(['member_id' => null])->createAppToken('Office PC')->plainTextToken;

        $this->api('GET', '/sermons')->assertOk();
        $this->api('GET', "/sermons/{$sermon->id}")->assertOk();
        $this->api('GET', '/sermon-series')->assertOk();
    }

    // ── one sermon ─────────────────────────────────────────────────────

    public function test_a_sermon_has_exactly_these_fields(): void
    {
        $series = SermonSeries::create(['title' => 'The Book of Acts', 'description' => 'Chapter by chapter.']);
        $sermon = $this->published([
            'scripture_reference' => 'Acts 2:1-13',
            'bible_book_id'       => 44,
            'chapter'             => 2,
            'verse_start'         => 1,
            'verse_end'           => 13,
            'series_id'           => $series->id,
            'summary'             => 'Mountains move.',
            'audio_url'           => 'https://example.org/a.mp3',
            'published_at'        => '2026-11-08 18:30:00',
            'created_by'          => User::factory()->create()->id,
        ]);

        $this->api('GET', "/sermons/{$sermon->id}")->assertOk()->assertExactJson(['data' => [
            'id'                  => $sermon->id,
            'title'               => 'Faith That Moves',
            'preacher'            => 'Rev. Mensah',
            'preached_on'         => '2026-11-08',
            'scripture_reference' => 'Acts 2:1-13',
            'series'              => ['id' => $series->id, 'title' => 'The Book of Acts'],
            'summary'             => 'Mountains move.',
            'youtube'             => ['video_id' => 'dQw4w9WgXcQ', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'audio_url'           => 'https://example.org/a.mp3',
            'thumbnail_url'       => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'published_at'        => '2026-11-08T18:30:00+00:00',
        ]]);
    }

    public function test_optional_parts_are_null_and_an_upload_beats_the_youtube_thumbnail(): void
    {
        $audioOnly = $this->published(['youtube_video_id' => null, 'audio_url' => 'https://example.org/a.mp3']);
        $uploaded = $this->published(['thumbnail_path' => 'sermons/thumbnails/abc.jpg']);

        $this->api('GET', "/sermons/{$audioOnly->id}")->assertOk()
            ->assertJsonPath('data.youtube', null)
            ->assertJsonPath('data.series', null)
            ->assertJsonPath('data.scripture_reference', null)
            ->assertJsonPath('data.summary', null)
            ->assertJsonPath('data.thumbnail_url', null);

        $this->api('GET', "/sermons/{$uploaded->id}")->assertJsonPath('data.thumbnail_url', asset('storage/sermons/thumbnails/abc.jpg'));
    }

    public function test_anything_not_published_is_the_same_404(): void
    {
        $draft = $this->published(['status' => Sermon::STATUS_DRAFT, 'published_at' => null]);
        $scheduled = $this->published(['published_at' => now()->addHour()]);
        $deleted = $this->published();
        $deleted->delete();

        foreach ([$draft->id, $scheduled->id, $deleted->id, 999999] as $id) {
            $this->api('GET', "/sermons/{$id}")->assertNotFound()->assertExactJson(['message' => 'Not found.', 'code' => 'not_found']);
        }
        $this->api('GET', '/sermons/abc')->assertNotFound();
    }

    // ── the list ───────────────────────────────────────────────────────

    public function test_the_list_shows_published_sermons_newest_preached_first(): void
    {
        $older = $this->published(['title' => 'Older', 'preached_on' => '2026-11-01']);
        $newer = $this->published(['title' => 'Newer', 'preached_on' => '2026-11-08']);
        $this->published(['title' => 'Draft', 'status' => Sermon::STATUS_DRAFT, 'published_at' => null]);
        $this->published(['title' => 'Scheduled', 'published_at' => now()->addDay()]);
        $this->published(['title' => 'Deleted'])->delete();

        $response = $this->api('GET', '/sermons')->assertOk();

        $this->assertSame([$newer->id, $older->id], $this->ids($response));
        $this->assertSame(self::KEYS, array_keys($response->json('data.0')));
        $response->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 15)->assertJsonPath('meta.current_page', 1);
    }

    public function test_the_list_pages(): void
    {
        foreach (range(1, 5) as $day) {
            $this->published(['title' => "Day {$day}", 'preached_on' => sprintf('2026-11-%02d', $day)]);
        }

        $page2 = $this->api('GET', '/sermons?per_page=2&page=2')->assertOk();

        $this->assertSame(['Day 3', 'Day 2'], array_column($page2->json('data'), 'title'));
        $page2->assertJsonPath('meta.last_page', 3)->assertJsonPath('meta.total', 5);
    }

    public function test_the_list_filters_by_series_and_search(): void
    {
        $acts = SermonSeries::create(['title' => 'The Book of Acts']);
        $pentecost = $this->published(['title' => 'Pentecost Power', 'series_id' => $acts->id, 'scripture_reference' => 'Acts 2:1-13']);
        $grace = $this->published(['title' => 'Amazing Grace', 'preacher_name' => 'Pastor Owusu']);

        $this->assertSame([$pentecost->id], $this->ids($this->api('GET', "/sermons?series={$acts->id}")));
        $this->assertSame([$pentecost->id], $this->ids($this->api('GET', '/sermons?q=pentecost')));
        $this->assertSame([$pentecost->id], $this->ids($this->api('GET', '/sermons?q=Acts%202')));
        $this->assertSame([$grace->id], $this->ids($this->api('GET', '/sermons?q=owusu')));
        $this->assertSame([], $this->ids($this->api('GET', '/sermons?q=%25%25')));   // "%%" is literal text, not a wildcard
    }

    public function test_bad_list_parameters_are_refused(): void
    {
        foreach (['q=a', 'q=' . str_repeat('a', 101), 'per_page=0', 'per_page=31', 'page=0', 'series=abc'] as $query) {
            $this->api('GET', "/sermons?{$query}")->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        }
    }

    // ── series ─────────────────────────────────────────────────────────

    public function test_series_lists_only_series_with_published_sermons(): void
    {
        $acts = SermonSeries::create(['title' => 'The Book of Acts', 'description' => 'Chapter by chapter.']);
        $faith = SermonSeries::create(['title' => 'Faith']);
        $empty = SermonSeries::create(['title' => 'Coming Soon']);

        $this->published(['series_id' => $acts->id, 'preached_on' => '2026-10-04']);
        $this->published(['series_id' => $acts->id, 'preached_on' => '2026-10-11']);
        $this->published(['series_id' => $acts->id, 'preached_on' => '2026-11-15', 'status' => Sermon::STATUS_DRAFT, 'published_at' => null]);
        $this->published(['series_id' => $faith->id, 'preached_on' => '2026-11-08']);
        $this->published(['series_id' => $empty->id, 'status' => Sermon::STATUS_DRAFT, 'published_at' => null]);

        $this->api('GET', '/sermon-series')->assertOk()->assertExactJson(['data' => [
            ['id' => $faith->id, 'title' => 'Faith', 'description' => null, 'sermon_count' => 1, 'latest_preached_on' => '2026-11-08'],
            ['id' => $acts->id, 'title' => 'The Book of Acts', 'description' => 'Chapter by chapter.', 'sermon_count' => 2, 'latest_preached_on' => '2026-10-11'],
        ]]);
    }
}
