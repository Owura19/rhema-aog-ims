<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\Biometric\UserMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Matches the fingerprint device's users (users.csv from the bridge: php bridge.exe --export-users) to
 * members, to fill members.fingerprint_id in bulk.
 *
 * Default is a DRY RUN: nothing in the database changes. A review file (names included, for the office) is
 * written under storage/app/private/biometric-match/. With --apply the same run first writes that review
 * file and prints the counts, then asks yes/no; only on "yes" are the IDs of `confident` rows set, in one
 * transaction. `suggested`, `ambiguous`, `conflict`, `already_set` and `no_match` rows are never applied.
 *
 * The console shows COUNTS ONLY: no names, no IDs.
 */
class BiometricMatchUsers extends Command
{
    protected $signature = 'biometric:match-users
                            {csv : users.csv, as exported by the bridge (columns user_id and name)}
                            {--apply : set the fingerprint ID of the confident matches (asks yes/no first)}
                            {--out= : name or path of the review file (default: storage/app/private/biometric-match/)}';

    protected $description = 'Match fingerprint device users (users.csv) to members; dry run unless --apply (counts only on screen)';

    private const DIRECTORY = 'biometric-match';
    private const COLUMNS = ['device_user_id', 'device_name', 'status', 'member_id', 'member_name', 'note'];

    public function handle(UserMatcher $matcher): int
    {
        $apply = (bool) $this->option('apply');

        $csv = UserMatcher::readCsv((string) $this->argument('csv'));
        if ($csv['error']) {
            $this->error($csv['error']);

            return self::FAILURE;
        }

        $reviewTarget = $this->reviewTarget((string) $this->argument('csv'), $apply);
        if ($reviewTarget === null) {
            return self::FAILURE;
        }

        $members = Member::query()->get(['id', 'member_id', 'first_name', 'other_name', 'last_name', 'fingerprint_id']);
        $rows = $matcher->match($csv['users'], $members);

        $this->writeReview($reviewTarget, $rows);
        $this->report(count($csv['users']), $members->count(), $rows, $reviewTarget['display']);

        $confident = count(array_filter($rows, fn ($r) => $r['status'] === UserMatcher::CONFIDENT));
        if (! $apply) {
            $this->info('Dry run: nothing was changed. Review the file, then run again with --apply to set the confident matches.');

            return self::SUCCESS;
        }

        if ($confident === 0) {
            $this->info('Nothing to apply: there are no confident matches.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Set the fingerprint ID for {$confident} members?", false)) {
            $this->info('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        $result = $matcher->apply($rows);
        Log::info('biometric:match-users applied', ['set' => $result['set'], 'skipped' => $result['skipped'], 'confident' => $confident]);   // counts only
        $this->info("Set the fingerprint ID for {$result['set']} members." . ($result['skipped'] ? " Skipped {$result['skipped']} that no longer qualified (changed since the check)." : ''));

        return self::SUCCESS;
    }

    // ── review file ────────────────────────────────────────────────────

    /**
     * Where the review file goes. By default: storage/app/private/biometric-match/<csv name>-<time>-<mode>.csv.
     * --out: a name or relative path inside that folder, or an absolute path anywhere EXCEPT inside the project
     * folder outside storage/app/private (names must not end up where they could be committed).
     *
     * @return array{disk: ?string, path: string, display: string}|null
     */
    private function reviewTarget(string $csvPath, bool $apply): ?array
    {
        $out = trim((string) $this->option('out'));

        if ($out === '') {
            $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($csvPath, PATHINFO_FILENAME)) ?: 'users';
            $relative = self::DIRECTORY . '/' . $base . '-' . now()->format('Ymd-His') . ($apply ? '-apply' : '-dry-run') . '.csv';

            return ['disk' => 'local', 'path' => $relative, 'display' => Storage::disk('local')->path($relative)];
        }

        if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $out)) {
            $this->error('--out must not contain "..".');

            return null;
        }
        if (! preg_match('/\.[A-Za-z0-9]+$/', $out)) {
            $out .= '.csv';
        }

        $absolute = (bool) preg_match('#^([A-Za-z]:[\\\\/]|[\\\\/])#', $out);
        if (! $absolute) {
            $relative = self::DIRECTORY . '/' . ltrim(str_replace('\\', '/', $out), '/');

            return ['disk' => 'local', 'path' => $relative, 'display' => Storage::disk('local')->path($relative)];
        }

        $directory = realpath(dirname($out));
        if ($directory === false) {
            $this->error('The folder for --out does not exist: ' . dirname($out));

            return null;
        }
        $project = realpath(base_path());
        $allowedInside = realpath(storage_path('app/private')) ?: storage_path('app/private');
        if ($this->isInside($directory, $project) && ! $this->isInside($directory, $allowedInside)) {
            $this->error('--out would put the review file (it contains names) inside the project folder. Use a path outside it, or leave it under storage/app/private.');

            return null;
        }

        return ['disk' => null, 'path' => $directory . DIRECTORY_SEPARATOR . basename($out), 'display' => $directory . DIRECTORY_SEPARATOR . basename($out)];
    }

    private function isInside(string $path, string $folder): bool
    {
        $path = rtrim(str_replace('\\', '/', strtolower($path)), '/') . '/';
        $folder = rtrim(str_replace('\\', '/', strtolower($folder)), '/') . '/';

        return str_starts_with($path, $folder);
    }

    /** @param array{disk: ?string, path: string, display: string} $target */
    private function writeReview(array $target, array $rows): void
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, self::COLUMNS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($key) => $this->safeCell((string) $row[$key]), self::COLUMNS), ',', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($target['disk']) {
            Storage::disk($target['disk'])->put($target['path'], $content);
        } else {
            file_put_contents($target['path'], $content);
        }
    }

    /** A cell that starts with = + - @ would be run as a formula by Excel: make it plain text. */
    private function safeCell(string $value): string
    {
        return $value !== '' && strpbrk($value[0], '=+-@') !== false ? "'" . $value : $value;
    }

    // ── console report (counts only) ───────────────────────────────────

    private function report(int $deviceUsers, int $members, array $rows, string $reviewPath): void
    {
        $counts = array_fill_keys(UserMatcher::STATUSES, 0);
        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }

        $this->newLine();
        $this->line("Device users in the CSV: {$deviceUsers}");
        $this->line("Members compared:        {$members}");
        foreach ($counts as $status => $count) {
            $this->line(sprintf('  %-12s %d', $status, $count));
        }
        $this->newLine();
        $this->line('Review file (contains names, keep it private): ' . $reviewPath);
    }
}
