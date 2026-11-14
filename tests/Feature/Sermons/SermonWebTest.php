<?php

namespace Tests\Feature\Sermons;

use App\Models\Member;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\User;
use App\Support\Bible\ReferenceParser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The staff sermon pages: 'view sermons' to read, 'manage sermons' for the rest. Now is 2026-11-14 10:00. */
class SermonWebTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['Super Admin', 'Pastor', 'Office Staff', 'HOD', 'Usher'];
    private const ALLOWED = ['Super Admin', 'Pastor', 'Office Staff'];

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

        foreach (self::ROLES as $role) {
            $this->users[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title'         => 'Faith That Moves',
            'preacher_name' => 'Rev. Mensah',
            'preached_on'   => '2026-11-08',
            'youtube_url'   => 'https://youtu.be/dQw4w9WgXcQ',
        ];
    }

    private function sermon(array $attributes = []): Sermon
    {
        return Sermon::create($attributes + [
            'title'            => 'Faith That Moves',
            'preacher_name'    => 'Rev. Mensah',
            'preached_on'      => '2026-11-08',
            'youtube_video_id' => 'dQw4w9WgXcQ',
        ]);
    }

    public static function manageRoutes(): array
    {
        return [
            'create'    => ['GET', 'sermons.create', false],
            'store'     => ['POST', 'sermons.store', false],
            'edit'      => ['GET', 'sermons.edit', true],
            'update'    => ['PUT', 'sermons.update', true],
            'publish'   => ['POST', 'sermons.publish', true],
            'unpublish' => ['POST', 'sermons.unpublish', true],
            'destroy'   => ['DELETE', 'sermons.destroy', true],
        ];
    }

    #[DataProvider('manageRoutes')]
    public function test_only_sermon_managers_can_write(string $method, string $route, bool $needsSermon): void
    {
        foreach (self::ROLES as $role) {
            $sermon = $needsSermon ? $this->sermon() : null;
            $data = in_array($method, ['POST', 'PUT'], true) ? $this->payload() : [];

            $response = $this->as($role)->call($method, $sermon ? route($route, $sermon) : route($route), $data);

            if (in_array($role, self::ALLOWED, true)) {
                $this->assertContains($response->getStatusCode(), [200, 302], "{$role} should be allowed to use {$route}");
            } else {
                $this->assertSame(403, $response->getStatusCode(), "{$role} should be refused {$route}");
            }
        }
    }

    public function test_only_sermon_viewers_can_see_the_list_and_a_sermon(): void
    {
        $sermon = $this->sermon();

        foreach (self::ROLES as $role) {
            $expected = in_array($role, self::ALLOWED, true) ? 200 : 403;
            $this->as($role)->get(route('sermons.index'))->assertStatus($expected);
            $this->as($role)->get(route('sermons.show', $sermon))->assertStatus($expected);
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('sermons.index'))->assertRedirect(route('login'));
    }

    public function test_the_sidebar_link_is_only_for_sermon_viewers(): void
    {
        $this->as('Office Staff')->get(route('dashboard'))->assertSee(route('sermons.index'));
        $this->as('Usher')->get(route('dashboard'))->assertDontSee(route('sermons.index'));
    }

    public function test_a_new_sermon_is_a_draft_with_its_creator_and_youtube_id(): void
    {
        $series = SermonSeries::create(['title' => 'The Book of Acts']);
        $member = Member::forceCreate(['member_id' => 'GW-90001', 'first_name' => 'Kofi', 'last_name' => 'Mensah', 'gender' => 'Male']);

        $this->as('Pastor')->post(route('sermons.store'), $this->payload([
            'youtube_url'        => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s',
            'audio_url'          => 'https://example.org/audio/faith.mp3',
            'series_id'          => $series->id,
            'preacher_member_id' => $member->id,
            'summary'            => 'Mountains move.',
        ]))->assertSessionHasNoErrors()->assertSessionHas('success', 'Sermon saved as a draft.');

        $sermon = Sermon::sole();
        $this->assertSame(Sermon::STATUS_DRAFT, $sermon->status);
        $this->assertNull($sermon->published_at);
        $this->assertSame('dQw4w9WgXcQ', $sermon->youtube_video_id);
        $this->assertSame('https://example.org/audio/faith.mp3', $sermon->audio_url);
        $this->assertSame($series->id, $sermon->series_id);
        $this->assertSame($member->id, $sermon->preacher_member_id);
        $this->assertSame('2026-11-08', $sermon->preached_on->toDateString());
        $this->assertSame($this->users['Pastor']->id, $sermon->created_by);
        $this->assertSame($this->users['Pastor']->id, $sermon->updated_by);
        $this->assertNull($sermon->scripture_reference);
        $this->assertNull($sermon->bible_book_id);
    }

    public function test_validation(): void
    {
        $this->as('Pastor')->post(route('sermons.store'), [])->assertSessionHasErrors(['title', 'preacher_name', 'preached_on']);

        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['youtube_url' => 'https://vimeo.com/123456']))
            ->assertSessionHasErrors(['youtube_url' => 'Enter a YouTube video link (youtube.com or youtu.be).']);
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['audio_url' => 'http://example.org/a.mp3']))
            ->assertSessionHasErrors(['audio_url' => 'The audio link must be a full https:// address.']);
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['audio_url' => 'not a link']))
            ->assertSessionHasErrors('audio_url');
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['series_id' => 999]))->assertSessionHasErrors('series_id');
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['preacher_member_id' => 999]))->assertSessionHasErrors('preacher_member_id');
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['preached_on' => '08/11/2026']))->assertSessionHasErrors('preached_on');

        $this->assertSame(0, Sermon::count());
    }

    public function test_a_scripture_reference_is_checked_and_stored_with_its_verses(): void
    {
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['scripture_reference' => '  acts 2 : 1 – 13 ']))
            ->assertSessionHasNoErrors();

        $sermon = Sermon::sole();
        $this->assertSame('Acts 2:1-13', $sermon->scripture_reference);
        $this->assertSame(44, $sermon->bible_book_id);   // Acts is the 44th book
        $this->assertSame(2, $sermon->chapter);
        $this->assertSame(1, $sermon->verse_start);
        $this->assertSame(13, $sermon->verse_end);
    }

    public function test_a_long_passage_is_allowed_but_a_bad_reference_is_refused(): void
    {
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['scripture_reference' => 'Psalm 119:1-176']))
            ->assertSessionHasNoErrors();
        $this->assertSame(176, Sermon::sole()->verse_end);

        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['scripture_reference' => 'Acts two']))
            ->assertSessionHasErrors(['scripture_reference' => ReferenceParser::FORMAT]);
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['scripture_reference' => 'Acts 1:1-2:4']))
            ->assertSessionHasErrors(['scripture_reference' => ReferenceParser::CROSS_CHAPTER]);

        $this->assertSame(1, Sermon::count());
    }

    public function test_clearing_the_reference_and_links_on_edit_clears_their_columns(): void
    {
        $sermon = $this->sermon(['scripture_reference' => 'Acts 2:1-13', 'bible_book_id' => 44, 'chapter' => 2, 'verse_start' => 1, 'verse_end' => 13, 'audio_url' => 'https://example.org/a.mp3']);

        $this->as('Office Staff')->put(route('sermons.update', $sermon), $this->payload(['title' => 'Renamed', 'youtube_url' => '', 'scripture_reference' => '']))
            ->assertRedirect(route('sermons.show', $sermon));

        $sermon->refresh();
        $this->assertSame('Renamed', $sermon->title);
        $this->assertNull($sermon->youtube_video_id);
        $this->assertNull($sermon->audio_url);
        $this->assertNull($sermon->scripture_reference);
        $this->assertNull($sermon->bible_book_id);
        $this->assertNull($sermon->verse_end);
        $this->assertSame($this->users['Office Staff']->id, $sermon->updated_by);
    }

    public function test_a_thumbnail_can_be_uploaded_replaced_and_removed(): void
    {
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['thumbnail' => UploadedFile::fake()->image('first.jpg', 640, 360)]))
            ->assertSessionHasNoErrors();
        $sermon = Sermon::sole();
        $first = $sermon->thumbnail_path;
        $this->assertStringStartsWith('sermons/thumbnails/', $first);
        Storage::disk('public')->assertExists($first);

        $this->as('Pastor')->put(route('sermons.update', $sermon), $this->payload(['thumbnail' => UploadedFile::fake()->image('second.png', 640, 360)]))
            ->assertSessionHasNoErrors();
        $second = $sermon->fresh()->thumbnail_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->as('Pastor')->put(route('sermons.update', $sermon), $this->payload(['remove_thumbnail' => '1']))->assertSessionHasNoErrors();
        $this->assertNull($sermon->fresh()->thumbnail_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_an_edit_without_a_new_file_keeps_the_thumbnail(): void
    {
        Storage::disk('public')->put('sermons/thumbnails/kept.jpg', 'x');
        $sermon = $this->sermon(['thumbnail_path' => 'sermons/thumbnails/kept.jpg']);

        $this->as('Pastor')->put(route('sermons.update', $sermon), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame('sermons/thumbnails/kept.jpg', $sermon->fresh()->thumbnail_path);
        Storage::disk('public')->assertExists('sermons/thumbnails/kept.jpg');
    }

    public function test_a_thumbnail_must_be_a_small_image(): void
    {
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['thumbnail' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('thumbnail');
        $this->as('Pastor')->post(route('sermons.store'), $this->payload(['thumbnail' => UploadedFile::fake()->image('big.jpg')->size(5000)]))
            ->assertSessionHasErrors('thumbnail');

        $this->assertSame(0, Sermon::count());
    }

    public function test_publish_and_unpublish(): void
    {
        $sermon = $this->sermon();

        $this->as('Pastor')->from(route('sermons.show', $sermon))->post(route('sermons.publish', $sermon))
            ->assertRedirect(route('sermons.show', $sermon))
            ->assertSessionHas('success', 'Sermon published. It is now in the app.');
        $sermon->refresh();
        $this->assertSame(Sermon::STATUS_PUBLISHED, $sermon->status);
        $this->assertTrue($sermon->published_at->equalTo(now()));
        $this->assertSame([$sermon->id], Sermon::published()->pluck('id')->all());

        $this->as('Pastor')->post(route('sermons.publish', $sermon))->assertSessionHas('error', 'This sermon is already published.');

        $this->as('Office Staff')->post(route('sermons.unpublish', $sermon))
            ->assertSessionHas('success', 'Sermon unpublished: it is a draft again and hidden from the app.');
        $sermon->refresh();
        $this->assertSame(Sermon::STATUS_DRAFT, $sermon->status);
        $this->assertNull($sermon->published_at);
        $this->assertSame($this->users['Office Staff']->id, $sermon->updated_by);
        $this->assertSame(0, Sermon::published()->count());

        $this->as('Office Staff')->post(route('sermons.unpublish', $sermon))->assertSessionHas('error', 'This sermon is not published.');
    }

    public function test_publishing_needs_a_youtube_or_audio_link(): void
    {
        $bare = $this->sermon(['youtube_video_id' => null]);
        $audioOnly = $this->sermon(['youtube_video_id' => null, 'audio_url' => 'https://example.org/a.mp3']);

        $this->as('Pastor')->post(route('sermons.publish', $bare))
            ->assertSessionHas('error', 'Add a YouTube link or an audio link before publishing.');
        $this->assertSame(Sermon::STATUS_DRAFT, $bare->fresh()->status);

        $this->as('Pastor')->post(route('sermons.publish', $audioOnly))->assertSessionHas('success');
        $this->assertSame(Sermon::STATUS_PUBLISHED, $audioOnly->fresh()->status);
    }

    public function test_deleting_is_a_soft_delete_and_hides_the_sermon(): void
    {
        $sermon = $this->sermon(['status' => Sermon::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);

        $this->as('Pastor')->delete(route('sermons.destroy', $sermon))
            ->assertRedirect(route('sermons.index'))
            ->assertSessionHas('success', '"Faith That Moves" was deleted.');

        $this->assertTrue(Sermon::withTrashed()->find($sermon->id)->trashed());
        $this->assertSame(0, Sermon::published()->count());
        $this->as('Pastor')->get(route('sermons.show', $sermon->id))->assertNotFound();
    }

    public function test_the_list_filters_by_status_and_series(): void
    {
        $series = SermonSeries::create(['title' => 'The Book of Acts']);
        $this->sermon(['title' => 'Draft in series', 'series_id' => $series->id]);
        $this->sermon(['title' => 'Published alone', 'status' => Sermon::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);

        $this->as('Pastor')->get(route('sermons.index'))->assertOk()->assertSee('Draft in series')->assertSee('Published alone');
        $this->as('Pastor')->get(route('sermons.index', ['status' => 'published']))->assertSee('Published alone')->assertDontSee('Draft in series');
        $this->as('Pastor')->get(route('sermons.index', ['series' => $series->id]))->assertSee('Draft in series')->assertDontSee('Published alone');
    }

    public function test_the_pages_render_with_the_summary_escaped(): void
    {
        $sermon = $this->sermon(['summary' => "Line one\n<script>alert(1)</script>", 'scripture_reference' => 'Acts 2:1-13']);

        $this->as('Office Staff')->get(route('sermons.show', $sermon))
            ->assertOk()
            ->assertSee('Acts 2:1-13')
            ->assertSee('https://www.youtube.com/watch?v=dQw4w9WgXcQ', false)
            ->assertSee('Line one<br />', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee(route('sermons.publish', $sermon));

        $this->as('Pastor')->get(route('sermons.create'))->assertOk()->assertSee('Save draft');
        $this->as('Pastor')->get(route('sermons.edit', $sermon))->assertOk()->assertSee('Faith That Moves');
    }
}
