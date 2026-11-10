<?php

namespace App\Services\Bible;

use App\Models\BibleVerse;
use App\Support\Bible\ScriptureReference;

/** The imported KJV verses (bible_verses), as devotions quote them. */
final class BibleText
{
    public const NOT_IMPORTED = "The Bible text hasn't been imported yet. Ask an administrator to run bible:import-kjv.";

    public static function isImported(): bool
    {
        return BibleVerse::query()->exists();
    }

    /**
     * The passage's verses in order, joined with single spaces and without verse numbers.
     * Null when any of its verses is missing (the import is incomplete).
     */
    public static function passage(ScriptureReference $reference): ?string
    {
        $texts = BibleVerse::query()
            ->where('book_id', $reference->bookId)
            ->where('chapter', $reference->chapter)
            ->whereBetween('verse', [$reference->verseStart, $reference->verseEnd])
            ->orderBy('verse')
            ->pluck('text');

        return $texts->count() === $reference->verseCount() ? $texts->implode(' ') : null;
    }
}
