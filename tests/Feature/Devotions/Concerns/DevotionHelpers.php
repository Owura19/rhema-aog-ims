<?php

namespace Tests\Feature\Devotions\Concerns;

use App\Models\Devotion;
use App\Models\DevotionTheme;
use App\Models\DevotionTranslation;

/** Builders for devotion tests. The devotion models have no factories. */
trait DevotionHelpers
{
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

    protected function theme(string $startsOn, string $endsOn, string $title = 'Faith'): DevotionTheme
    {
        return DevotionTheme::create(['title' => $title, 'starts_on' => $startsOn, 'ends_on' => $endsOn]);
    }

    protected function translation(Devotion $devotion, string $locale, array $attributes = []): DevotionTranslation
    {
        return DevotionTranslation::create($attributes + ['devotion_id' => $devotion->id, 'locale' => $locale]);
    }
}
