<?php

namespace App\Services\Bible;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads a verse-per-line Bible text (eBible.org VPL format: "JOH 3:16 text") into
 * bible_books and bible_verses.
 *
 * parse() checks the WHOLE text before anything is written: every line well formed, the
 * books in canonical order, chapters and verses numbered 1..n with no gaps or repeats, and
 * each book's chapter and verse counts as expected. import() then makes the two tables match
 * the text exactly, in one transaction, so running it again changes nothing. It compares and
 * writes one book at a time, so only one book's existing rows are in memory at once.
 *
 * Cleaning: the pilcrow (a paragraph mark) is removed; the square brackets marking italic
 * (translator-supplied) words are removed but the words are kept. Everything else is kept.
 */
final class KjvImporter
{
    public const CHUNK = 1000;

    /**
     * @param  list<array{code: string, ebible: string, name: string, testament: string, chapters: int, verses: int}>  $canon
     * @return array{books: list<array<string, int|string>>, verses: array<string, array{book_id: int, chapter: int, verse: int, text: string}>}
     *
     * @throws KjvImportException listing everything wrong with the text (nothing is written)
     */
    public function parse(string $text, array $canon): array
    {
        $position = [];                      // eBible code => index in $canon
        foreach ($canon as $i => $book) {
            $position[$book['ebible']] = $i;
        }

        $lines = explode("\n", $text);
        if (end($lines) === '') {
            array_pop($lines);               // the final newline
        }

        $problems = [];
        $verses = [];
        $seen = [];                          // index => ['chapters' => n, 'verses' => n]
        $previous = null;                    // [index, chapter, verse]

        foreach ($lines as $n => $line) {
            $lineNo = $n + 1;

            if (! preg_match('/^([0-9A-Z]{3}) ([1-9][0-9]{0,2}):([1-9][0-9]{0,2}) ([^\x00-\x1F\x7F]+)$/u', $line, $m)) {
                $problems[] = "Line {$lineNo} is not in the form \"BOOK chapter:verse text\".";
                continue;
            }

            [, $code, $chapter, $verse, $raw] = $m;
            $chapter = (int) $chapter;
            $verse = (int) $verse;

            if (! isset($position[$code])) {
                $problems[] = "Line {$lineNo}: unknown book code {$code}.";
                continue;
            }

            $index = $position[$code];
            $current = [$index, $chapter, $verse];

            if (! $this->follows($previous, $current)) {
                $problems[] = "Line {$lineNo}: {$code} {$chapter}:{$verse} " . ($previous
                    ? 'follows ' . $canon[$previous[0]]['ebible'] . " {$previous[1]}:{$previous[2]}."
                    : 'is the first line (expected ' . $canon[0]['ebible'] . ' 1:1).');
            }
            $previous = $current;

            $clean = self::clean($raw);
            if ($clean === '') {
                $problems[] = "Line {$lineNo}: {$code} {$chapter}:{$verse} has no text.";
            }

            $seen[$index]['chapters'] = max($seen[$index]['chapters'] ?? 0, $chapter);
            $seen[$index]['verses'] = ($seen[$index]['verses'] ?? 0) + 1;

            $bookId = $index + 1;
            $verses["{$bookId}:{$chapter}:{$verse}"] = ['book_id' => $bookId, 'chapter' => $chapter, 'verse' => $verse, 'text' => $clean];
        }

        $books = [];
        foreach ($canon as $i => $book) {
            if (! isset($seen[$i])) {
                $problems[] = "{$book['name']} ({$book['ebible']}) is missing.";
            } elseif ($seen[$i]['chapters'] !== $book['chapters'] || $seen[$i]['verses'] !== $book['verses']) {
                $problems[] = "{$book['name']} has {$seen[$i]['chapters']} chapter(s) and {$seen[$i]['verses']} verse(s); expected {$book['chapters']} and {$book['verses']}.";
            }

            $books[] = [
                'id'            => $i + 1,
                'code'          => $book['code'],
                'name'          => $book['name'],
                'testament'     => $book['testament'],
                'chapter_count' => $book['chapters'],
                'verse_count'   => $book['verses'],
            ];
        }

        if ($problems) {
            throw new KjvImportException($problems);
        }

        return ['books' => $books, 'verses' => $verses];
    }

    /**
     * Make bible_books and bible_verses match $parsed exactly (one transaction, one book at a time).
     * With $dryRun, only count what would change.
     *
     * @param  array{books: list<array<string, int|string>>, verses: array<string, array{book_id: int, chapter: int, verse: int, text: string}>}  $parsed
     * @return array{books: int, inserted: int, updated: int, unchanged: int, removed: int}
     *
     * @throws KjvImportException when bible_books holds a different book under one of the ids
     */
    public function import(array $parsed, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($parsed, $dryRun) {
            $currentBooks = DB::table('bible_books')->pluck('code', 'id')->all();
            foreach ($parsed['books'] as $book) {
                $code = $currentBooks[$book['id']] ?? null;
                if ($code !== null && $code !== $book['code']) {
                    throw new KjvImportException(["bible_books id {$book['id']} is {$code}, expected {$book['code']}. Nothing was written."]);
                }
                unset($currentBooks[$book['id']]);
            }
            $extraBookIds = array_keys($currentBooks);

            $counts = ['books' => count($parsed['books']), 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0];

            // Books not in the text: their verses go, then the books.
            if ($extraBookIds) {
                $counts['removed'] += DB::table('bible_verses')->whereIn('book_id', $extraBookIds)->count();
                if (! $dryRun) {
                    DB::table('bible_verses')->whereIn('book_id', $extraBookIds)->delete();
                    DB::table('bible_books')->whereIn('id', $extraBookIds)->delete();
                }
            }

            if (! $dryRun) {
                DB::table('bible_books')->upsert($parsed['books'], ['id'], ['code', 'name', 'testament', 'chapter_count', 'verse_count']);
            }

            $byBook = [];                    // book id => "chapter:verse" => row (the rows are shared, not copied)
            foreach ($parsed['verses'] as $row) {
                $byBook[$row['book_id']]["{$row['chapter']}:{$row['verse']}"] = $row;
            }

            foreach ($parsed['books'] as $book) {
                $this->importBook($book['id'], $byBook[$book['id']] ?? [], $dryRun, $counts);
                unset($byBook[$book['id']]);
            }

            if (! $dryRun) {
                $total = DB::table('bible_verses')->count();
                if ($total !== count($parsed['verses'])) {
                    throw new RuntimeException("bible_verses holds {$total} rows after the import, expected " . count($parsed['verses']) . '. Rolled back.');
                }
            }

            return $counts;
        });
    }

    /** Remove pilcrows and italic brackets (keeping the bracketed words). */
    public static function clean(string $raw): string
    {
        $text = preg_replace('/\x{00B6}/u', '', $raw);
        $text = str_replace(['[', ']'], '', $text);

        return trim(preg_replace('/ {2,}/', ' ', $text));
    }

    /**
     * Compare one book's rows with the text and (unless $dryRun) write the differences.
     *
     * @param  array<string, array{book_id: int, chapter: int, verse: int, text: string}>  $rows  keyed "chapter:verse"
     * @param  array{books: int, inserted: int, updated: int, unchanged: int, removed: int}  $counts
     */
    private function importBook(int $bookId, array $rows, bool $dryRun, array &$counts): void
    {
        $existing = [];                      // "chapter:verse" => [id, text]
        foreach (DB::table('bible_verses')->where('book_id', $bookId)->get(['id', 'chapter', 'verse', 'text']) as $row) {
            $existing["{$row->chapter}:{$row->verse}"] = [$row->id, $row->text];
        }

        $changed = [];
        foreach ($rows as $key => $row) {
            if (! isset($existing[$key])) {
                $counts['inserted']++;
                $changed[] = $row;
            } elseif ($existing[$key][1] !== $row['text']) {
                $counts['updated']++;
                $changed[] = $row;
            } else {
                $counts['unchanged']++;
            }
            unset($existing[$key]);
        }
        $removeIds = array_column($existing, 0);   // this book's verses not in the text
        $counts['removed'] += count($removeIds);

        if ($dryRun) {
            return;
        }

        foreach (array_chunk($removeIds, self::CHUNK) as $ids) {
            DB::table('bible_verses')->whereIn('id', $ids)->delete();
        }
        foreach (array_chunk($changed, self::CHUNK) as $chunk) {
            DB::table('bible_verses')->upsert($chunk, ['book_id', 'chapter', 'verse'], ['text']);
        }
    }

    /** Is $current the verse straight after $previous (or the first book's 1:1 when there is none)? */
    private function follows(?array $previous, array $current): bool
    {
        [$book, $chapter, $verse] = $current;

        if ($previous === null) {
            return $book === 0 && $chapter === 1 && $verse === 1;
        }

        [$prevBook, $prevChapter, $prevVerse] = $previous;

        return ($book === $prevBook && $chapter === $prevChapter && $verse === $prevVerse + 1)
            || ($book === $prevBook && $chapter === $prevChapter + 1 && $verse === 1)
            || ($book === $prevBook + 1 && $chapter === 1 && $verse === 1);
    }
}
