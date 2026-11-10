<?php

namespace Tests\Feature\Devotions;

use App\Models\Devotion;
use App\Models\DevotionTheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Devotions\Concerns\DevotionHelpers;
use Tests\TestCase;

/** Devotion::live(), DevotionTheme::covering(), casts and relations. Today is Monday 2026-11-09. */
class DevotionModelTest extends TestCase
{
    use RefreshDatabase, DevotionHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00'));
    }

    public function test_live_means_approved_and_dated_today_or_earlier(): void
    {
        $today     = $this->devotion(['publish_date' => '2026-11-09', 'status' => 'approved']);
        $lastWeek  = $this->devotion(['publish_date' => '2026-11-02', 'status' => 'approved']);
        $this->devotion(['publish_date' => '2026-11-05', 'status' => 'draft']);
        $this->devotion(['publish_date' => '2026-11-10', 'status' => 'approved']);              // tomorrow
        $this->devotion(['publish_date' => '2026-11-03', 'status' => 'approved'])->delete();    // deleted

        $this->assertEqualsCanonicalizing([$today->id, $lastWeek->id], Devotion::live()->pluck('id')->all());
    }

    public function test_the_theme_covering_a_date_prefers_the_most_recently_started(): void
    {
        $month = $this->theme('2026-11-01', '2026-11-30', 'November: Faith');
        $week  = $this->theme('2026-11-15', '2026-11-21', 'Thanksgiving week');

        $this->assertNull(DevotionTheme::covering('2026-10-31'));
        $this->assertSame($month->id, DevotionTheme::covering('2026-11-01')->id);   // first day included
        $this->assertSame($month->id, DevotionTheme::covering('2026-11-14')->id);
        $this->assertSame($week->id, DevotionTheme::covering('2026-11-15')->id);    // overlap: the later start wins
        $this->assertSame($week->id, DevotionTheme::covering(Carbon::parse('2026-11-21'))->id);   // last day included
        $this->assertSame($month->id, DevotionTheme::covering('2026-11-22')->id);
        $this->assertSame($month->id, DevotionTheme::covering('2026-11-30')->id);
        $this->assertNull(DevotionTheme::covering('2026-12-01'));
    }

    public function test_themes_starting_the_same_day_resolve_to_the_newest(): void
    {
        $this->theme('2026-11-01', '2026-11-30', 'First');
        $newer = $this->theme('2026-11-01', '2026-11-30', 'Second');

        $this->assertSame($newer->id, DevotionTheme::covering('2026-11-10')->id);
    }

    public function test_casts_defaults_and_relations(): void
    {
        $staff = User::factory()->create();
        $theme = $this->theme('2026-11-01', '2026-11-30');
        $devotion = $this->devotion([
            'theme_id'    => $theme->id,
            'status'      => Devotion::STATUS_APPROVED,
            'approved_by' => $staff->id,
            'approved_at' => '2026-11-09 09:00:00',
            'created_by'  => $staff->id,
            'updated_by'  => $staff->id,
        ])->fresh();
        $this->translation($devotion, 'ha');

        $this->assertInstanceOf(Carbon::class, $devotion->publish_date);
        $this->assertSame('2026-11-20', $devotion->publish_date->toDateString());
        $this->assertFalse($devotion->no_theme);
        $this->assertFalse($devotion->ai_edited);
        $this->assertSame(Devotion::SOURCE_MANUAL, $devotion->source);
        $this->assertTrue($devotion->isApproved());
        $this->assertTrue($devotion->theme->is($theme));
        $this->assertTrue($devotion->approvedBy->is($staff));
        $this->assertTrue($devotion->createdBy->is($staff));
        $this->assertTrue($devotion->updatedBy->is($staff));
        $this->assertSame(['ha'], $devotion->translations->pluck('locale')->all());
        $this->assertTrue($theme->devotions->first()->is($devotion));
    }
}
