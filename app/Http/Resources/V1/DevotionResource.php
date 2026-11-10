<?php

namespace App\Http\Resources\V1;

use App\Models\Devotion;
use App\Support\Devotions\DevotionLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A live devotion as the app shows it, in the requested language where an approved,
 * up-to-date translation exists (else English). Exactly these keys: never ids,
 * status, source, approval, authorship, timestamps or reviewer details.
 * Expects theme and translations to be loaded.
 *
 * @mixin Devotion
 */
class DevotionResource extends JsonResource
{
    public function __construct(Devotion $devotion, private readonly string $lang)
    {
        parent::__construct($devotion);
    }

    public function toArray(Request $request): array
    {
        $translation = DevotionLanguage::servable($this->resource, $this->lang);
        $text = $translation ?? $this->resource;

        $translatedVerse = $translation && trim((string) $translation->verse_text) !== '' ? $translation->verse_text : null;
        $verse = $translatedVerse ?? $this->verse_text;

        return [
            'date'                => $this->publish_date->toDateString(),
            'title'               => $text->title,
            'scripture_reference' => $this->scripture_reference,
            'verse_text'          => $verse,
            'body'                => $text->body,
            'prayer'              => $text->prayer,
            'thought_for_the_day' => $text->thought_for_the_day,
            'theme'               => $this->theme?->title,
            'author_name'         => $this->author_name,
            'lang_requested'      => $this->lang,
            'lang'                => $translation ? $this->lang : DevotionLanguage::default(),
            'verse_lang'          => $verse === null ? null : ($translatedVerse !== null ? $this->lang : DevotionLanguage::default()),
            'available_langs'     => DevotionLanguage::available($this->resource),
        ];
    }
}
