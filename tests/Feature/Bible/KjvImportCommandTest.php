<?php

namespace Tests\Feature\Bible;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** bible:import-kjv refusals, using throw-away files (the real file has its own test). */
class KjvImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = "GEN 1:1 In the beginning [was] the first line.\nGEN 1:2 And the second line.\n";

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_a_missing_file_is_refused(): void
    {
        config(['bible.kjv.file' => sys_get_temp_dir() . '/no-such-kjv-file.txt.gz']);

        $this->artisan('bible:import-kjv')
            ->expectsOutputToContain('The KJV data file was not found')
            ->assertFailed()
            ->run();

        $this->assertNothingWritten();
    }

    public function test_a_file_that_is_not_gzip_is_refused(): void
    {
        config(['bible.kjv.file' => $this->tempFile(self::TEXT)]);

        $this->artisan('bible:import-kjv')
            ->expectsOutputToContain('The KJV data file could not be unzipped')
            ->assertFailed()
            ->run();

        $this->assertNothingWritten();
    }

    public function test_a_checksum_mismatch_is_refused(): void
    {
        config(['bible.kjv.file' => $this->tempFile(gzencode(self::TEXT, 9))]);   // the real pinned sha256 stays

        $this->artisan('bible:import-kjv')
            ->expectsOutputToContain('The KJV text does not match the pinned checksum. Nothing was written.')
            ->expectsOutputToContain('actual   sha256 ' . hash('sha256', self::TEXT))
            ->assertFailed()
            ->run();

        $this->assertNothingWritten();
    }

    public function test_a_text_matching_its_checksum_but_not_the_66_book_canon_is_refused(): void
    {
        config([
            'bible.kjv.file'   => $this->tempFile(gzencode(self::TEXT, 9)),
            'bible.kjv.sha256' => hash('sha256', self::TEXT),
        ]);

        $this->artisan('bible:import-kjv')
            ->expectsOutputToContain('The KJV text has problems. Nothing was written.')
            ->expectsOutputToContain('Genesis has 1 chapter(s) and 2 verse(s); expected 50 and 1533.')
            ->expectsOutputToContain('Exodus (EXO) is missing.')
            ->expectsOutputToContain('... and 46 more.')
            ->assertFailed()
            ->run();

        $this->assertNothingWritten();
    }

    private function tempFile(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'kjv');
        file_put_contents($file, $contents);

        return $this->tempFiles[] = $file;
    }

    private function assertNothingWritten(): void
    {
        $this->assertSame(0, BibleBook::count());
        $this->assertSame(0, BibleVerse::count());
    }
}
