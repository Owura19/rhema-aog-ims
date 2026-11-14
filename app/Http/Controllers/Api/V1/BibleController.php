<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BibleVerse;
use App\Services\Bible\BibleText;
use App\Support\Api\ApiError;
use App\Support\Bible\KjvCanon;
use Illuminate\Http\JsonResponse;

/**
 * The KJV reader (any active login; no member record needed).
 *   GET /bible/books                 -> {data:[{id, name, abbreviation, testament, chapters}]}
 *   GET /bible/{book}/{chapter}      -> {data:{book:{id,name}, chapter, verses:[{verse,text}], previous, next}}
 * book is the canonical id 1-66 (Genesis 1 ... Revelation 66); previous/next cross book
 * boundaries and are null at Genesis 1 and Revelation 22. A missing book or chapter is 404;
 * before `bible:import-kjv` has run, both endpoints answer 503 bible_not_imported.
 */
class BibleController extends Controller
{
    private const CACHE = ['Cache-Control' => 'private, max-age=86400'];

    public function books(): JsonResponse
    {
        if ($missing = $this->notImported()) {
            return $missing;
        }

        $books = array_map(fn (array $book, int $index) => [
            'id'           => $index + 1,
            'name'         => $book['name'],
            'abbreviation' => $book['code'],
            'testament'    => $book['testament'],
            'chapters'     => $book['chapters'],
        ], KjvCanon::BOOKS, array_keys(KjvCanon::BOOKS));

        return response()->json(['data' => $books], 200, self::CACHE);
    }

    public function chapter(int $book, int $chapter): JsonResponse
    {
        if ($missing = $this->notImported()) {
            return $missing;
        }

        $canon = KjvCanon::BOOKS[$book - 1] ?? null;
        abort_if(! $canon || $chapter < 1 || $chapter > $canon['chapters'], 404);

        $verses = BibleVerse::query()
            ->where('book_id', $book)
            ->where('chapter', $chapter)
            ->orderBy('verse')
            ->get(['verse', 'text']);
        abort_if($verses->isEmpty(), 404);

        return response()->json(['data' => [
            'book'     => ['id' => $book, 'name' => $canon['name']],
            'chapter'  => $chapter,
            'verses'   => $verses->map(fn (BibleVerse $v) => ['verse' => (int) $v->verse, 'text' => $v->text])->values(),
            'previous' => $this->previous($book, $chapter),
            'next'     => $this->next($book, $chapter),
        ]], 200, self::CACHE);
    }

    /** @return array{book: int, chapter: int}|null */
    private function previous(int $book, int $chapter): ?array
    {
        if ($chapter > 1) {
            return ['book' => $book, 'chapter' => $chapter - 1];
        }

        return $book > 1 ? ['book' => $book - 1, 'chapter' => KjvCanon::BOOKS[$book - 2]['chapters']] : null;
    }

    /** @return array{book: int, chapter: int}|null */
    private function next(int $book, int $chapter): ?array
    {
        if ($chapter < KjvCanon::BOOKS[$book - 1]['chapters']) {
            return ['book' => $book, 'chapter' => $chapter + 1];
        }

        return $book < count(KjvCanon::BOOKS) ? ['book' => $book + 1, 'chapter' => 1] : null;
    }

    private function notImported(): ?JsonResponse
    {
        return BibleText::isImported()
            ? null
            : ApiError::response('bible_not_imported', "The Bible isn't available yet. Please try again later.", 503);
    }
}
