<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The announcements table and the model's live window and states. Now is 2026-11-16 10:00 UTC. */
class AnnouncementModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
    }

    private function make(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + ['title' => 'Harvest', 'body' => 'Bring your harvest offering.']);
    }

    public function test_defaults(): void
    {
        $announcement = $this->make()->fresh();

        $this->assertSame(Announcement::STATUS_DRAFT, $announcement->status);
        $this->assertFalse($announcement->is_pinned);
        $this->assertNotNull($announcement->starts_at);   // defaults to now
        $this->assertNull($announcement->ends_at);
        $this->assertSame('draft', $announcement->state());
    }

    public function test_live_means_published_and_inside_the_window(): void
    {
        $live = $this->make(['status' => 'published', 'starts_at' => now()->subDay()]);
        $endsLater = $this->make(['status' => 'published', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMinute()]);
        $this->make(['title' => 'Draft', 'starts_at' => now()->subDay()]);
        $scheduled = $this->make(['status' => 'published', 'starts_at' => now()->addHour()]);
        $expired = $this->make(['status' => 'published', 'starts_at' => now()->subWeek(), 'ends_at' => now()]);
        $deleted = $this->make(['status' => 'published', 'starts_at' => now()->subDay()]);
        $deleted->delete();

        $this->assertEqualsCanonicalizing([$live->id, $endsLater->id], Announcement::live()->pluck('id')->all());
        $this->assertSame('active', $live->state());
        $this->assertSame('scheduled', $scheduled->state());
        $this->assertSame('expired', $expired->state());
    }
}
