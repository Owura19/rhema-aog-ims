<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Bible\KjvCanon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/bible/books and /api/v1/bible/{book}/{chapter}. */
class BibleApiTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsMember();
    }

    /** A few imported chapters: Genesis 1 (2 verses), Genesis 50:1, Exodus 1:1, Revelation 22:21. */
    private function importFixture(): void
    {
        foreach ([1, 2, 66] as $id) {
            $book = KjvCanon::BOOKS[$id - 1];
            DB::table('bible_books')->insert([
                'id' => $id, 'code' => $book['code'], 'name' => $book['name'], 'testament' => $book['testament'],
                'chapter_count' => $book['chapters'], 'verse_count' => $book['verses'],
            ]);
        }
        DB::table('bible_verses')->insert([
            ['book_id' => 1, 'chapter' => 1, 'verse' => 2, 'text' => 'And the earth was without form, and void;'],
            ['book_id' => 1, 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning God created the heaven and the earth.'],
            ['book_id' => 1, 'chapter' => 50, 'verse' => 1, 'text' => "And Joseph fell upon his father's face,"],
            ['book_id' => 2, 'chapter' => 1, 'verse' => 1, 'text' => 'Now these are the names of the children of Israel,'],
            ['book_id' => 66, 'chapter' => 22, 'verse' => 21, 'text' => 'The grace of our Lord Jesus Christ be with you all. Amen.'],
        ]);
    }

    public function test_it_needs_an_active_login_but_no_member_record(): void
    {
        $this->importFixture();
        $this->getJson('/api/v1/bible/books')->assertUnauthorized();

        $this->token = User::factory()->create(['member_id' => null])->createAppToken('Office PC')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->api('GET', '/bible/books')->assertOk();
        $this->api('GET', '/bible/1/1')->assertOk();

        $this->token = User::factory()->create(['must_change_password' => true])->createAppToken('Phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->api('GET', '/bible/1/1')->assertForbidden()->assertJsonPath('code', 'password_change_required');
    }

    public function test_the_books_list(): void
    {
        $this->importFixture();

        $books = $this->api('GET', '/bible/books')->assertOk()->json('data');

        $this->assertCount(66, $books);
        $this->assertSame(['id' => 1, 'name' => 'Genesis', 'abbreviation' => 'GEN', 'testament' => 'OT', 'chapters' => 50], $books[0]);
        $this->assertSame(['id' => 40, 'name' => 'Matthew', 'abbreviation' => 'MAT', 'testament' => 'NT', 'chapters' => 28], $books[39]);
        $this->assertSame(['id' => 66, 'name' => 'Revelation', 'abbreviation' => 'REV', 'testament' => 'NT', 'chapters' => 22], $books[65]);
        $this->assertSame(39, count(array_filter($books, fn ($b) => $b['testament'] === 'OT')));
        $this->assertSame(KjvCanon::TOTAL_CHAPTERS, array_sum(array_column($books, 'chapters')));
    }

    public function test_a_chapter_with_its_verses_in_order(): void
    {
        $this->importFixture();

        $this->api('GET', '/bible/1/1')->assertOk()->assertExactJson(['data' => [
            'book'     => ['id' => 1, 'name' => 'Genesis'],
            'chapter'  => 1,
            'verses'   => [
                ['verse' => 1, 'text' => 'In the beginning God created the heaven and the earth.'],
                ['verse' => 2, 'text' => 'And the earth was without form, and void;'],
            ],
            'previous' => null,
            'next'     => ['book' => 1, 'chapter' => 2],
        ]]);
    }

    public function test_previous_and_next_cross_book_boundaries(): void
    {
        $this->importFixture();

        $this->api('GET', '/bible/1/50')->assertJsonPath('data.previous', ['book' => 1, 'chapter' => 49])->assertJsonPath('data.next', ['book' => 2, 'chapter' => 1]);
        $this->api('GET', '/bible/2/1')->assertJsonPath('data.previous', ['book' => 1, 'chapter' => 50]);
        $this->api('GET', '/bible/66/22')->assertJsonPath('data.next', null)->assertJsonPath('data.previous', ['book' => 66, 'chapter' => 21]);
    }

    public function test_missing_books_and_chapters_are_404(): void
    {
        $this->importFixture();

        foreach (['/bible/0/1', '/bible/67/1', '/bible/1/0', '/bible/1/51', '/bible/1/2', '/bible/abc/1'] as $uri) {
            $this->api('GET', $uri)->assertNotFound();
        }
        $this->api('GET', '/bible/1/51')->assertExactJson(['message' => 'Not found.', 'code' => 'not_found']);
    }

    public function test_before_the_import_both_endpoints_say_so(): void
    {
        foreach (['/bible/books', '/bible/1/1'] as $uri) {
            $this->api('GET', $uri)->assertStatus(503)->assertExactJson([
                'message' => "The Bible isn't available yet. Please try again later.",
                'code'    => 'bible_not_imported',
            ]);
        }
    }
}
