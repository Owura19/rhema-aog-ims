<?php

namespace Tests\Unit;

use App\Support\Bible\KjvCanon;
use PHPUnit\Framework\TestCase;

class KjvCanonTest extends TestCase
{
    public function test_the_canon_has_66_books_1189_chapters_and_31102_verses(): void
    {
        $books = KjvCanon::BOOKS;

        $this->assertCount(66, $books);
        $this->assertSame(KjvCanon::TOTAL_CHAPTERS, array_sum(array_column($books, 'chapters')));
        $this->assertSame(KjvCanon::TOTAL_VERSES, array_sum(array_column($books, 'verses')));
        $this->assertSame(1189, KjvCanon::TOTAL_CHAPTERS);
        $this->assertSame(31102, KjvCanon::TOTAL_VERSES);
        $this->assertSame(39, count(array_filter($books, fn ($b) => $b['testament'] === 'OT')));
        $this->assertSame(27, count(array_filter($books, fn ($b) => $b['testament'] === 'NT')));
        $this->assertSame(['Genesis', 'Psalms', 'Isaiah', 'John', 'Revelation'], [$books[0]['name'], $books[18]['name'], $books[22]['name'], $books[42]['name'], $books[65]['name']]);
        $this->assertSame([150, 66, 21, 22], [$books[18]['chapters'], $books[22]['chapters'], $books[42]['chapters'], $books[65]['chapters']]);
    }

    public function test_codes_and_names_are_unique_and_eleven_ebible_codes_differ_from_usfm(): void
    {
        $books = KjvCanon::BOOKS;

        $this->assertCount(66, array_unique(array_column($books, 'code')));
        $this->assertCount(66, array_unique(array_column($books, 'ebible')));
        $this->assertCount(66, array_unique(array_column($books, 'name')));

        $differ = array_values(array_map(fn ($b) => "{$b['ebible']}>{$b['code']}", array_filter($books, fn ($b) => $b['ebible'] !== $b['code'])));
        $this->assertSame(['SOL>SNG', 'EZE>EZK', 'JOE>JOL', 'NAH>NAM', 'MAR>MRK', 'JOH>JHN', 'PHI>PHP', 'JAM>JAS', '1JO>1JN', '2JO>2JN', '3JO>3JN'], $differ);
    }
}
