<?php

namespace Tests\Unit;

use App\Support\Bible\InvalidReference;
use App\Support\Bible\KjvCanon;
use App\Support\Bible\ReferenceParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** ReferenceParser: no database, no config (the 30-verse cap is passed in). */
class ReferenceParserTest extends TestCase
{
    private function parser(int $maxVerses = 30): ReferenceParser
    {
        return new ReferenceParser($maxVerses);
    }

    /** input => [USFM code, chapter, first verse, last verse, display] */
    public static function accepted(): array
    {
        return [
            'single verse'                 => ['John 3:16',            'JHN', 3,   16, 16, 'John 3:16'],
            'range with a hyphen'          => ['John 3:16-18',         'JHN', 3,   16, 18, 'John 3:16-18'],
            'range with an en dash'        => ['John 3:16–18',         'JHN', 3,   16, 18, 'John 3:16-18'],
            'spaces around the dash'       => ['John 3:16 - 18',       'JHN', 3,   16, 18, 'John 3:16-18'],
            'same chapter written twice'   => ['John 3:16-3:18',       'JHN', 3,   16, 18, 'John 3:16-18'],
            'whole chapter'                => ['John 21',              'JHN', 21,  1,  25, 'John 21'],
            'abbreviation'                 => ['Jn 3:16',              'JHN', 3,   16, 16, 'John 3:16'],
            'abbreviation with a stop'     => ['Gen. 1:1',             'GEN', 1,   1,  1,  'Genesis 1:1'],
            'numbered book'                => ['1 John 1:9',           '1JN', 1,   9,  9,  '1 John 1:9'],
            'numbered, no space'           => ['1John 1:9',            '1JN', 1,   9,  9,  '1 John 1:9'],
            'roman numeral'                => ['I John 1:9',           '1JN', 1,   9,  9,  '1 John 1:9'],
            'ordinal word'                 => ['First John 1:9',       '1JN', 1,   9,  9,  '1 John 1:9'],
            'roman II'                     => ['II Kings 2:11',        '2KI', 2,   11, 11, '2 Kings 2:11'],
            'ordinal Third'                => ['third john 1:4',       '3JN', 1,   4,  4,  '3 John 1:4'],
            'Ps, whole psalm'              => ['Ps 23',                'PSA', 23,  1,  6,  'Psalm 23'],
            'Psalm, whole psalm'           => ['Psalm 23',             'PSA', 23,  1,  6,  'Psalm 23'],
            'Psalms with verses'           => ['Psalms 23:1-6',        'PSA', 23,  1,  6,  'Psalm 23:1-6'],
            'shortest psalm'               => ['Psalm 117',            'PSA', 117, 1,  2,  'Psalm 117'],
            'Song of Solomon'              => ['Song of Solomon 2:4',  'SNG', 2,   4,  4,  'Song of Solomon 2:4'],
            'Song of Songs'                => ['Song of Songs 2:4',    'SNG', 2,   4,  4,  'Song of Solomon 2:4'],
            'Canticles'                    => ['Canticles 2:4',        'SNG', 2,   4,  4,  'Song of Solomon 2:4'],
            'upper case'                   => ['JOHN 3:16',            'JHN', 3,   16, 16, 'John 3:16'],
            'extra spaces'                 => ['  john   3 : 16  ',    'JHN', 3,   16, 16, 'John 3:16'],
            'no space before the chapter'  => ['John3:16',             'JHN', 3,   16, 16, 'John 3:16'],
            'Isaiah is not "I saiah"'      => ['Isaiah 40:31',         'ISA', 40,  31, 31, 'Isaiah 40:31'],
            'Rev'                          => ['Rev 22:21',            'REV', 22,  21, 21, 'Revelation 22:21'],
            'exactly 30 verses'            => ['John 3:1-30',          'JHN', 3,   1,  30, 'John 3:1-30'],
            'last verse of a chapter'      => ['John 3:36',            'JHN', 3,   36, 36, 'John 3:36'],
            // one-chapter books: a lone number is a verse
            'Jude 3'                       => ['Jude 3',               'JUD', 1,   3,  3,  'Jude 1:3'],
            'Jude 3-5'                     => ['Jude 3-5',             'JUD', 1,   3,  5,  'Jude 1:3-5'],
            'Jude 1:3'                     => ['Jude 1:3',             'JUD', 1,   3,  3,  'Jude 1:3'],
            'Obadiah 21'                   => ['Obadiah 21',           'OBA', 1,   21, 21, 'Obadiah 1:21'],
            'Philemon 4'                   => ['Phlm 4',               'PHM', 1,   4,  4,  'Philemon 1:4'],
            '2 John 12'                    => ['2 John 12',            '2JN', 1,   12, 12, '2 John 1:12'],
            '3 John 14'                    => ['3 Jn 14',              '3JN', 1,   14, 14, '3 John 1:14'],
        ];
    }

    #[DataProvider('accepted')]
    public function test_accepted_references(string $input, string $code, int $chapter, int $start, int $end, string $display): void
    {
        $reference = $this->parser()->parse($input);

        $this->assertSame(
            [$code, $chapter, $start, $end, $display],
            [$reference->bookCode, $reference->chapter, $reference->verseStart, $reference->verseEnd, $reference->display],
        );
        $this->assertSame(array_search($code, array_column(KjvCanon::BOOKS, 'code'), true) + 1, $reference->bookId);
    }

    /** input => message */
    public static function refused(): array
    {
        return [
            'unknown book'                 => ['Hezekiah 3:1',            'We don\'t recognise the book "Hezekiah".'],
            'misspelt book, as typed'      => ['Jhon 3:16',               'We don\'t recognise the book "Jhon".'],
            'chapter too high'             => ['John 22:1',               'John has 21 chapters.'],
            'one-chapter book, chapter 2'  => ['Jude 2:1',                'Jude has 1 chapter.'],
            'verse too high'               => ['John 3:37',               'John 3 has 36 verses.'],
            'range end too high'           => ['John 3:30-40',            'John 3 has 36 verses.'],
            'verse too high in a psalm'    => ['Psalm 23:7',              'Psalm 23 has 6 verses.'],
            'verse too high in Jude'       => ['Jude 26',                 'Jude 1 has 25 verses.'],
            'across chapters'              => ['John 3:16-4:2',           ReferenceParser::CROSS_CHAPTER],
            'chapter range'                => ['John 3-4',                ReferenceParser::CROSS_CHAPTER],
            'a list'                       => ['John 3:16,18',            ReferenceParser::FORMAT],
            'several passages'             => ['John 3:16; Romans 8:28',  ReferenceParser::FORMAT],
            'joined with "and"'            => ['John 3:16 and 18',        ReferenceParser::FORMAT],
            'reversed range'               => ['John 3:18-16',            ReferenceParser::FORMAT],
            'book only'                    => ['John',                    ReferenceParser::FORMAT],
            'numbers only'                 => ['3:16',                    ReferenceParser::FORMAT],
            'empty'                        => ['',                        ReferenceParser::FORMAT],
            'chapter 0'                    => ['John 0:1',                ReferenceParser::FORMAT],
            'verse 0'                      => ['John 3:0',                ReferenceParser::FORMAT],
            'whole chapter over 30 verses' => ['Psalm 119',               'Choose up to 30 verses.'],
            'John 3 has 36 verses'         => ['John 3',                  'Choose up to 30 verses.'],
            'range over 30 verses'         => ['John 3:1-31',             'Choose up to 30 verses.'],
            'long range in Psalm 119'      => ['Psalm 119:1-40',          'Choose up to 30 verses.'],
        ];
    }

    #[DataProvider('refused')]
    public function test_refused_references(string $input, string $message): void
    {
        try {
            $this->parser()->parse($input);
            $this->fail("\"{$input}\" should have been refused");
        } catch (InvalidReference $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_the_verse_cap_comes_from_the_constructor_for_ranges_and_whole_chapters(): void
    {
        $this->assertSame('John 3:16-20', $this->parser(5)->parse('John 3:16-20')->display);
        $this->assertSame('Psalm 117', $this->parser(5)->parse('Psalm 117')->display);

        foreach (['John 3:16-21', 'Psalm 23'] as $input) {
            try {
                $this->parser(5)->parse($input);
                $this->fail("\"{$input}\" should have been refused");
            } catch (InvalidReference $e) {
                $this->assertSame('Choose up to 5 verses.', $e->getMessage());
            }
        }
    }

    public function test_whole_chapters_are_marked_and_count_their_verses(): void
    {
        $whole = $this->parser()->parse('Psalm 23');
        $range = $this->parser()->parse('Psalm 23:1-6');

        $this->assertTrue($whole->wholeChapter);
        $this->assertFalse($range->wholeChapter);
        $this->assertSame(6, $whole->verseCount());
        $this->assertSame(1, $this->parser()->parse('John 3:16')->verseCount());
    }

    public function test_every_book_name_in_the_canon_is_recognised(): void
    {
        foreach (KjvCanon::BOOKS as $i => $book) {
            $reference = $this->parser()->parse("{$book['name']} 1:1");

            $this->assertSame($book['code'], $reference->bookCode, $book['name']);
            $this->assertSame($i + 1, $reference->bookId, $book['name']);
        }
    }

    public function test_the_alias_list_covers_every_book_once_and_is_fixed(): void
    {
        $aliases = ReferenceParser::aliases();

        $this->assertSame(array_column(KjvCanon::BOOKS, 'code'), array_values(array_unique($aliases)));
        $this->assertSame('JHN', $aliases['jn']);
        $this->assertSame('PSA', $aliases['ps']);
        $this->assertSame('SNG', $aliases['canticles']);
        $this->assertArrayNotHasKey('jo', $aliases);   // no guessing: "Jo" could be Job, Joel, John, Jonah...

        foreach (['Jo 3:16', 'Phil 2:5x', 'Johnn 3:16'] as $input) {
            try {
                $this->parser()->parse($input);
                $this->fail("\"{$input}\" should have been refused");
            } catch (InvalidReference) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
