<?php

namespace Tests\Feature\Api;

use App\Models\Devotion;
use App\Models\DevotionTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\Feature\Devotions\Concerns\DevotionHelpers;
use Tests\TestCase;

/** GET /api/v1/devotions/today, /api/v1/devotions and /api/v1/devotions/{date}. Today is Monday 2026-11-09. */
class DevotionApiTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers, DevotionHelpers;

    /** Every key of one devotion, in order. Nothing else may appear. */
    private const KEYS = [
        'date', 'title', 'scripture_reference', 'verse_text', 'body', 'prayer', 'thought_for_the_day',
        'theme', 'author_name', 'lang_requested', 'lang', 'verse_lang', 'available_langs',
    ];

    private const KJV = 'But they that wait upon the LORD shall renew their strength; they shall mount up with wings as eagles.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->signInAsMember();
    }

    private function live(string $date, array $attributes = []): Devotion
    {
        return $this->devotion($attributes + ['publish_date' => $date, 'status' => Devotion::STATUS_APPROVED]);
    }

    /** An approved translation that matches the devotion's current English. */
    private function freshTranslation(Devotion $devotion, string $locale, array $attributes = []): DevotionTranslation
    {
        return $this->translation($devotion, $locale, $attributes + [
            'title'               => "Title ({$locale})",
            'body'                => "Body ({$locale})",
            'prayer'              => "Prayer ({$locale})",
            'thought_for_the_day' => "Thought ({$locale})",
            'status'              => DevotionTranslation::STATUS_APPROVED,
            'source_hash'         => $devotion->sourceHash(),
        ]);
    }

    /** @return list<string> */
    private function dates(TestResponse $response): array
    {
        return array_column($response->json('data'), 'date');
    }

    // ── access ─────────────────────────────────────────────────────────

    public function test_it_needs_a_token(): void
    {
        $this->getJson('/api/v1/devotions/today')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_a_deactivated_login_is_refused(): void
    {
        $this->token = User::factory()->create(['is_active' => false])->createAppToken('Phone')->plainTextToken;

        $this->api('GET', '/devotions/today')->assertForbidden()->assertJsonPath('code', 'account_inactive');
    }

    public function test_members_and_staff_without_a_member_record_can_read_devotions(): void
    {
        $this->live('2026-11-09');

        $this->api('GET', '/devotions/today')->assertOk();   // a member

        $this->token = User::factory()->create(['member_id' => null])->createAppToken('Office PC')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->api('GET', '/devotions/today')->assertOk();   // staff, no member record
        $this->api('GET', '/devotions')->assertOk();
        $this->api('GET', '/devotions/2026-11-09')->assertOk();
    }

    // ── today ──────────────────────────────────────────────────────────

    public function test_todays_devotion_has_exactly_these_fields(): void
    {
        $theme = $this->theme('2026-11-01', '2026-11-30', 'November: Faith');
        $this->live('2026-11-09', [
            'title'               => 'Renewed Strength',
            'scripture_reference' => 'Isaiah 40:31',
            'verse_text'          => self::KJV,
            'body'                => "Paragraph one.\n\nParagraph two.",
            'prayer'              => 'Lord, renew my strength.',
            'thought_for_the_day' => 'Wait on the Lord today.',
            'theme_id'            => $theme->id,
            'author_name'         => 'Rev. Mensah',
            'source'              => 'ai',
            'ai_edited'           => true,
            'approved_by'         => User::factory()->create()->id,
            'approved_at'         => now(),
        ]);

        $this->api('GET', '/devotions/today')->assertOk()->assertExactJson(['data' => [
            'date'                => '2026-11-09',
            'title'               => 'Renewed Strength',
            'scripture_reference' => 'Isaiah 40:31',
            'verse_text'          => self::KJV,
            'body'                => "Paragraph one.\n\nParagraph two.",
            'prayer'              => 'Lord, renew my strength.',
            'thought_for_the_day' => 'Wait on the Lord today.',
            'theme'               => 'November: Faith',
            'author_name'         => 'Rev. Mensah',
            'lang_requested'      => 'en',
            'lang'                => 'en',
            'verse_lang'          => 'en',
            'available_langs'     => ['en'],
        ]]);
    }

    public function test_no_live_devotion_today_is_a_404(): void
    {
        $this->live('2026-11-10');                        // tomorrow
        $this->devotion(['publish_date' => '2026-11-09']); // today, but a draft

        $this->api('GET', '/devotions/today')
            ->assertNotFound()
            ->assertExactJson(['message' => 'There is no devotion for today yet.', 'code' => 'no_devotion_today']);
    }

    public function test_a_devotion_without_a_verse_yet_has_no_verse_language(): void
    {
        $this->live('2026-11-09');

        $this->api('GET', '/devotions/today')->assertOk()->assertJsonPath('data.verse_text', null)->assertJsonPath('data.verse_lang', null);
    }

    // ── by date ────────────────────────────────────────────────────────

    public function test_a_past_live_devotion_by_date(): void
    {
        $this->live('2026-11-01', ['title' => 'All Saints']);

        $this->api('GET', '/devotions/2026-11-01')->assertOk()->assertJsonPath('data.date', '2026-11-01')->assertJsonPath('data.title', 'All Saints');
    }

    public function test_future_draft_deleted_missing_and_impossible_dates_all_get_the_same_404(): void
    {
        $this->live('2026-11-20');                                     // future, approved
        $this->devotion(['publish_date' => '2026-11-05']);             // draft
        $this->live('2026-11-04')->delete();                           // deleted
        // 2026-11-03: nothing

        $bodies = collect(['2026-11-20', '2026-11-05', '2026-11-04', '2026-11-03', '2026-02-30', '2026-13-01', '2026-11-9'])
            ->map(fn (string $date) => $this->api('GET', "/devotions/{$date}")->assertNotFound()->getContent())
            ->unique();

        $this->assertCount(1, $bodies, 'every 404 must look the same');
        $this->assertSame(['message' => 'Not found.', 'code' => 'not_found'], json_decode($bodies->first(), true));
    }

    // ── archive ────────────────────────────────────────────────────────

    public function test_the_archive_lists_live_devotions_newest_first(): void
    {
        $this->live('2026-11-01');
        $this->live('2026-11-09');
        $this->live('2026-11-08');
        $this->live('2026-11-10');                          // future
        $this->devotion(['publish_date' => '2026-11-07']);  // draft
        $this->live('2026-11-06')->delete();                // deleted

        $response = $this->api('GET', '/devotions')->assertOk();

        $this->assertSame(['2026-11-09', '2026-11-08', '2026-11-01'], $this->dates($response));
        $response->assertJsonPath('meta.next_before', null);
        $this->assertSame(self::KEYS, array_keys($response->json('data.0')));
    }

    public function test_before_is_exclusive(): void
    {
        foreach (['2026-11-09', '2026-11-08', '2026-11-07'] as $date) {
            $this->live($date);
        }

        $this->assertSame(['2026-11-07'], $this->dates($this->api('GET', '/devotions?before=2026-11-08')));
        $this->assertSame(['2026-11-09', '2026-11-08', '2026-11-07'], $this->dates($this->api('GET', '/devotions?before=2026-12-31')));   // never beyond today
    }

    public function test_pages_follow_next_before(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->live(today()->subDays($i)->toDateString());   // 2026-11-09 back to 2026-10-29
        }

        $first = $this->api('GET', '/devotions')->assertOk();
        $this->assertCount(10, $first->json('data'));
        $this->assertSame('2026-11-09', $first->json('data.0.date'));
        $first->assertJsonPath('meta.next_before', '2026-10-31');

        $second = $this->api('GET', '/devotions?before=2026-10-31')->assertOk();
        $this->assertSame(['2026-10-30', '2026-10-29'], $this->dates($second));
        $second->assertJsonPath('meta.next_before', null);
    }

    public function test_a_page_that_ends_exactly_at_the_last_devotion_has_no_next_page(): void
    {
        foreach (['2026-11-09', '2026-11-08', '2026-11-07'] as $date) {
            $this->live($date);
        }

        $this->api('GET', '/devotions?limit=3')->assertJsonCount(3, 'data')->assertJsonPath('meta.next_before', null);
        $this->api('GET', '/devotions?limit=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.next_before', '2026-11-08');
    }

    public function test_an_empty_archive(): void
    {
        $this->api('GET', '/devotions')->assertOk()->assertExactJson(['data' => [], 'meta' => ['next_before' => null]]);
    }

    public static function badArchiveQueries(): array
    {
        return [
            'limit 0'          => ['limit=0', 'limit'],
            'limit 31'         => ['limit=31', 'limit'],
            'limit text'       => ['limit=ten', 'limit'],
            'before not a date' => ['before=2026-13-01', 'before'],
            'before wrong form' => ['before=09-11-2026', 'before'],
            'unknown language' => ['lang=fr', 'lang'],
        ];
    }

    #[DataProvider('badArchiveQueries')]
    public function test_bad_archive_queries_are_422(string $query, string $field): void
    {
        $this->api('GET', "/devotions?{$query}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrorFor($field);
    }

    public function test_up_to_30_per_page(): void
    {
        for ($i = 0; $i < 31; $i++) {
            $this->live(today()->subDays($i)->toDateString());
        }

        $this->api('GET', '/devotions?limit=30')->assertJsonCount(30, 'data')->assertJsonPath('meta.next_before', '2026-10-11');
    }

    // ── languages ──────────────────────────────────────────────────────

    public static function badLanguages(): array
    {
        return ['French' => ['fr'], 'upper case' => ['TW'], 'Akan code' => ['ak']];
    }

    #[DataProvider('badLanguages')]
    public function test_an_unknown_language_is_422(string $lang): void
    {
        $this->live('2026-11-09');

        $this->api('GET', "/devotions/today?lang={$lang}")->assertStatus(422)->assertJsonValidationErrorFor('lang');
        $this->api('GET', "/devotions/2026-11-09?lang={$lang}")->assertStatus(422)->assertJsonValidationErrorFor('lang');
    }

    public function test_without_a_translation_the_devotion_comes_back_in_english(): void
    {
        $this->live('2026-11-09', ['title' => 'Renewed Strength']);

        $this->api('GET', '/devotions/today?lang=tw')
            ->assertOk()
            ->assertJsonPath('data.title', 'Renewed Strength')
            ->assertJsonPath('data.lang_requested', 'tw')
            ->assertJsonPath('data.lang', 'en')
            ->assertJsonPath('data.available_langs', ['en']);
    }

    public function test_a_draft_translation_is_not_served(): void
    {
        $devotion = $this->live('2026-11-09', ['title' => 'Renewed Strength']);
        $this->freshTranslation($devotion, 'tw', ['status' => DevotionTranslation::STATUS_DRAFT]);

        $this->api('GET', '/devotions/today?lang=tw')->assertJsonPath('data.title', 'Renewed Strength')->assertJsonPath('data.lang', 'en');
    }

    public function test_an_approved_up_to_date_translation_is_served(): void
    {
        $devotion = $this->live('2026-11-09', ['verse_text' => self::KJV, 'scripture_reference' => 'Isaiah 40:31']);
        $this->freshTranslation($devotion, 'tw', ['verse_text' => 'Twi verse text']);

        $this->api('GET', '/devotions/today?lang=tw')->assertOk()->assertJson(['data' => [
            'title'               => 'Title (tw)',
            'body'                => 'Body (tw)',
            'prayer'              => 'Prayer (tw)',
            'thought_for_the_day' => 'Thought (tw)',
            'verse_text'          => 'Twi verse text',
            'scripture_reference' => 'Isaiah 40:31',   // the reference itself is not translated
            'lang_requested'      => 'tw',
            'lang'                => 'tw',
            'verse_lang'          => 'tw',
            'available_langs'     => ['en', 'tw'],
        ]]);
    }

    public function test_a_translation_made_before_the_english_changed_is_not_served(): void
    {
        $devotion = $this->live('2026-11-09', ['body' => 'Original body.']);
        $this->freshTranslation($devotion, 'tw');
        $devotion->update(['body' => 'Corrected body.']);

        $this->api('GET', '/devotions/today?lang=tw')
            ->assertJsonPath('data.body', 'Corrected body.')
            ->assertJsonPath('data.lang', 'en')
            ->assertJsonPath('data.available_langs', ['en']);
    }

    public function test_the_verse_falls_back_to_the_kjv(): void
    {
        $devotion = $this->live('2026-11-09', ['verse_text' => self::KJV]);
        $this->freshTranslation($devotion, 'tw');   // no verse pasted yet

        $this->api('GET', '/devotions/today?lang=tw')
            ->assertJsonPath('data.lang', 'tw')
            ->assertJsonPath('data.verse_text', self::KJV)
            ->assertJsonPath('data.verse_lang', 'en');
    }

    public function test_available_languages_lists_only_approved_up_to_date_translations(): void
    {
        $devotion = $this->live('2026-11-09');
        $this->freshTranslation($devotion, 'ha', ['source_hash' => str_repeat('0', 64)]);   // stale
        $this->freshTranslation($devotion, 'ee', ['status' => DevotionTranslation::STATUS_DRAFT]);
        $this->freshTranslation($devotion, 'gaa');
        $this->freshTranslation($devotion, 'tw');

        $this->api('GET', '/devotions/today')->assertJsonPath('data.available_langs', ['en', 'tw', 'gaa']);   // config order
    }

    public function test_the_archive_honours_the_language_per_devotion(): void
    {
        $translated = $this->live('2026-11-09');
        $this->freshTranslation($translated, 'ha');
        $this->live('2026-11-08', ['title' => 'English only']);

        $response = $this->api('GET', '/devotions?lang=ha')->assertOk();

        $response->assertJsonPath('data.0.title', 'Title (ha)')->assertJsonPath('data.0.lang', 'ha');
        $response->assertJsonPath('data.1.title', 'English only')->assertJsonPath('data.1.lang', 'en');
    }

    // ── source hash ────────────────────────────────────────────────────

    public function test_the_source_hash_follows_the_english_text_only(): void
    {
        $devotion = $this->live('2026-11-09');
        $hash = $devotion->sourceHash();

        $this->assertSame(64, strlen($hash));
        $this->assertSame($hash, $devotion->fresh()->sourceHash(), 'stable for the same text');

        $devotion->author_name = 'Rev. Owusu';
        $devotion->theme_id = $this->theme('2026-11-01', '2026-11-30')->id;
        $this->assertSame($hash, $devotion->sourceHash(), 'author and theme are not part of the text');

        foreach (Devotion::SOURCE_FIELDS as $field) {
            $changed = $devotion->fresh();
            $changed->{$field} = $changed->{$field} . ' (edited)';
            $this->assertNotSame($hash, $changed->sourceHash(), "{$field} must change the hash");
        }
    }

    // ── nothing internal leaks ─────────────────────────────────────────

    public function test_internal_fields_never_appear(): void
    {
        $devotion = $this->live('2026-11-09', ['source' => 'ai', 'ai_edited' => true, 'no_theme' => true, 'approved_by' => User::factory()->create()->id]);
        $this->freshTranslation($devotion, 'tw', ['reviewed_by' => User::factory()->create()->id, 'reviewed_at' => now()]);

        foreach (['/devotions/today?lang=tw', '/devotions/2026-11-09', '/devotions?lang=tw'] as $uri) {
            $response = $this->api('GET', $uri)->assertOk();
            $item = $response->json('data.0') ?? $response->json('data');

            $this->assertSame(self::KEYS, array_keys($item), $uri);
            foreach (['"id"', 'status', 'source', 'ai_edited', 'no_theme', 'approved', 'created_', 'updated_', 'reviewed'] as $hidden) {
                $this->assertStringNotContainsString($hidden, $response->getContent(), "{$uri} leaks {$hidden}");
            }
        }
    }
}
