<?php

namespace App\Support\Bible;

use LogicException;

/**
 * Turns a typed scripture reference into one checked passage within one chapter.
 * Never reads the database: book names and chapter/verse counts come from KjvCanon.
 *
 * Accepted: "John 3:16", "John 3:16-18" (hyphen or en dash), "John 21" (whole chapter),
 * "Jn 3:16", "Gen. 1:1", "1 John 1:9", "1John", "I John", "First John", "Ps 23",
 * "Psalms 23:1-6", "Song of Songs 2:4"; any case, extra spaces. For the one-chapter books
 * (Obadiah, Philemon, 2 John, 3 John, Jude) "Jude 3" means verse 3.
 *
 * Display: "John 3:16", "John 3:16-18", "John 21", "Psalm 23" (never "Psalms" for one
 * chapter), "Jude 1:3". Book names come only from the fixed alias list below: no guessing.
 */
final class ReferenceParser
{
    public const FORMAT        = 'Write the reference like John 3:16 or John 3:16-18.';
    public const CROSS_CHAPTER = 'A passage must stay within one chapter.';

    /** book, chapter, optional :verse, optional -[chapter:]verse (spaces around - and : already removed). */
    private const PATTERN = '/^(?<book>(?:(?:[1-3]|i{1,3}|first|second|third)\s*)?[a-z][a-z.\s]*?)\s*(?<c>\d{1,3})(?::(?<v>\d{1,3}))?(?:-(?:(?<c2>\d{1,3}):)?(?<e>\d{1,3}))?$/i';

    private const ORDINALS = ['i' => '1', 'ii' => '2', 'iii' => '3', 'first' => '1', 'second' => '2', 'third' => '3'];

    /**
     * Every accepted book name, by USFM code: lower case, no full stops, single spaces,
     * numbered books written "1 name". "1John", "I John" and "First John" become "1 john".
     */
    private const ALIASES = [
        'GEN' => ['genesis', 'gen', 'ge', 'gn'],
        'EXO' => ['exodus', 'exod', 'exo', 'ex'],
        'LEV' => ['leviticus', 'lev', 'le', 'lv'],
        'NUM' => ['numbers', 'num', 'nu', 'nm', 'nb'],
        'DEU' => ['deuteronomy', 'deut', 'deu', 'dt'],
        'JOS' => ['joshua', 'josh', 'jos'],
        'JDG' => ['judges', 'judg', 'jdg', 'jg'],
        'RUT' => ['ruth', 'rut', 'ru'],
        '1SA' => ['1 samuel', '1 sam', '1 sa', '1 sm'],
        '2SA' => ['2 samuel', '2 sam', '2 sa', '2 sm'],
        '1KI' => ['1 kings', '1 kgs', '1 ki', '1 kin'],
        '2KI' => ['2 kings', '2 kgs', '2 ki', '2 kin'],
        '1CH' => ['1 chronicles', '1 chron', '1 chr', '1 ch'],
        '2CH' => ['2 chronicles', '2 chron', '2 chr', '2 ch'],
        'EZR' => ['ezra', 'ezr'],
        'NEH' => ['nehemiah', 'neh', 'ne'],
        'EST' => ['esther', 'esth', 'est', 'es'],
        'JOB' => ['job', 'jb'],
        'PSA' => ['psalms', 'psalm', 'ps', 'psa', 'pss'],
        'PRO' => ['proverbs', 'prov', 'pro', 'prv', 'pr'],
        'ECC' => ['ecclesiastes', 'eccles', 'eccl', 'ecc', 'ec', 'qoheleth'],
        'SNG' => ['song of solomon', 'song of songs', 'song of sol', 'song', 'canticles', 'cant', 'sos'],
        'ISA' => ['isaiah', 'isa', 'is'],
        'JER' => ['jeremiah', 'jer', 'je'],
        'LAM' => ['lamentations', 'lam', 'la'],
        'EZK' => ['ezekiel', 'ezek', 'eze', 'ezk'],
        'DAN' => ['daniel', 'dan', 'da', 'dn'],
        'HOS' => ['hosea', 'hos', 'ho'],
        'JOL' => ['joel', 'jl'],
        'AMO' => ['amos', 'am'],
        'OBA' => ['obadiah', 'obad', 'ob'],
        'JON' => ['jonah', 'jon', 'jnh'],
        'MIC' => ['micah', 'mic', 'mc'],
        'NAM' => ['nahum', 'nah', 'na'],
        'HAB' => ['habakkuk', 'hab', 'hb'],
        'ZEP' => ['zephaniah', 'zeph', 'zep', 'zp'],
        'HAG' => ['haggai', 'hag', 'hg'],
        'ZEC' => ['zechariah', 'zech', 'zec', 'zc'],
        'MAL' => ['malachi', 'mal', 'ml'],
        'MAT' => ['matthew', 'matt', 'mat', 'mt'],
        'MRK' => ['mark', 'mrk', 'mar', 'mk', 'mr'],
        'LUK' => ['luke', 'luk', 'lk'],
        'JHN' => ['john', 'jhn', 'joh', 'jn'],
        'ACT' => ['acts', 'act', 'ac'],
        'ROM' => ['romans', 'rom', 'ro', 'rm'],
        '1CO' => ['1 corinthians', '1 cor', '1 co'],
        '2CO' => ['2 corinthians', '2 cor', '2 co'],
        'GAL' => ['galatians', 'gal', 'ga'],
        'EPH' => ['ephesians', 'ephes', 'eph'],
        'PHP' => ['philippians', 'phil', 'php', 'pp'],
        'COL' => ['colossians', 'col'],
        '1TH' => ['1 thessalonians', '1 thess', '1 thes', '1 th'],
        '2TH' => ['2 thessalonians', '2 thess', '2 thes', '2 th'],
        '1TI' => ['1 timothy', '1 tim', '1 ti'],
        '2TI' => ['2 timothy', '2 tim', '2 ti'],
        'TIT' => ['titus', 'tit'],
        'PHM' => ['philemon', 'philem', 'phlm', 'phm'],
        'HEB' => ['hebrews', 'heb'],
        'JAS' => ['james', 'jas', 'jm'],
        '1PE' => ['1 peter', '1 pet', '1 pe', '1 pt'],
        '2PE' => ['2 peter', '2 pet', '2 pe', '2 pt'],
        '1JN' => ['1 john', '1 jhn', '1 joh', '1 jn', '1 jo'],
        '2JN' => ['2 john', '2 jhn', '2 joh', '2 jn', '2 jo'],
        '3JN' => ['3 john', '3 jhn', '3 joh', '3 jn', '3 jo'],
        'JUD' => ['jude', 'jud', 'jd'],
        'REV' => ['revelation', 'revelations', 'rev', 're', 'rv'],
    ];

    /** @var array<string, int>|null alias => index in KjvCanon::BOOKS */
    private static ?array $lookup = null;

    public function __construct(private readonly int $maxVerses = 30)
    {
    }

    /** The parser with the cap from config/devotions.php (scripture.max_verses). */
    public static function fromConfig(): self
    {
        return new self((int) config('devotions.scripture.max_verses', 30));
    }

    /** @throws InvalidReference with a message for staff */
    public function parse(string $input): ScriptureReference
    {
        $text = str_replace(mb_chr(0x2013, 'UTF-8'), '-', trim((string) preg_replace('/\s+/u', ' ', $input)));
        $text = (string) preg_replace('/ ?([-:]) ?/', '$1', $text);

        if (! preg_match(self::PATTERN, $text, $m)) {
            throw new InvalidReference(self::FORMAT);
        }

        $index = self::lookup()[self::bookKey($m['book'])] ?? null;
        if ($index === null) {
            throw new InvalidReference('We don\'t recognise the book "' . trim($m['book']) . '".');
        }

        $book = KjvCanon::BOOKS[$index];
        $name = $book['code'] === 'PSA' ? 'Psalm' : $book['name'];   // one chapter of Psalms is "Psalm 23"

        $first     = (int) $m['c'];
        $verse     = ($m['v'] ?? '') === '' ? null : (int) $m['v'];
        $toChapter = ($m['c2'] ?? '') === '' ? null : (int) $m['c2'];
        $to        = ($m['e'] ?? '') === '' ? null : (int) $m['e'];
        $whole     = false;

        if ($book['chapters'] === 1 && $verse === null) {
            // "Jude 3" or "Jude 3-5": the numbers are verses of the only chapter.
            if ($toChapter !== null) {
                throw new InvalidReference(self::FORMAT);
            }
            [$chapter, $start, $end] = [1, $first, $to ?? $first];
        } elseif ($verse === null) {
            if ($to !== null) {
                throw new InvalidReference(self::CROSS_CHAPTER);   // "John 3-4", "John 3-4:2"
            }
            [$chapter, $start, $end, $whole] = [$first, 1, null, true];
        } else {
            if ($toChapter !== null && $toChapter !== $first) {
                throw new InvalidReference(self::CROSS_CHAPTER);   // "John 3:16-4:2"
            }
            [$chapter, $start, $end] = [$first, $verse, $to ?? $verse];
        }

        if ($chapter < 1 || $start < 1) {
            throw new InvalidReference(self::FORMAT);
        }

        if ($chapter > $book['chapters']) {
            throw new InvalidReference("{$book['name']} has {$book['chapters']} " . ($book['chapters'] === 1 ? 'chapter.' : 'chapters.'));
        }

        $versesInChapter = KjvCanon::CHAPTER_VERSES[$book['code']][$chapter - 1];
        $end ??= $versesInChapter;

        if ($start > $versesInChapter || $end > $versesInChapter) {
            throw new InvalidReference("{$name} {$chapter} has {$versesInChapter} verses.");
        }

        if ($start > $end) {
            throw new InvalidReference(self::FORMAT);   // "John 3:18-16"
        }

        if ($end - $start + 1 > $this->maxVerses) {
            throw new InvalidReference("Choose up to {$this->maxVerses} verses.");
        }

        $display = $whole
            ? "{$name} {$chapter}"
            : "{$name} {$chapter}:{$start}" . ($end > $start ? "-{$end}" : '');

        return new ScriptureReference($index + 1, $book['code'], $chapter, $start, $end, $whole, $display);
    }

    /** @return array<string, string> every accepted book name => USFM code */
    public static function aliases(): array
    {
        $codes = array_column(KjvCanon::BOOKS, 'code');

        return array_map(fn (int $index) => $codes[$index], self::lookup());
    }

    /** "1John" / "I John" / "First John" => "1 john"; "Gen." => "gen". */
    private static function bookKey(string $book): string
    {
        $name = strtolower(trim((string) preg_replace('/[\s.]+/', ' ', $book)));

        if (preg_match('/^([1-3]) ?(.+)$/', $name, $m)) {
            return "{$m[1]} {$m[2]}";
        }

        if (preg_match('/^(iii|ii|i|first|second|third) (.+)$/', $name, $m)) {
            return self::ORDINALS[$m[1]] . " {$m[2]}";
        }

        return $name;
    }

    /** @return array<string, int> */
    private static function lookup(): array
    {
        if (self::$lookup !== null) {
            return self::$lookup;
        }

        $positions = array_flip(array_column(KjvCanon::BOOKS, 'code'));
        $lookup = [];
        foreach (self::ALIASES as $code => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($lookup[$alias])) {
                    throw new LogicException("Book alias \"{$alias}\" is listed twice.");
                }
                $lookup[$alias] = $positions[$code];
            }
        }

        return self::$lookup = $lookup;
    }
}
