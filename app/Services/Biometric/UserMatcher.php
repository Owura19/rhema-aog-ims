<?php

namespace App\Services\Biometric;

use App\Models\Member;
use App\Rules\UniqueFingerprintId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Matches the fingerprint device's users (the users.csv exported by the bridge) to members, so
 * members.fingerprint_id can be filled in bulk.
 *
 * Names are compared as sets of words: lowercase, punctuation removed (an apostrophe joins: O'Brien is
 * obrien), titles (Mr, Mrs, Miss, Rev, Pastor, Deacon, Elder, Dr, Apostle, Evangelist, Bro, Sis) dropped,
 * word order ignored. A member's name is first + other + last name.
 *
 * Statuses (only `confident` is ever applied):
 *   confident    exactly one member has exactly this name, has no fingerprint ID yet, and no other member
 *                holds this device user ID
 *   already_set  that member already has this same ID
 *   conflict     that member has a different ID; or another member already holds this device ID; or the
 *                CSV lists this device ID twice; or two device users match the same member
 *   ambiguous    more than one member has exactly this name
 *   suggested    no exact match, but exactly one member is close: every word of the shorter name appears
 *                in the longer one, or the words are very similar (a one-letter spelling difference)
 *   no_match     nothing found (or the row cannot be used: bad ID, empty name, several close members)
 */
final class UserMatcher
{
    public const CONFIDENT = 'confident';
    public const ALREADY_SET = 'already_set';
    public const CONFLICT = 'conflict';
    public const AMBIGUOUS = 'ambiguous';
    public const SUGGESTED = 'suggested';
    public const NO_MATCH = 'no_match';

    public const STATUSES = [self::CONFIDENT, self::ALREADY_SET, self::CONFLICT, self::AMBIGUOUS, self::SUGGESTED, self::NO_MATCH];

    public const TITLES = ['mr', 'mrs', 'miss', 'rev', 'pastor', 'deacon', 'elder', 'dr', 'apostle', 'evangelist', 'bro', 'sis'];

    /** members.fingerprint_id is a signed 32-bit integer, and 0 is not a user number. */
    public const MAX_ID = 2147483647;

    private const SIMILARITY = 0.8;   // share of words that must be (nearly) the same for a "very similar" name

    /** @return list<string> the name's words: lowercase, no punctuation or titles, unique, sorted. */
    public static function tokens(?string $name): array
    {
        $name = mb_strtolower((string) $name);
        $name = preg_replace("/['’‘`´]+/u", '', $name);          // O'Brien -> obrien
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);    // every other mark becomes a space
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique(array_filter($words, fn ($w) => ! in_array($w, self::TITLES, true))));
        sort($words);

        return $words;
    }

    /** The device user number as an int, or null if it is not a whole number the members table can hold. */
    public static function parseId(?string $raw): ?int
    {
        $raw = trim((string) $raw);
        if (! preg_match('/^[0-9]{1,10}$/', $raw)) {
            return null;
        }
        $id = (int) $raw;

        return $id >= 1 && $id <= self::MAX_ID ? $id : null;
    }

    /**
     * Reads users.csv (columns user_id and name; a byte-order mark, CRLF lines and a Windows-1252 file are fine).
     *
     * @return array{users: list<array{user_id: string, name: string}>, error: ?string}
     */
    public static function readCsv(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ['users' => [], 'error' => 'The CSV file could not be read: ' . $path];
        }

        $handle = fopen($path, 'r');
        $first = fgets($handle);
        if ($first === false || trim($first) === '') {
            fclose($handle);

            return ['users' => [], 'error' => 'The CSV file is empty.'];
        }

        // The header line is parsed on its own so a byte-order mark (Excel, Notepad) can't confuse the quotes.
        $columns = array_map(fn ($c) => mb_strtolower(trim((string) $c)), str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', $first), ',', '"', ''));
        $idColumn = array_search('user_id', $columns, true);
        $nameColumn = array_search('name', $columns, true);
        if ($idColumn === false || $nameColumn === false) {
            fclose($handle);

            return ['users' => [], 'error' => 'The CSV needs the columns user_id and name (found: ' . implode(', ', array_filter($columns, 'strlen')) . ').'];
        }

        $users = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($row === [null]) {
                continue;   // blank line
            }
            $id = trim((string) ($row[$idColumn] ?? ''));
            $name = trim((string) ($row[$nameColumn] ?? ''));
            if (! mb_check_encoding($name, 'UTF-8')) {
                $name = mb_convert_encoding($name, 'UTF-8', 'Windows-1252');
            }
            if ($id === '' && $name === '') {
                continue;
            }
            $users[] = ['user_id' => $id, 'name' => $name];
        }
        fclose($handle);

        return ['users' => $users, 'error' => null];
    }

    /**
     * @param  list<array{user_id: string, name: string}>  $users
     * @param  iterable<Member>  $members  the members to match against (soft-deleted ones are not loaded)
     * @return list<array{device_user_id: string, device_name: string, status: string, member_id: string, member_name: string, note: string, member_pk: ?int, device_id: ?int}>
     */
    public function match(array $users, iterable $members): array
    {
        $people = $byName = $byWord = $holders = [];
        foreach ($members as $member) {
            $words = self::tokens($member->full_name);
            $people[$member->id] = ['member' => $member, 'words' => $words];
            if ($words) {
                $byName[implode(' ', $words)][] = $member->id;
                foreach ($words as $word) {
                    $byWord[$word][] = $member->id;
                }
            }
            if ($member->fingerprint_id !== null) {
                $holders[(int) $member->fingerprint_id][] = $member->id;
            }
        }

        $idUses = [];
        foreach ($users as $user) {
            if (($id = self::parseId($user['user_id'])) !== null) {
                $idUses[$id] = ($idUses[$id] ?? 0) + 1;
            }
        }

        $rows = $claims = [];
        foreach ($users as $index => $user) {
            $rows[$index] = $this->classify($user, $people, $byName, $byWord, $holders, $idUses);
            if ($rows[$index]['status'] === self::CONFIDENT) {
                $claims[$rows[$index]['member_pk']][] = $index;
            }
        }

        // Two device users that both match one member: neither is safe to apply.
        foreach ($claims as $indexes) {
            if (count($indexes) > 1) {
                foreach ($indexes as $index) {
                    $rows[$index]['status'] = self::CONFLICT;
                    $rows[$index]['note'] = 'another device user matches this same member';
                }
            }
        }

        return array_values($rows);
    }

    /**
     * Sets the fingerprint ID for the `confident` rows, all in one transaction. Each row is checked again
     * just before it is written (the member still has no ID, and the UniqueFingerprintId rule passes);
     * a row that no longer qualifies is skipped, never forced.
     *
     * @param  list<array<string, mixed>>  $rows  the result of match()
     * @return array{set: int, skipped: int}
     */
    public function apply(array $rows): array
    {
        $set = $skipped = 0;

        DB::transaction(function () use ($rows, &$set, &$skipped) {
            foreach ($rows as $row) {
                if ($row['status'] !== self::CONFIDENT) {
                    continue;
                }

                $member = Member::whereKey($row['member_pk'])->lockForUpdate()->first();
                $allowed = $member
                    && $member->fingerprint_id === null
                    && Validator::make(['fingerprint_id' => $row['device_id']], ['fingerprint_id' => [new UniqueFingerprintId($member->id)]])->passes();

                if (! $allowed) {
                    $skipped++;
                    continue;
                }

                $member->update(['fingerprint_id' => $row['device_id']]);
                $set++;
            }
        });

        return ['set' => $set, 'skipped' => $skipped];
    }

    // ── internals ──────────────────────────────────────────────────────

    private function classify(array $user, array $people, array $byName, array $byWord, array $holders, array $idUses): array
    {
        $row = [
            'device_user_id' => $user['user_id'], 'device_name' => $user['name'], 'status' => self::NO_MATCH,
            'member_id' => '', 'member_name' => '', 'note' => '', 'member_pk' => null, 'device_id' => self::parseId($user['user_id']),
        ];

        $id = $row['device_id'];
        $words = self::tokens($user['name']);

        if ($id === null) {
            return ['note' => 'the device user ID is not a whole number from 1 to ' . self::MAX_ID] + $row;
        }
        if ($words === []) {
            return ['note' => 'the name is empty (or only titles)'] + $row;
        }

        $exact = $byName[implode(' ', $words)] ?? [];

        if (count($exact) > 1) {
            return ['status' => self::AMBIGUOUS, 'note' => count($exact) . ' members have exactly this name'] + $this->withMembers($row, $people, $exact);
        }

        if (count($exact) === 1) {
            $pk = $exact[0];
            $member = $people[$pk]['member'];
            $row = $this->withMembers($row, $people, [$pk]);
            $others = array_values(array_diff($holders[$id] ?? [], [$pk]));

            if (($idUses[$id] ?? 0) > 1) {
                return ['status' => self::CONFLICT, 'note' => 'this device user ID appears ' . $idUses[$id] . ' times in the CSV'] + $row;
            }
            if ($others) {
                return ['status' => self::CONFLICT, 'note' => 'this device user ID is already held by ' . $this->codes($people, $others)] + $row;
            }
            if ($member->fingerprint_id !== null && (int) $member->fingerprint_id === $id) {
                return ['status' => self::ALREADY_SET, 'note' => 'the member already has this ID'] + $row;
            }
            if ($member->fingerprint_id !== null) {
                return ['status' => self::CONFLICT, 'note' => 'the member already has a different fingerprint ID (' . $member->fingerprint_id . ')'] + $row;
            }

            return ['status' => self::CONFIDENT, 'note' => 'exact name match'] + $row;
        }

        return $this->suggest($row, $words, $people, $byWord, $holders, $id);
    }

    private function suggest(array $row, array $words, array $people, array $byWord, array $holders, int $id): array
    {
        $candidates = [];
        foreach ($words as $word) {
            foreach ($byWord[$word] ?? [] as $pk) {
                $candidates[$pk] = true;
            }
        }

        $close = [];
        foreach (array_keys($candidates) as $pk) {
            if ($reason = $this->closeness($words, $people[$pk]['words'])) {
                $close[$pk] = $reason;
            }
        }

        if (count($close) === 1) {
            $pk = array_key_first($close);
            $member = $people[$pk]['member'];
            $note = 'close match: ' . $close[$pk];
            if ($member->fingerprint_id !== null) {
                $note .= '; the member already has fingerprint ID ' . $member->fingerprint_id;
            }
            if ($others = array_diff($holders[$id] ?? [], [$pk])) {
                $note .= '; this device user ID is held by ' . $this->codes($people, array_values($others));
            }

            return ['status' => self::SUGGESTED, 'note' => $note] + $this->withMembers($row, $people, [$pk]);
        }

        return ['note' => count($close) > 1 ? count($close) . ' members are close to this name; none chosen' : 'no member with this name'] + $row;
    }

    /** Why two names are "close" (never the same set), or null. */
    private function closeness(array $a, array $b): ?string
    {
        [$short, $long] = count($a) <= count($b) ? [$a, $b] : [$b, $a];
        if (count($short) < 2) {
            return null;   // one word (a first name alone) fits too many people
        }
        if (! array_diff($short, $long)) {
            return 'every word of the shorter name appears in the longer name';
        }

        $remaining = $long;
        $matched = 0;
        foreach ($short as $word) {
            foreach ($remaining as $key => $other) {
                if ($word === $other || (min(strlen($word), strlen($other)) >= 5 && levenshtein($word, $other) <= 1)) {
                    unset($remaining[$key]);
                    $matched++;
                    break;
                }
            }
        }

        return $matched / max(count($a), count($b)) >= self::SIMILARITY ? 'very similar words (spelling differs slightly)' : null;
    }

    /** @param list<int> $pks */
    private function withMembers(array $row, array $people, array $pks): array
    {
        $row['member_pk'] = count($pks) === 1 ? $pks[0] : null;
        $row['member_id'] = $this->codes($people, $pks);
        $row['member_name'] = implode(' | ', array_map(fn ($pk) => $people[$pk]['member']->full_name, $pks));

        return $row;
    }

    /** @param list<int> $pks */
    private function codes(array $people, array $pks): string
    {
        return implode(' | ', array_map(fn ($pk) => (string) $people[$pk]['member']->member_id, $pks));
    }
}
