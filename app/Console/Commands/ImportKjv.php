<?php

namespace App\Console\Commands;

use App\Services\Bible\KjvImporter;
use App\Services\Bible\KjvImportException;
use App\Support\Bible\KjvCanon;
use Illuminate\Console\Command;

/**
 * Loads the KJV from database/data/kjv (config/bible.php) into bible_books and bible_verses.
 * Refuses unless the UNZIPPED text matches the pinned sha256 and every book, chapter and verse
 * checks out. Safe to run any number of times: a second run changes nothing.
 * Deploy: run once after `php artisan migrate`.
 */
class ImportKjv extends Command
{
    private const SHOW_PROBLEMS = 20;

    protected $signature = 'bible:import-kjv
        {--dry-run : Check the file and report what would change, without writing anything}';

    protected $description = 'Load the King James Version (database/data/kjv) into bible_books and bible_verses';

    public function handle(KjvImporter $importer): int
    {
        $file = (string) config('bible.kjv.file');
        $expected = (string) config('bible.kjv.sha256');

        if (! is_file($file)) {
            $this->error("The KJV data file was not found: {$file}. Nothing was written.");

            return self::FAILURE;
        }

        $text = @gzdecode((string) file_get_contents($file));

        if ($text === false) {
            $this->error("The KJV data file could not be unzipped: {$file}. Nothing was written.");

            return self::FAILURE;
        }

        $actual = hash('sha256', $text);

        if (! hash_equals($expected, $actual)) {
            $this->error('The KJV text does not match the pinned checksum. Nothing was written.');
            $this->line("  expected sha256 {$expected}");
            $this->line("  actual   sha256 {$actual}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $counts = $importer->import($importer->parse($text, KjvCanon::BOOKS), $dryRun);
        } catch (KjvImportException $e) {
            $this->error('The KJV text has problems. Nothing was written.');
            foreach (array_slice($e->problems, 0, self::SHOW_PROBLEMS) as $problem) {
                $this->line("  - {$problem}");
            }
            if (($more = count($e->problems) - self::SHOW_PROBLEMS) > 0) {
                $this->line("  ... and {$more} more.");
            }

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN: nothing was written. An import would make these changes:');
        }

        $this->info("Books: {$counts['books']}.");
        $this->info("Verses: {$counts['inserted']} inserted, {$counts['updated']} updated, {$counts['unchanged']} unchanged, {$counts['removed']} removed.");

        return self::SUCCESS;
    }
}
