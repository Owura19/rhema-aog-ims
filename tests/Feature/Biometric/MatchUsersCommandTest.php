<?php

namespace Tests\Feature\Biometric;

use App\Models\Member;
use App\Services\Biometric\UserMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** biometric:match-users. FAKE names only (NATO words); no real data is ever used. */
class MatchUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');   // the review files go to a throw-away disk
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function member(string $first, string $last, ?string $other = null, ?int $fingerprint = null): Member
    {
        return Member::create(['first_name' => $first, 'last_name' => $last, 'other_name' => $other, 'fingerprint_id' => $fingerprint]);
    }

    /** @param list<array{0: string|int, 1: string}> $rows */
    private function csv(array $rows, string $header = 'user_id,name', string $eol = "\n", string $prefix = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'umt');
        $lines = [$header];
        foreach ($rows as [$id, $name]) {
            $lines[] = $id . ',' . '"' . str_replace('"', '""', $name) . '"';
        }
        file_put_contents($path, $prefix . implode($eol, $lines) . $eol);
        $this->tempFiles[] = $path;

        return $path;
    }

    /** The one review file, as a list of rows keyed by column. */
    private function review(): array
    {
        $files = Storage::disk('local')->files('biometric-match');
        $this->assertCount(1, $files, 'exactly one review file');

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, Storage::disk('local')->get($files[0]));
        rewind($stream);
        $header = fgetcsv($stream, null, ',', '"', '');
        $rows = [];
        while (($line = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = array_combine($header, $line);
        }
        fclose($stream);

        return $rows;
    }

    /** @return array<string, array<string, string>> the review rows by device_name (names are unique per test) */
    private function byName(): array
    {
        $out = [];
        foreach ($this->review() as $row) {
            $out[$row['device_name']] = $row;
        }

        return $out;
    }

    private function snapshot(): array
    {
        return Member::withTrashed()->orderBy('id')->get(['id', 'fingerprint_id', 'updated_at'])->map(fn ($m) => [$m->id, $m->fingerprint_id, (string) $m->updated_at])->all();
    }

    private function fingerprintIds(): array
    {
        return Member::orderBy('id')->pluck('fingerprint_id', 'first_name')->all();
    }

    // ── matching ──────────────────────────────────────────────────────

    public function test_word_order_titles_and_punctuation_are_ignored(): void
    {
        $alpha = $this->member('Alpha', 'Bravo');
        $charlie = $this->member('Charlie', 'Echo', 'Delta');          // first + other + last
        $foxtrot = $this->member('Foxtrot', "O'Hotel");
        $india = $this->member('India', 'Juliet');
        $path = $this->csv([[11, 'Bravo Alpha'], [12, 'Rev. ECHO,  delta-charlie'], [13, 'Pastor Foxtrot OHotel'], [14, 'Sis India Dr Juliet']]);

        $this->artisan('biometric:match-users', ['csv' => $path])->assertSuccessful();

        $rows = $this->byName();
        foreach (['Bravo Alpha' => $alpha, 'Rev. ECHO,  delta-charlie' => $charlie, 'Pastor Foxtrot OHotel' => $foxtrot, 'Sis India Dr Juliet' => $india] as $device => $member) {
            $this->assertSame(['confident', $member->member_id], [$rows[$device]['status'], $rows[$device]['member_id']], $device);
        }
        $this->assertSame('Charlie Delta Echo', $rows['Rev. ECHO,  delta-charlie']['member_name']);
    }

    public function test_two_members_with_the_same_name_make_it_ambiguous(): void
    {
        $first = $this->member('Kilo', 'Lima');
        $second = $this->member('Lima', 'Kilo');
        $path = $this->csv([[21, 'Kilo Lima']]);

        $this->artisan('biometric:match-users', ['csv' => $path, '--apply' => true])->assertSuccessful()->expectsOutputToContain('Nothing to apply');

        $row = $this->review()[0];
        $this->assertSame('ambiguous', $row['status']);
        $this->assertSame("{$first->member_id} | {$second->member_id}", $row['member_id']);
        $this->assertStringContainsString('2 members', $row['note']);
        $this->assertSame([null, null], array_values($this->fingerprintIds()));
    }

    public function test_conflicts_are_never_confident(): void
    {
        $this->member('Mike', 'November', null, 50);                       // has a different ID
        $this->member('Oscar', 'Papa');
        $holder = $this->member('Quebec', 'Romeo', null, 70);              // holds the ID the device gives Oscar
        $this->member('Sierra', 'Tango', null, 80);                        // already has this ID: already_set
        $this->member('Uniform', 'Victor');
        $this->member('Whiskey', 'Xray');                                  // two rows share one device ID
        $this->member('Yankee', 'Zulu');                                   // two device IDs, one member
        $path = $this->csv([
            [60, 'Mike November'], [70, 'Oscar Papa'], [80, 'Sierra Tango'],
            [90, 'Uniform Victor'], [90, 'Whiskey Xray'], [91, 'Yankee Zulu'], [92, 'Zulu Yankee'],
        ]);

        $this->artisan('biometric:match-users', ['csv' => $path])->assertSuccessful();

        $rows = $this->byName();
        $this->assertSame(['conflict', 'the member already has a different fingerprint ID (50)'], [$rows['Mike November']['status'], $rows['Mike November']['note']]);
        $this->assertSame(['conflict', "this device user ID is already held by {$holder->member_id}"], [$rows['Oscar Papa']['status'], $rows['Oscar Papa']['note']]);
        $this->assertSame('already_set', $rows['Sierra Tango']['status']);
        foreach (['Uniform Victor', 'Whiskey Xray'] as $name) {
            $this->assertSame(['conflict', 'this device user ID appears 2 times in the CSV'], [$rows[$name]['status'], $rows[$name]['note']]);
        }
        foreach (['Yankee Zulu', 'Zulu Yankee'] as $name) {
            $this->assertSame(['conflict', 'another device user matches this same member'], [$rows[$name]['status'], $rows[$name]['note']]);
        }
    }

    public function test_already_set_loses_to_a_conflict_when_another_member_holds_the_same_id(): void
    {
        $this->member('Sierra', 'Tango', null, 80);
        $other = $this->member('Other', 'Holder', null, 80);
        $path = $this->csv([[80, 'Sierra Tango']]);

        $this->artisan('biometric:match-users', ['csv' => $path])->assertSuccessful();

        $this->assertSame(['conflict', "this device user ID is already held by {$other->member_id}"], [$this->review()[0]['status'], $this->review()[0]['note']]);
    }

    public function test_close_matches_are_only_suggested_and_never_applied(): void
    {
        $this->member('Alpha', 'Charlie', 'Bravo');                        // device gives "Alpha Charlie": every word is in the member's name
        $this->member('Delta', 'Foxtrot');                                 // device spells it "Delta Foxtro"
        $this->member('Golf', 'Hotel');                                    // device gives only "Golf": too weak
        $this->member('India', 'Kilo', 'Juliet');
        $this->member('India', 'Lima', 'Juliet');                          // "India Juliet" fits both: none chosen
        $this->member('Mike', 'Oscar', 'November', 5);                     // close, but already has an ID
        $this->member('Papa', 'Quebec');                                   // the one confident row, so --apply has something to do
        $path = $this->csv([[101, 'Alpha Charlie'], [102, 'Delta Foxtro'], [103, 'Golf'], [104, 'India Juliet'], [105, 'Mike Oscar'], [106, 'Quebec Papa']]);

        $this->artisan('biometric:match-users', ['csv' => $path, '--apply' => true])
            ->expectsConfirmation('Set the fingerprint ID for 1 members?', 'yes')
            ->expectsOutputToContain('Set the fingerprint ID for 1 members.')
            ->assertSuccessful();

        $rows = $this->byName();
        $this->assertSame(['suggested', 'close match: every word of the shorter name appears in the longer name'], [$rows['Alpha Charlie']['status'], $rows['Alpha Charlie']['note']]);
        $this->assertSame(['suggested', 'close match: very similar words (spelling differs slightly)'], [$rows['Delta Foxtro']['status'], $rows['Delta Foxtro']['note']]);
        $this->assertSame('no_match', $rows['Golf']['status']);
        $this->assertSame(['no_match', '2 members are close to this name; none chosen'], [$rows['India Juliet']['status'], $rows['India Juliet']['note']]);
        $this->assertSame('suggested', $rows['Mike Oscar']['status']);
        $this->assertStringContainsString('the member already has fingerprint ID 5', $rows['Mike Oscar']['note']);
        $this->assertSame('confident', $rows['Quebec Papa']['status']);

        // Only the confident row was written
        $this->assertSame(['Alpha' => null, 'Delta' => null, 'Golf' => null, 'India' => null, 'Mike' => 5, 'Papa' => 106], $this->fingerprintIds() + ['India' => null]);
    }

    public function test_rows_that_cannot_be_used_are_no_match(): void
    {
        $this->member('Alpha', 'Bravo');
        $path = $this->csv([['abc', 'Alpha Bravo'], [0, 'Alpha Bravo'], [2147483648, 'Alpha Bravo'], [7, ''], [8, 'Rev Dr'], [9, 'Nobody Known']]);

        $this->artisan('biometric:match-users', ['csv' => $path])->assertSuccessful();

        $rows = array_column($this->review(), null, 'device_user_id');
        foreach (['abc', '0', '2147483648'] as $badId) {
            $this->assertSame('no_match', $rows[$badId]['status']);
            $this->assertStringContainsString('not a whole number', $rows[$badId]['note']);
        }
        $this->assertSame(['no_match', 'the name is empty (or only titles)'], [$rows['7']['status'], $rows['7']['note']]);
        $this->assertSame('the name is empty (or only titles)', $rows['8']['note']);
        $this->assertSame(['no_match', 'no member with this name'], [$rows['9']['status'], $rows['9']['note']]);
    }

    public function test_soft_deleted_members_are_ignored_and_do_not_hold_ids(): void
    {
        $this->member('Gone', 'Person', null, 15)->delete();
        $this->member('Alpha', 'Bravo');
        $path = $this->csv([[15, 'Alpha Bravo'], [16, 'Gone Person']]);

        $this->artisan('biometric:match-users', ['csv' => $path])->assertSuccessful();

        $rows = $this->byName();
        $this->assertSame('confident', $rows['Alpha Bravo']['status']);    // the deleted member's ID 15 is not "held"
        $this->assertSame('no_match', $rows['Gone Person']['status']);
    }

    // ── dry run and the console ───────────────────────────────────────

    public function test_a_dry_run_writes_nothing_to_the_database_and_writes_the_review_file(): void
    {
        $this->member('Alpha', 'Bravo');
        $this->member('Charlie', 'Delta', null, 3);
        $path = $this->csv([[1, 'Alpha Bravo'], [2, 'Charlie Delta'], [3, 'Unknown Person'], [4, 'Echo Foxtrot']]);
        $before = $this->snapshot();
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|truncate|alter|drop)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->artisan('biometric:match-users', ['csv' => $path])->assertSuccessful()->expectsOutputToContain('Dry run: nothing was changed');

        $this->assertSame([], $writes);
        $this->assertSame($before, $this->snapshot());
        $files = Storage::disk('local')->files('biometric-match');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^biometric-match/umt[A-Za-z0-9-]*-\d{8}-\d{6}-dry-run\.csv$#', $files[0]);
        $this->assertStringStartsWith("device_user_id,device_name,status,member_id,member_name,note\n", Storage::disk('local')->get($files[0]));
        $this->assertCount(4, $this->review());
    }

    public function test_the_console_shows_counts_and_the_review_path_but_no_names_or_ids(): void
    {
        $this->member('Alpha', 'Bravo');
        $this->member('Charlie', 'Delta', null, 3);
        $path = $this->csv([[1001, 'Bravo Alpha'], [3, 'Charlie Delta'], [1003, 'Unknown Person']]);

        Artisan::call('biometric:match-users', ['csv' => $path]);
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/Device users in the CSV: 3/', $output);
        $this->assertMatchesRegularExpression('/Members compared:\s+2/', $output);
        foreach (['confident' => 1, 'already_set' => 1, 'conflict' => 0, 'ambiguous' => 0, 'suggested' => 0, 'no_match' => 1] as $status => $count) {
            $this->assertMatchesRegularExpression('/^\s+' . $status . '\s+' . $count . '\r?$/m', $output, $status);
        }
        $this->assertStringContainsString('biometric-match', $output);

        // Everything except the review file's own path (which holds a time stamp and a temp-file name)
        $shown = implode("\n", array_filter(explode("\n", $output), fn ($line) => ! str_contains($line, 'Review file')));
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta', 'Unknown', 'Person', 'GW-', '1001', '1003'] as $private) {
            $this->assertStringNotContainsString($private, $shown, "the console must not show '{$private}'");
        }
    }

    // ── --apply ───────────────────────────────────────────────────────

    public function test_apply_sets_only_confident_rows_after_a_yes(): void
    {
        $this->member('Alpha', 'Bravo');                       // confident
        $this->member('Charlie', 'Delta');                     // confident
        $this->member('Echo', 'Foxtrot', null, 77);            // conflict (different ID)
        $this->member('Golf', 'Hotel', 'India');               // device "Golf Hotel": suggested
        $this->member('Juliet', 'Kilo');
        $this->member('Kilo', 'Juliet');                       // ambiguous
        $path = $this->csv([[1, 'Alpha Bravo'], [2, 'Delta Charlie'], [3, 'Echo Foxtrot'], [4, 'Golf Hotel'], [5, 'Juliet Kilo'], [6, 'No One']]);

        $this->artisan('biometric:match-users', ['csv' => $path, '--apply' => true])
            ->expectsOutputToContain('confident    2')
            ->expectsConfirmation('Set the fingerprint ID for 2 members?', 'yes')
            ->expectsOutputToContain('Set the fingerprint ID for 2 members.')
            ->assertSuccessful();

        $this->assertSame(['Alpha' => 1, 'Charlie' => 2, 'Echo' => 77, 'Golf' => null, 'Juliet' => null, 'Kilo' => null], $this->fingerprintIds());
        $this->assertMatchesRegularExpression('#-apply\.csv$#', Storage::disk('local')->files('biometric-match')[0]);   // the review file was written first
    }

    public function test_answering_no_changes_nothing_but_the_review_file_is_still_written(): void
    {
        $this->member('Alpha', 'Bravo');
        $path = $this->csv([[1, 'Alpha Bravo']]);
        $before = $this->snapshot();

        $this->artisan('biometric:match-users', ['csv' => $path, '--apply' => true])
            ->expectsConfirmation('Set the fingerprint ID for 1 members?', 'no')
            ->expectsOutputToContain('Cancelled. Nothing was changed.')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertCount(1, Storage::disk('local')->files('biometric-match'));
    }

    public function test_apply_without_confident_rows_does_not_ask(): void
    {
        $this->member('Echo', 'Foxtrot', null, 77);
        $path = $this->csv([[3, 'Echo Foxtrot'], [4, 'Nobody Here']]);

        $this->artisan('biometric:match-users', ['csv' => $path, '--apply' => true])->expectsOutputToContain('Nothing to apply: there are no confident matches.')->assertSuccessful();

        $this->assertSame(['Echo' => 77], $this->fingerprintIds());
    }

    public function test_when_nobody_can_answer_the_prompt_apply_does_nothing(): void
    {
        $this->member('Alpha', 'Bravo');
        $path = $this->csv([[1, 'Alpha Bravo']]);

        // The real command with a non-interactive console (a scheduled run, a pipe): the question's default is "no".
        $code = Artisan::call('biometric:match-users', ['csv' => $path, '--apply' => true, '--no-interaction' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Cancelled. Nothing was changed.', Artisan::output());
        $this->assertSame(['Alpha' => null], $this->fingerprintIds());   // no yes, no change
    }

    public function test_apply_is_all_or_nothing(): void
    {
        $this->member('Alpha', 'Bravo');
        $this->member('Charlie', 'Delta');
        $rows = app(UserMatcher::class)->match([['user_id' => '1', 'name' => 'Alpha Bravo'], ['user_id' => '2', 'name' => 'Charlie Delta']], Member::all());
        $updates = 0;
        Member::updating(function () use (&$updates) {
            if (++$updates === 2) {
                throw new \RuntimeException('the second write fails');
            }
        });

        try {
            app(UserMatcher::class)->apply($rows);
            $this->fail('the failure should have propagated');
        } catch (\RuntimeException $e) {
            $this->assertSame('the second write fails', $e->getMessage());
        }

        $this->assertSame(['Alpha' => null, 'Charlie' => null], $this->fingerprintIds());   // the first write was rolled back
    }

    public function test_apply_checks_each_row_again_and_skips_what_changed_since(): void
    {
        $taken = $this->member('Alpha', 'Bravo');
        $gotOne = $this->member('Charlie', 'Delta');
        $fine = $this->member('Echo', 'Foxtrot');
        $matcher = app(UserMatcher::class);
        $rows = $matcher->match([['user_id' => '1', 'name' => 'Alpha Bravo'], ['user_id' => '2', 'name' => 'Charlie Delta'], ['user_id' => '3', 'name' => 'Echo Foxtrot']], Member::all());
        $this->assertSame(['confident', 'confident', 'confident'], array_column($rows, 'status'));

        $this->member('Late', 'Arrival', null, 1);        // someone else gets ID 1 after the check
        $gotOne->update(['fingerprint_id' => 40]);        // this member got an ID after the check

        $this->assertSame(['set' => 1, 'skipped' => 2], $matcher->apply($rows));
        $this->assertSame([null, 40, 3], [$taken->fresh()->fingerprint_id, $gotOne->fresh()->fingerprint_id, $fine->fresh()->fingerprint_id]);
    }

    public function test_only_confident_rows_can_be_applied_by_the_service(): void
    {
        $this->member('Alpha', 'Charlie', 'Bravo');
        $rows = app(UserMatcher::class)->match([['user_id' => '1', 'name' => 'Alpha Charlie']], Member::all());
        $this->assertSame('suggested', $rows[0]['status']);

        $this->assertSame(['set' => 0, 'skipped' => 0], app(UserMatcher::class)->apply($rows));
        $this->assertSame(['Alpha' => null], $this->fingerprintIds());
    }

    // ── the CSV and --out ─────────────────────────────────────────────

    public function test_a_csv_with_a_byte_order_mark_windows_line_endings_and_a_windows_1252_name_works(): void
    {
        $this->member('Zoë', 'Alpha');
        $path = tempnam(sys_get_temp_dir(), 'umt');
        $this->tempFiles[] = $path;
        file_put_contents($path, "\xEF\xBB\xBF\"user_id\",\"name\"\r\n5,\"Zo\xEB Alpha\"\r\n\r\n6,\"Nobody Known\"\r\n");   // ë as one Windows-1252 byte

        $this->artisan('biometric:match-users', ['csv' => $path])->assertSuccessful()->expectsOutputToContain('Device users in the CSV: 2');

        $this->assertSame('confident', $this->byName()['Zoë Alpha']['status']);
    }

    public function test_a_csv_without_the_needed_columns_is_refused_and_only_column_names_are_shown(): void
    {
        $path = $this->csv([[1, 'Secret Person']], 'id,fullname');

        $this->artisan('biometric:match-users', ['csv' => $path])->assertFailed()->expectsOutputToContain('The CSV needs the columns user_id and name (found: id, fullname).');

        $this->assertSame([], Storage::disk('local')->files('biometric-match'));
    }

    public function test_a_missing_or_empty_csv_is_refused(): void
    {
        $this->artisan('biometric:match-users', ['csv' => 'C:\\no\\such\\users.csv'])->assertFailed()->expectsOutputToContain('The CSV file could not be read');

        $empty = tempnam(sys_get_temp_dir(), 'umt');
        $this->tempFiles[] = $empty;
        $this->artisan('biometric:match-users', ['csv' => $empty])->assertFailed()->expectsOutputToContain('The CSV file is empty.');
    }

    public function test_out_names_the_review_file(): void
    {
        $this->member('Alpha', 'Bravo');
        $path = $this->csv([[1, 'Alpha Bravo']]);

        $this->artisan('biometric:match-users', ['csv' => $path, '--out' => 'my-review'])->assertSuccessful();
        $this->artisan('biometric:match-users', ['csv' => $path, '--out' => 'sub/second.csv'])->assertSuccessful();

        $this->assertEqualsCanonicalizing(['biometric-match/my-review.csv', 'biometric-match/sub/second.csv'], Storage::disk('local')->allFiles('biometric-match'));
    }

    public function test_out_may_be_an_absolute_path_outside_the_project_but_not_inside_it(): void
    {
        $this->member('Alpha', 'Bravo');
        $path = $this->csv([[1, 'Alpha Bravo']]);

        $outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'umt-review-' . uniqid() . '.csv';
        $this->tempFiles[] = $outside;
        $this->artisan('biometric:match-users', ['csv' => $path, '--out' => $outside])->assertSuccessful();
        $this->assertFileExists($outside);
        $this->assertStringContainsString('confident', file_get_contents($outside));

        $inside = base_path('tests') . DIRECTORY_SEPARATOR . 'must-not-exist.csv';
        $this->artisan('biometric:match-users', ['csv' => $path, '--out' => $inside])->assertFailed()->expectsOutputToContain('inside the project folder');
        $this->assertFileDoesNotExist($inside);

        $this->artisan('biometric:match-users', ['csv' => $path, '--out' => '../escape.csv'])->assertFailed()->expectsOutputToContain('must not contain ".."');
        $this->artisan('biometric:match-users', ['csv' => $path, '--out' => 'C:\\no\\such\\folder\\x.csv'])->assertFailed()->expectsOutputToContain('does not exist');
    }

    public function test_cells_that_look_like_formulas_are_made_plain_text(): void
    {
        $path = $this->csv([[1, '=HYPERLINK("http://example.org")'], [2, '+Alpha Bravo'], [3, '@Charlie Delta']]);

        $this->artisan('biometric:match-users', ['csv' => $path])->assertSuccessful();

        $names = array_column($this->review(), 'device_name');
        $this->assertSame(["'=HYPERLINK(\"http://example.org\")", "'+Alpha Bravo", "'@Charlie Delta"], $names);
    }

    public function test_no_csv_is_ever_committed(): void
    {
        $ignore = file_get_contents(base_path('.gitignore'));

        foreach (['users.csv', '*.csv', '/storage/app/private/biometric-match/'] as $line) {
            $this->assertMatchesRegularExpression('/^' . preg_quote($line, '/') . '$/m', $ignore, $line);
        }
    }
}
