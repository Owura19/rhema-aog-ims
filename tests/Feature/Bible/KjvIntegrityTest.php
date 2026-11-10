<?php

namespace Tests\Feature\Bible;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Support\Bible\KjvCanon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The real database/data/kjv file: imports completely, cleaned, and a second run changes nothing. */
class KjvIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** Peak memory one import may add (measured about 31 MB; PHP's CLI default limit is 128 MB). */
    private const MAX_IMPORT_MEMORY = 48 * 1024 * 1024;

    public function test_the_pinned_kjv_file_imports_completely_and_a_second_run_changes_nothing(): void
    {
        $this->artisan('bible:import-kjv', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN: nothing was written.')
            ->expectsOutputToContain('Verses: 31102 inserted, 0 updated, 0 unchanged, 0 removed.')
            ->assertSuccessful()
            ->run();
        $this->assertSame(0, BibleVerse::count());

        $before = memory_get_usage();
        memory_reset_peak_usage();
        $this->artisan('bible:import-kjv')
            ->expectsOutputToContain('Books: 66.')
            ->expectsOutputToContain('Verses: 31102 inserted, 0 updated, 0 unchanged, 0 removed.')
            ->assertSuccessful()
            ->run();
        $this->assertImportMemory($before, 'the import');

        // Every book: its chapters and verses as in the standard KJV.
        $this->assertSame(66, BibleBook::count());
        $this->assertSame(KjvCanon::TOTAL_VERSES, BibleVerse::count());
        $actual = DB::table('bible_verses')
            ->selectRaw('book_id, MAX(chapter) AS chapters, COUNT(*) AS verses, COUNT(DISTINCT chapter) AS distinct_chapters')
            ->groupBy('book_id')->orderBy('book_id')->get()
            ->map(fn ($r) => [(int) $r->book_id, (int) $r->chapters, (int) $r->verses, (int) $r->distinct_chapters])->all();
        $expected = array_map(fn ($b, $i) => [$i + 1, $b['chapters'], $b['verses'], $b['chapters']], KjvCanon::BOOKS, array_keys(KjvCanon::BOOKS));
        $this->assertSame($expected, $actual);

        // Spot checks, cleaned: no pilcrows, no italic brackets; ’ and — kept.
        $this->assertSame('In the beginning God created the heaven and the earth.', $this->verse('GEN', 1, 1));
        $this->assertSame('A Psalm of David. The LORD is my shepherd; I shall not want.', $this->verse('PSA', 23, 1));
        $this->assertSame('For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.', $this->verse('JHN', 3, 16));
        $this->assertSame('And we know that all things work together for good to them that love God, to them who are the called according to his purpose.', $this->verse('ROM', 8, 28));
        $this->assertSame('The grace of our Lord Jesus Christ be with you all. Amen.', $this->verse('REV', 22, 21));
        $this->assertSame('Whosoever denieth the Son, the same hath not the Father: but he that acknowledgeth the Son hath the Father also.', $this->verse('1JN', 2, 23));
        $this->assertSame('Yet now, if thou wilt forgive their sin—; and if not, blot me, I pray thee, out of thy book which thou hast written.', $this->verse('EXO', 32, 32));
        $this->assertSame(0, BibleVerse::where('text', 'like', '%¶%')->count());
        $this->assertSame(0, BibleVerse::where('text', 'like', '%[%')->orWhere('text', 'like', '%]%')->count());
        $this->assertSame(0, BibleVerse::where('text', '')->count());

        $before = memory_get_usage();
        memory_reset_peak_usage();
        $this->artisan('bible:import-kjv')
            ->expectsOutputToContain('Verses: 0 inserted, 0 updated, 31102 unchanged, 0 removed.')
            ->assertSuccessful()
            ->run();
        $this->assertImportMemory($before, 'the second (no-change) run');
        $this->assertSame(KjvCanon::TOTAL_VERSES, BibleVerse::count());
    }

    public function test_the_readme_records_the_pinned_checksum_and_the_licence(): void
    {
        $readme = file_get_contents(database_path('data/kjv/README.md'));

        $this->assertStringContainsString(config('bible.kjv.sha256'), $readme);
        $this->assertSame(hash('sha256', gzdecode(file_get_contents(config('bible.kjv.file')))), config('bible.kjv.sha256'));
        $this->assertStringContainsString('Public Domain', $readme);
        $this->assertStringContainsString('https://eBible.org/Scriptures/eng-kjv2006_vpl.zip', $readme);
    }

    private function assertImportMemory(int $before, string $what): void
    {
        $used = memory_get_peak_usage() - $before;

        $this->assertLessThan(self::MAX_IMPORT_MEMORY, $used, sprintf('%s added %.1f MB at its peak (limit %d MB)', $what, $used / 1048576, self::MAX_IMPORT_MEMORY / 1048576));
    }

    private function verse(string $code, int $chapter, int $verse): ?string
    {
        return BibleVerse::query()
            ->whereHas('book', fn ($q) => $q->where('code', $code))
            ->where(['chapter' => $chapter, 'verse' => $verse])
            ->value('text');
    }
}
