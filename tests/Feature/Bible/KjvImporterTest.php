<?php

namespace Tests\Feature\Bible;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Services\Bible\KjvImporter;
use App\Services\Bible\KjvImportException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The importer on a tiny made-up text with its own 3-book canon (the real file has its own test). */
class KjvImporterTest extends TestCase
{
    use RefreshDatabase;

    private const CANON = [
        ['code' => 'GEN', 'ebible' => 'GEN', 'name' => 'Genesis', 'testament' => 'OT', 'chapters' => 2, 'verses' => 3],
        ['code' => 'JHN', 'ebible' => 'JOH', 'name' => 'John',    'testament' => 'NT', 'chapters' => 1, 'verses' => 2],
        ['code' => '1JN', 'ebible' => '1JO', 'name' => '1 John',  'testament' => 'NT', 'chapters' => 1, 'verses' => 1],
    ];

    private const TEXT = "GEN 1:1 In the beginning [was] the first line.\n"
        . "GEN 1:2 ¶ And the second line [was] good.\n"
        . "GEN 2:1 Thus the test’s chapters were finished.\n"
        . "JOH 1:1 ¶ The æon [is] long—very long.\n"
        . "JOH 1:2 And the fixture ended.\n"
        . "1JO 1:1 Whosoever [[but] he that readeth this hath read it also].\n";

    public function test_it_loads_the_books_in_canonical_order_with_their_counts(): void
    {
        $this->import();

        $this->assertSame([
            ['id' => 1, 'code' => 'GEN', 'name' => 'Genesis', 'testament' => 'OT', 'chapter_count' => 2, 'verse_count' => 3],
            ['id' => 2, 'code' => 'JHN', 'name' => 'John',    'testament' => 'NT', 'chapter_count' => 1, 'verse_count' => 2],
            ['id' => 3, 'code' => '1JN', 'name' => '1 John',  'testament' => 'NT', 'chapter_count' => 1, 'verse_count' => 1],
        ], BibleBook::query()->orderBy('id')->get()->map->only(['id', 'code', 'name', 'testament', 'chapter_count', 'verse_count'])->all());
    }

    public function test_it_loads_every_verse_and_reports_them_as_inserted(): void
    {
        $counts = $this->import();

        $this->assertSame(['books' => 3, 'inserted' => 6, 'updated' => 0, 'unchanged' => 0, 'removed' => 0], $counts);
        $this->assertSame(6, BibleVerse::count());
        $this->assertSame('In the beginning was the first line.', $this->verse('GEN', 1, 1));
        $this->assertSame('And the fixture ended.', $this->verse('JHN', 1, 2));
    }

    public function test_pilcrows_are_removed(): void
    {
        $this->import();

        $this->assertSame('And the second line was good.', $this->verse('GEN', 1, 2));
        $this->assertSame(0, BibleVerse::where('text', 'like', '%¶%')->count());
    }

    public function test_italic_brackets_are_removed_but_the_words_kept_including_a_nested_pair(): void
    {
        $this->import();

        $this->assertSame('Whosoever but he that readeth this hath read it also.', $this->verse('1JN', 1, 1));
        $this->assertSame(0, BibleVerse::where('text', 'like', '%[%')->orWhere('text', 'like', '%]%')->count());
    }

    public function test_curly_apostrophes_ae_and_em_dashes_are_kept(): void
    {
        $this->import();

        $this->assertSame('Thus the test’s chapters were finished.', $this->verse('GEN', 2, 1));
        $this->assertSame('The æon is long—very long.', $this->verse('JHN', 1, 1));
    }

    public function test_a_second_import_changes_nothing(): void
    {
        $this->import();
        $before = BibleVerse::query()->orderBy('id')->get(['id', 'book_id', 'chapter', 'verse', 'text'])->toArray();

        $counts = $this->import();

        $this->assertSame(['books' => 3, 'inserted' => 0, 'updated' => 0, 'unchanged' => 6, 'removed' => 0], $counts);
        $this->assertSame($before, BibleVerse::query()->orderBy('id')->get(['id', 'book_id', 'chapter', 'verse', 'text'])->toArray());
    }

    public function test_an_altered_verse_is_restored(): void
    {
        $this->import();
        DB::table('bible_verses')->where(['book_id' => 1, 'chapter' => 1, 'verse' => 1])->update(['text' => 'Tampered.']);

        $counts = $this->import();

        $this->assertSame(['books' => 3, 'inserted' => 0, 'updated' => 1, 'unchanged' => 5, 'removed' => 0], $counts);
        $this->assertSame('In the beginning was the first line.', $this->verse('GEN', 1, 1));
    }

    public function test_verses_and_books_not_in_the_text_are_removed(): void
    {
        $this->import();
        DB::table('bible_books')->insert(['id' => 4, 'code' => 'REV', 'name' => 'Revelation', 'testament' => 'NT', 'chapter_count' => 1, 'verse_count' => 1]);
        DB::table('bible_verses')->insert([
            ['book_id' => 4, 'chapter' => 1, 'verse' => 1, 'text' => 'Not in the text.'],
            ['book_id' => 2, 'chapter' => 1, 'verse' => 3, 'text' => 'Not in the text either.'],
        ]);

        $counts = $this->import();

        $this->assertSame(['books' => 3, 'inserted' => 0, 'updated' => 0, 'unchanged' => 6, 'removed' => 2], $counts);
        $this->assertSame([1, 2, 3], BibleBook::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame(6, BibleVerse::count());
    }

    public function test_a_dry_run_reports_the_changes_but_writes_nothing(): void
    {
        $counts = $this->import(dryRun: true);

        $this->assertSame(['books' => 3, 'inserted' => 6, 'updated' => 0, 'unchanged' => 0, 'removed' => 0], $counts);
        $this->assertSame(0, BibleBook::count());
        $this->assertSame(0, BibleVerse::count());
    }

    public function test_a_bad_text_is_refused_and_existing_rows_are_left_untouched(): void
    {
        $this->import();
        $before = BibleVerse::query()->orderBy('id')->pluck('text', 'id')->all();

        try {
            $this->import(str_replace('GEN 1:2 ', 'GEN 1:3 ', self::TEXT));
            $this->fail('a text with a missing verse should have been refused');
        } catch (KjvImportException $e) {
            $this->assertContains('Line 2: GEN 1:3 follows GEN 1:1.', $e->problems);
        }

        $this->assertSame($before, BibleVerse::query()->orderBy('id')->pluck('text', 'id')->all());
    }

    public function test_a_books_table_holding_different_books_is_refused(): void
    {
        $this->import();
        $canon = self::CANON;
        $canon[1] = ['code' => 'EXO', 'ebible' => 'EXO', 'name' => 'Exodus', 'testament' => 'OT', 'chapters' => 1, 'verses' => 2];
        $importer = new KjvImporter();
        $parsed = $importer->parse(str_replace('JOH ', 'EXO ', self::TEXT), $canon);

        try {
            $importer->import($parsed);
            $this->fail('a books table with JHN where EXO is expected should have been refused');
        } catch (KjvImportException $e) {
            $this->assertSame(['bible_books id 2 is JHN, expected EXO. Nothing was written.'], $e->problems);
        }

        $this->assertSame('JHN', BibleBook::find(2)->code);
        $this->assertSame('The æon is long—very long.', $this->verse('JHN', 1, 1));
    }

    public function test_a_verse_belongs_to_its_book(): void
    {
        $this->import();

        $verse = BibleVerse::query()->where(['book_id' => 2, 'chapter' => 1, 'verse' => 2])->first();

        $this->assertSame('John', $verse->book->name);
        $this->assertSame(2, BibleBook::find(2)->verses()->count());
    }

    public static function badTexts(): array
    {
        $text = self::TEXT;

        return [
            'malformed line'         => [str_replace('GEN 2:1 ', 'GEN 2 1 ', $text), 'Line 3 is not in the form "BOOK chapter:verse text".'],
            'windows line endings'   => [str_replace("\n", "\r\n", $text), 'Line 1 is not in the form "BOOK chapter:verse text".'],
            'unknown book code'      => [str_replace('JOH 1:1 ', 'JHN 1:1 ', $text), 'Line 4: unknown book code JHN.'],
            'verse gap'              => [str_replace('GEN 1:2 ', 'GEN 1:3 ', $text), 'Line 2: GEN 1:3 follows GEN 1:1.'],
            'repeated verse'         => [str_replace('GEN 1:2 ', 'GEN 1:1 ', $text), 'Line 2: GEN 1:1 follows GEN 1:1.'],
            'not starting at 1:1'    => [str_replace('GEN 1:1 ', 'GEN 1:5 ', $text), 'Line 1: GEN 1:5 is the first line (expected GEN 1:1).'],
            'book missing at end'    => [str_replace("1JO 1:1 Whosoever [[but] he that readeth this hath read it also].\n", '', $text), '1 John (1JO) is missing.'],
            'wrong count for a book' => [str_replace("GEN 2:1 Thus the test’s chapters were finished.\n", '', $text), 'Genesis has 1 chapter(s) and 2 verse(s); expected 2 and 3.'],
            'verse with no text'     => [str_replace('GEN 1:1 In the beginning [was] the first line.', 'GEN 1:1 ¶ []', $text), 'Line 1: GEN 1:1 has no text.'],
        ];
    }

    #[DataProvider('badTexts')]
    public function test_problems_in_the_text_are_reported_and_nothing_is_written(string $text, string $problem): void
    {
        try {
            $this->import($text);
            $this->fail('the text should have been refused');
        } catch (KjvImportException $e) {
            $this->assertContains($problem, $e->problems);
        }

        $this->assertSame(0, BibleBook::count());
        $this->assertSame(0, BibleVerse::count());
    }

    /** @return array{books: int, inserted: int, updated: int, unchanged: int, removed: int} */
    private function import(string $text = self::TEXT, bool $dryRun = false): array
    {
        $importer = new KjvImporter();

        return $importer->import($importer->parse($text, self::CANON), $dryRun);
    }

    private function verse(string $code, int $chapter, int $verse): ?string
    {
        return BibleVerse::query()
            ->whereHas('book', fn ($q) => $q->where('code', $code))
            ->where(['chapter' => $chapter, 'verse' => $verse])
            ->value('text');
    }
}
