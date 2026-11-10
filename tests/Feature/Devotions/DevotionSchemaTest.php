<?php

namespace Tests\Feature\Devotions;

use App\Models\Devotion;
use App\Models\DevotionTranslation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Devotions\Concerns\DevotionHelpers;
use Tests\TestCase;

/**
 * The database itself allows one non-deleted devotion per date (generated column
 * live_publish_date + unique index), and one translation per devotion per language.
 */
class DevotionSchemaTest extends TestCase
{
    use RefreshDatabase, DevotionHelpers;

    public function test_two_live_devotions_cannot_share_a_date(): void
    {
        $this->devotion(['publish_date' => '2026-11-20']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->devotion(['publish_date' => '2026-11-20', 'title' => 'A second one']);
    }

    public function test_soft_deleted_devotions_do_not_block_their_date(): void
    {
        $first = $this->devotion(['publish_date' => '2026-11-20']);
        $first->delete();
        $second = $this->devotion(['publish_date' => '2026-11-20', 'title' => 'Replacement']);
        $second->delete();
        $third = $this->devotion(['publish_date' => '2026-11-20', 'title' => 'Second replacement']);

        $this->assertSame(3, Devotion::withTrashed()->whereDate('publish_date', '2026-11-20')->count());
        $this->assertNull(Devotion::withTrashed()->find($first->id)->live_publish_date);
        $this->assertSame('2026-11-20', (string) Devotion::find($third->id)->live_publish_date);
    }

    public function test_restoring_a_deleted_devotion_onto_a_taken_date_fails_and_leaves_it_deleted(): void
    {
        $old = $this->devotion(['publish_date' => '2026-11-20']);
        $old->delete();
        $this->devotion(['publish_date' => '2026-11-20', 'title' => 'Replacement']);

        try {
            $old->restore();
            $this->fail('restoring onto a taken date should have been refused');
        } catch (UniqueConstraintViolationException) {
            // expected
        }

        $this->assertTrue(Devotion::withTrashed()->find($old->id)->trashed());
        $this->assertSame(1, Devotion::whereDate('publish_date', '2026-11-20')->count());
    }

    public function test_moving_a_devotion_onto_a_taken_date_fails(): void
    {
        $this->devotion(['publish_date' => '2026-11-20']);
        $other = $this->devotion(['publish_date' => '2026-11-21']);

        $this->expectException(UniqueConstraintViolationException::class);

        $other->update(['publish_date' => '2026-11-20']);
    }

    public function test_the_generated_column_follows_the_publish_date(): void
    {
        $devotion = $this->devotion(['publish_date' => '2026-11-20']);
        $devotion->update(['publish_date' => '2026-11-25']);

        $this->assertSame('2026-11-25', (string) $devotion->fresh()->live_publish_date);
    }

    public function test_one_translation_per_devotion_per_language(): void
    {
        $devotion = $this->devotion();
        $this->translation($devotion, 'tw');
        $this->translation($devotion, 'ha');          // another language is fine
        $this->translation($this->devotion(['publish_date' => '2026-11-21']), 'tw');   // same language, another devotion: fine

        $this->expectException(UniqueConstraintViolationException::class);

        $this->translation($devotion, 'tw');
    }

    public function test_permanently_deleting_a_devotion_removes_its_translations(): void
    {
        $devotion = $this->devotion();
        $this->translation($devotion, 'tw');
        $this->translation($devotion, 'ha');

        $devotion->delete();   // soft delete keeps them
        $this->assertSame(2, DevotionTranslation::count());

        $devotion->forceDelete();
        $this->assertSame(0, DevotionTranslation::count());
    }
}
