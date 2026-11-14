<?php

namespace Tests\Feature\Sermons;

use App\Models\Sermon;
use App\Models\SermonSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The sermons and sermon_series tables and models. Now is 2026-11-14 10:00. */
class SermonModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));
    }

    private function sermon(array $attributes = []): Sermon
    {
        return Sermon::create($attributes + [
            'title'         => 'Faith That Moves',
            'preacher_name' => 'Rev. Mensah',
            'preached_on'   => '2026-11-08',
        ]);
    }

    public function test_a_new_sermon_is_a_draft(): void
    {
        $sermon = $this->sermon();

        $this->assertSame(Sermon::STATUS_DRAFT, $sermon->fresh()->status);
        $this->assertNull($sermon->fresh()->published_at);
    }

    public function test_only_published_sermons_whose_time_has_come_are_published(): void
    {
        $live = $this->sermon(['status' => 'published', 'published_at' => now()->subHour()]);
        $this->sermon(['title' => 'Draft']);
        $this->sermon(['title' => 'Scheduled', 'status' => 'published', 'published_at' => now()->addHour()]);
        $this->sermon(['title' => 'No time', 'status' => 'published']);
        $deleted = $this->sermon(['title' => 'Deleted', 'status' => 'published', 'published_at' => now()->subHour()]);
        $deleted->delete();

        $this->assertSame([$live->id], Sermon::published()->pluck('id')->all());
    }

    public function test_soft_deleted_sermons_are_kept(): void
    {
        $sermon = $this->sermon();
        $sermon->delete();

        $this->assertNull(Sermon::find($sermon->id));
        $this->assertTrue(Sermon::withTrashed()->find($sermon->id)->trashed());
    }

    public function test_thumbnail_is_the_upload_else_the_youtube_image_else_null(): void
    {
        $uploaded = $this->sermon(['thumbnail_path' => 'sermons/thumbnails/abc.jpg', 'youtube_video_id' => 'dQw4w9WgXcQ']);
        $youtube = $this->sermon(['youtube_video_id' => 'dQw4w9WgXcQ']);
        $none = $this->sermon();

        $this->assertSame(asset('storage/sermons/thumbnails/abc.jpg'), $uploaded->thumbnailUrl());
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $youtube->thumbnailUrl());
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $youtube->youtubeUrl());
        $this->assertNull($none->thumbnailUrl());
        $this->assertNull($none->youtubeUrl());
    }

    public function test_a_series_has_sermons_and_deleting_it_keeps_them(): void
    {
        $series = SermonSeries::create(['title' => 'The Book of Acts']);
        $sermon = $this->sermon(['series_id' => $series->id]);

        $this->assertSame([$sermon->id], $series->sermons()->pluck('id')->all());
        $this->assertSame('The Book of Acts', $sermon->series->title);

        $series->delete();

        $this->assertNull($sermon->fresh()->series_id);   // nullOnDelete
    }
}
