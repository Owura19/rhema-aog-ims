<?php

namespace App\Support\Devotions;

use App\Models\Devotion;
use App\Models\DevotionTranslation;

/**
 * Which language a devotion is shown in.
 *
 * A translation is served only when it is approved, up to date (its source_hash
 * matches the devotion's current English) and complete; otherwise the WHOLE text
 * is English. The verse comes from the translation when it has one, else the KJV.
 * Expects the devotion's translations to be loaded.
 */
final class DevotionLanguage
{
    /** Translated fields; all must be present for a translation to be served. */
    private const TEXT_FIELDS = ['title', 'body', 'prayer', 'thought_for_the_day'];

    public static function default(): string
    {
        return config('devotions.default_locale');
    }

    /** @return list<string> e.g. ['en', 'tw', 'gaa', 'ee', 'ha'] */
    public static function supported(): array
    {
        return [self::default(), ...array_keys(config('devotions.translations'))];
    }

    /** The translation that may be shown for $locale, or null (English is shown). */
    public static function servable(Devotion $devotion, string $locale): ?DevotionTranslation
    {
        if ($locale === self::default() || ! array_key_exists($locale, config('devotions.translations'))) {
            return null;
        }

        $hash = $devotion->sourceHash();

        return $devotion->translations->first(fn (DevotionTranslation $translation) => $translation->locale === $locale
            && $translation->status === DevotionTranslation::STATUS_APPROVED
            && $translation->source_hash === $hash
            && collect(self::TEXT_FIELDS)->every(fn (string $field) => trim((string) $translation->{$field}) !== ''));
    }

    /** @return list<string> 'en' plus every language with a servable translation, in config order */
    public static function available(Devotion $devotion): array
    {
        return [
            self::default(),
            ...array_values(array_filter(
                array_keys(config('devotions.translations')),
                fn (string $locale) => self::servable($devotion, $locale) !== null,
            )),
        ];
    }
}
