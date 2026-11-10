<?php

namespace App\Support\Bible;

/** A checked reference to one passage within one chapter (see ReferenceParser). */
final class ScriptureReference
{
    public function __construct(
        public readonly int $bookId,          // bible_books.id: canonical position, Genesis 1 ... Revelation 66
        public readonly string $bookCode,     // USFM, e.g. JHN
        public readonly int $chapter,
        public readonly int $verseStart,
        public readonly int $verseEnd,
        public readonly bool $wholeChapter,   // written as "Psalm 23" rather than with verses
        public readonly string $display,      // e.g. "John 3:16-18", "Psalm 23", "Jude 1:3"
    ) {
    }

    public function verseCount(): int
    {
        return $this->verseEnd - $this->verseStart + 1;
    }
}
