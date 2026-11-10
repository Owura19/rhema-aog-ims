<?php

namespace Tests\Feature\Devotions;

use App\Models\Devotion;
use App\Models\DevotionTranslation;
use App\Models\User;
use App\Services\Bible\BibleText;
use App\Support\Bible\ReferenceParser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Devotions\Concerns\DevotionHelpers;
use Tests\TestCase;

/**
 * The scripture reference on the staff devotion pages: checked, stored in display form with
 * its book/chapter/verses, and the KJV verse filled in. The KJV here is kjvFixture():
 * Isaiah 40:31 and John 3:16-17. Today is Monday 2026-11-09.
 */
class DevotionScriptureWebTest extends TestCase
{
    use RefreshDatabase, DevotionHelpers;

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->kjvFixture();

        foreach (['Pastor', 'Office Staff'] as $role) {
            $this->users[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'publish_date'        => '2026-11-25',
            'title'               => 'Renewed Strength',
            'scripture_reference' => 'Isaiah 40:31',
            'theme_id'            => '',
            'author_name'         => 'Rev. Mensah',
            'body'                => trim(str_repeat('grace ', 300)),
            'prayer'              => trim(str_repeat('Lord ', 50)),
            'thought_for_the_day' => 'Wait on the Lord today.',
        ];
    }

    /** Payload that re-submits a devotion exactly as it is. */
    private function unchanged(Devotion $devotion, array $overrides = []): array
    {
        return $overrides + [
            'publish_date'        => $devotion->publish_date->toDateString(),
            'title'               => $devotion->title,
            'scripture_reference' => $devotion->scripture_reference,
            'theme_id'            => (string) $devotion->theme_id,
            'author_name'         => $devotion->author_name,
            'body'                => $devotion->body,
            'prayer'              => $devotion->prayer,
            'thought_for_the_day' => $devotion->thought_for_the_day,
        ];
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int, 3: ?int} */
    private function columns(Devotion $devotion): array
    {
        return [$devotion->bible_book_id, $devotion->chapter, $devotion->verse_start, $devotion->verse_end];
    }

    // ── saving ─────────────────────────────────────────────────────────

    public function test_saving_stores_the_display_form_its_book_chapter_verses_and_the_kjv_text(): void
    {
        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['scripture_reference' => '  jn 3 : 16 – 17 ']))
            ->assertSessionHasNoErrors();

        $devotion = Devotion::sole();
        $this->assertSame('John 3:16-17', $devotion->scripture_reference);
        $this->assertSame([43, 3, 16, 17], $this->columns($devotion));
        $this->assertSame(self::KJV_JOHN_3_16 . ' ' . self::KJV_JOHN_3_17, $devotion->verse_text);   // one space, no verse numbers
        $this->assertSame('John', $devotion->bibleBook->name);
    }

    public static function invalidReferences(): array
    {
        return [
            'unknown book'       => ['Jhon 3:16', 'We don\'t recognise the book "Jhon".'],
            'chapter too high'   => ['John 22:1', 'John has 21 chapters.'],
            'verse too high'     => ['John 3:37', 'John 3 has 36 verses.'],
            'across chapters'    => ['John 3:16-4:2', ReferenceParser::CROSS_CHAPTER],
            'a list'             => ['John 3:16, 18', ReferenceParser::FORMAT],
            'more than 30'       => ['Psalm 119', 'Choose up to 30 verses.'],
        ];
    }

    #[DataProvider('invalidReferences')]
    public function test_an_invalid_reference_is_a_validation_error_and_nothing_is_saved(string $reference, string $message): void
    {
        $this->as('Pastor')->from(route('devotions.create'))->post(route('devotions.store'), $this->payload(['scripture_reference' => $reference]))
            ->assertRedirect(route('devotions.create'))
            ->assertSessionHasErrors(['scripture_reference' => $message]);

        $this->assertSame(0, Devotion::count());
    }

    public function test_without_the_imported_bible_text_saving_says_so_and_never_book_not_recognised(): void
    {
        DB::table('bible_verses')->delete();

        foreach (['John 3:16', 'Jhon 3:16'] as $reference) {
            $this->as('Pastor')->post(route('devotions.store'), $this->payload(['scripture_reference' => $reference]))
                ->assertSessionHasErrors(['scripture_reference' => BibleText::NOT_IMPORTED]);
        }

        $this->assertSame(0, Devotion::count());
    }

    public function test_a_passage_missing_from_the_imported_text_says_the_import_is_incomplete(): void
    {
        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['scripture_reference' => 'Isaiah 40:30-31']))   // 40:30 is not in the fixture
            ->assertSessionHasErrors(['scripture_reference' => BibleText::NOT_IMPORTED]);

        $this->assertSame(0, Devotion::count());
    }

    public function test_an_unchanged_passage_keeps_its_stored_verse_text_and_an_empty_one_is_filled(): void
    {
        $kept = $this->devotionWithVerse(['verse_text' => 'Stored earlier.']);
        $empty = $this->devotionWithVerse(['publish_date' => '2026-11-21', 'verse_text' => '']);

        $this->as('Pastor')->put(route('devotions.update', $kept), $this->unchanged($kept, ['scripture_reference' => 'Isa 40:31']))
            ->assertSessionHasNoErrors();
        $this->as('Pastor')->put(route('devotions.update', $empty), $this->unchanged($empty))
            ->assertSessionHasNoErrors();

        $this->assertSame('Stored earlier.', $kept->fresh()->verse_text);   // same passage: not looked up again
        $this->assertSame('Isaiah 40:31', $kept->fresh()->scripture_reference);
        $this->assertSame(self::KJV_ISAIAH_40_31, $empty->fresh()->verse_text);
    }

    public function test_an_older_devotion_gets_its_reference_checked_when_it_is_next_saved(): void
    {
        $older = $this->devotion();   // saved before references were checked: no columns, no verse
        $unreadable = $this->devotion(['publish_date' => '2026-11-21', 'scripture_reference' => 'Isaiah forty, verse 31']);

        $this->as('Pastor')->get(route('devotions.show', $older))->assertOk();   // still shown as it is

        $this->as('Pastor')->put(route('devotions.update', $older), $this->unchanged($older))->assertSessionHasNoErrors();
        $this->assertSame([23, 40, 31, 31], $this->columns($older->fresh()));
        $this->assertSame(self::KJV_ISAIAH_40_31, $older->fresh()->verse_text);

        $this->as('Pastor')->put(route('devotions.update', $unreadable), $this->unchanged($unreadable, ['title' => 'New title']))
            ->assertSessionHasErrors(['scripture_reference' => ReferenceParser::FORMAT]);
        $this->assertSame('Strength for Today', $unreadable->fresh()->title);   // nothing saved
    }

    // ── approving ──────────────────────────────────────────────────────

    public function test_approving_needs_a_checked_reference_with_its_verse(): void
    {
        $noVerse = $this->devotion();
        $typedVerseOnly = $this->devotion(['publish_date' => '2026-11-21', 'verse_text' => self::KJV_ISAIAH_40_31]);   // no book/chapter

        foreach ([$noVerse, $typedVerseOnly] as $devotion) {
            $this->as('Pastor')->post(route('devotions.approve', $devotion))
                ->assertSessionHas('error', 'Add a scripture reference before approving.');
            $this->assertSame(Devotion::STATUS_DRAFT, $devotion->fresh()->status);
        }
    }

    public function test_without_the_imported_bible_text_approving_says_so(): void
    {
        $devotion = $this->devotion();
        DB::table('bible_verses')->delete();

        $this->as('Pastor')->post(route('devotions.approve', $devotion))
            ->assertSessionHas('error', BibleText::NOT_IMPORTED);

        $this->assertSame(Devotion::STATUS_DRAFT, $devotion->fresh()->status);
    }

    // ── changing the reference ─────────────────────────────────────────

    public function test_changing_the_reference_refreshes_the_verse_and_makes_translations_out_of_date(): void
    {
        $devotion = $this->devotionWithVerse([
            'publish_date' => '2026-11-09',
            'status'       => Devotion::STATUS_APPROVED,
            'approved_by'  => $this->users['Pastor']->id,
            'approved_at'  => now(),
        ]);
        $this->translation($devotion, 'tw', [
            'title'               => 'Twi title',
            'body'                => 'Twi body',
            'prayer'              => 'Twi prayer',
            'thought_for_the_day' => 'Twi thought',
            'status'              => DevotionTranslation::STATUS_APPROVED,
            'source_hash'         => $devotion->sourceHash(),
        ]);
        $token = User::factory()->create(['member_id' => null])->createAppToken('Test')->plainTextToken;
        $read = function () use ($token) {
            $this->app['auth']->forgetGuards();

            return $this->withToken($token)->getJson('/api/v1/devotions/2026-11-09?lang=tw')->assertOk();
        };

        $read()->assertJsonPath('data.lang', 'tw')->assertJsonPath('data.title', 'Twi title');   // up to date: served
        $hashBefore = $devotion->sourceHash();

        $this->as('Pastor')->put(route('devotions.update', $devotion), $this->unchanged($devotion, ['scripture_reference' => 'John 3:16-17']))
            ->assertSessionHasNoErrors();

        $devotion->refresh();
        $this->assertSame('John 3:16-17', $devotion->scripture_reference);
        $this->assertSame([43, 3, 16, 17], $this->columns($devotion));
        $this->assertSame(self::KJV_JOHN_3_16 . ' ' . self::KJV_JOHN_3_17, $devotion->verse_text);
        $this->assertNotSame($hashBefore, $devotion->sourceHash());

        $read()
            ->assertJsonPath('data.lang', 'en')                       // the Twi translation is out of date
            ->assertJsonPath('data.title', 'Strength for Today')        // so the whole text is English
            ->assertJsonPath('data.scripture_reference', 'John 3:16-17')
            ->assertJsonPath('data.verse_text', self::KJV_JOHN_3_16 . ' ' . self::KJV_JOHN_3_17)
            ->assertJsonPath('data.verse_lang', 'en')
            ->assertJsonPath('data.available_langs', ['en']);
    }

    // ── the form ───────────────────────────────────────────────────────

    public function test_the_form_shows_the_checked_reference_and_its_verse_read_only(): void
    {
        $devotion = $this->devotionWithVerse();

        $this->as('Pastor')->get(route('devotions.edit', $devotion))
            ->assertOk()
            ->assertSee('value="Isaiah 40:31"', false)
            ->assertSee(self::KJV_ISAIAH_40_31)
            ->assertSee('Filled in from the KJV.')
            ->assertDontSee('name="verse_text"', false);

        $this->as('Pastor')->get(route('devotions.create'))
            ->assertOk()
            ->assertSee('The KJV verse fills in when you save')
            ->assertSee('up to 30 verses');
    }
}
