<?php

namespace App\Console\Commands;

use App\Services\Programs\WeeklyProgramGenerator;
use Illuminate\Console\Command;

/**
 * Creates church services for the weekly programmes in config/church_programs.php.
 * Scheduled daily at 01:00 (routes/console.php). Safe to run any number of times.
 */
class GenerateProgramServices extends Command
{
    protected $signature = 'programs:generate-services
        {--weeks=8 : How many weeks ahead to create services for (1-26)}
        {--dry-run : Report what would happen without writing anything}';

    protected $description = 'Create church services for the weekly programmes in config/church_programs.php';

    public function handle(WeeklyProgramGenerator $generator): int
    {
        $weeks = (string) $this->option('weeks');

        if (! ctype_digit($weeks) || (int) $weeks < WeeklyProgramGenerator::MIN_WEEKS || (int) $weeks > WeeklyProgramGenerator::MAX_WEEKS) {
            $this->error('--weeks must be a whole number from ' . WeeklyProgramGenerator::MIN_WEEKS . ' to ' . WeeklyProgramGenerator::MAX_WEEKS . '.');

            return self::FAILURE;
        }

        $programs = config('church_programs.programs', []);

        if (! is_array($programs)) {
            $this->error("config/church_programs.php: 'programs' must be a list. Nothing was written.");

            return self::FAILURE;
        }

        if ($programs === []) {
            $this->info('No weekly programmes configured.');

            return self::SUCCESS;
        }

        if ($problems = $generator->validate($programs)) {
            $this->error('config/church_programs.php has problems. Nothing was written.');
            foreach ($problems as $problem) {
                $this->line("  - {$problem}");
            }

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN: nothing will be written.');
        }

        $summary = $generator->generate($programs, (int) $weeks, $dryRun);

        $this->table(
            ['Programme', $dryRun ? 'Would create' : 'Created', 'Existing', 'Skipped'],
            collect($summary)->map(fn (array $counts, string $key) => $counts['active']
                ? [$key, $counts['created'], $counts['existing'], $counts['skipped']]
                : [$key . ' (inactive)', '-', '-', '-'])->values()->all(),
        );

        return self::SUCCESS;
    }
}
