<?php

namespace Tests\Feature\Devotions\Concerns;

use App\Models\Devotion;
use App\Models\DevotionTheme;
use App\Models\DevotionTranslation;
use App\Support\Bible\KjvCanon;
use Illuminate\Support\Facades\DB;

/** Builders for devotion tests. The devotion models have no factories. */
trait DevotionHelpers
{
    /** The real KJV text (cleaned) of the verses in kjvFixture(). */
    protected const KJV_ISAIAH_40_31 = 'But they that wait upon the LORD shall renew their strength; they shall mount up with wings as eagles; they shall run, and not be weary; and they shall walk, and not faint.';
    protected const KJV_JOHN_3_16    = 'For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.';
    protected const KJV_JOHN_3_17    = 'For God sent not his Son into the world to condemn the world; but that the world through him might be saved.';

    protected function devotion(array $attributes = []): Devotion
    {
        return Devotion::create($attributes + [
            'publish_date'        => '2026-11-20',
            'title'               => 'Strength for Today',
            'scripture_reference' => 'Isaiah 40:31',
            'body'                => 'They that wait upon the LORD shall renew their strength.',
            'prayer'              => 'Lord, teach me to wait on You.',
            'thought_for_the_day' => 'Waiting on God is never wasted time.',
        ]);
    }

    /** A devotion whose reference has been checked: Isaiah 40:31 with its KJV text. Needs kjvFixture(). */
    protected function devotionWithVerse(array $attributes = []): Devotion
    {
        return $this->devotion($attributes + [
            'bible_book_id' => 23,
            'chapter'       => 40,
            'verse_start'   => 31,
            'verse_end'     => 31,
            'verse_text'    => self::KJV_ISAIAH_40_31,
        ]);
    }

    /**
     * A tiny imported KJV, so web tests need not import the whole Bible: Isaiah and John
     * (their real ids 23 and 43) with just Isaiah 40:31 and John 3:16-17.
     */
    protected function kjvFixture(): void
    {
        $positions = array_flip(array_column(KjvCanon::BOOKS, 'code'));

        foreach (['ISA', 'JHN'] as $code) {
            $book = KjvCanon::BOOKS[$positions[$code]];
            DB::table('bible_books')->insert([
                'id'            => $positions[$code] + 1,
                'code'          => $code,
                'name'          => $book['name'],
                'testament'     => $book['testament'],
                'chapter_count' => $book['chapters'],
                'verse_count'   => $book['verses'],
            ]);
        }

        DB::table('bible_verses')->insert([
            ['book_id' => 23, 'chapter' => 40, 'verse' => 31, 'text' => self::KJV_ISAIAH_40_31],
            ['book_id' => 43, 'chapter' => 3,  'verse' => 16, 'text' => self::KJV_JOHN_3_16],
            ['book_id' => 43, 'chapter' => 3,  'verse' => 17, 'text' => self::KJV_JOHN_3_17],
        ]);
    }

    protected function theme(string $startsOn, string $endsOn, string $title = 'Faith'): DevotionTheme
    {
        return DevotionTheme::create(['title' => $title, 'starts_on' => $startsOn, 'ends_on' => $endsOn]);
    }

    protected function translation(Devotion $devotion, string $locale, array $attributes = []): DevotionTranslation
    {
        return DevotionTranslation::create($attributes + ['devotion_id' => $devotion->id, 'locale' => $locale]);
    }
}
