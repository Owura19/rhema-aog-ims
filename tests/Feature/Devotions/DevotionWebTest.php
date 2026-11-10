<?php

namespace Tests\Feature\Devotions;

use App\Models\Devotion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Devotions\Concerns\DevotionHelpers;
use Tests\TestCase;

/** The staff devotion pages. Today is Monday 2026-11-09. Roles come from the real seeder. */
class DevotionWebTest extends TestCase
{
    use RefreshDatabase, DevotionHelpers;

    private const ROLES = ['Super Admin', 'Pastor', 'Office Staff', 'Devotion Translator', 'Usher'];

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->kjvFixture();   // Isaiah 40:31 and John 3:16-17

        foreach (self::ROLES as $role) {
            $this->users[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    private function words(int $count, string $word = 'grace'): string
    {
        return trim(str_repeat("{$word} ", $count));
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'publish_date'        => '2026-11-25',
            'title'               => 'Renewed Strength',
            'scripture_reference' => 'Isaiah 40:31',
            'theme_id'            => '',
            'author_name'         => 'Rev. Mensah',
            'body'                => $this->words(300),
            'prayer'              => $this->words(50, 'Lord'),
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

    // ── access ─────────────────────────────────────────────────────────

    public static function routes(): array
    {
        $viewers  = ['Super Admin', 'Pastor', 'Office Staff', 'Devotion Translator'];
        $managers = ['Super Admin', 'Pastor', 'Office Staff'];
        $approvers = ['Super Admin', 'Pastor'];

        return [
            'index'    => ['GET', 'devotions.index', null, $viewers],
            'show'     => ['GET', 'devotions.show', 'draft', $viewers],
            'create'   => ['GET', 'devotions.create', null, $managers],
            'store'    => ['POST', 'devotions.store', null, $managers],
            'edit'     => ['GET', 'devotions.edit', 'draft', $managers],
            'update'   => ['PUT', 'devotions.update', 'draft', $managers],
            'destroy'  => ['DELETE', 'devotions.destroy', 'draft', $managers],
            'approve'  => ['POST', 'devotions.approve', 'draft', $approvers],
            'withdraw' => ['POST', 'devotions.withdraw', 'approved', $approvers],
        ];
    }

    #[DataProvider('routes')]
    public function test_who_can_use_each_page(string $method, string $route, ?string $status, array $allowed): void
    {
        foreach (self::ROLES as $i => $role) {
            $devotion = $status ? $this->devotion(['publish_date' => '2026-12-' . (10 + $i), 'status' => $status]) : null;
            $data = match ($route) {
                'devotions.store'  => $this->payload(['publish_date' => '2026-12-2' . $i]),
                'devotions.update' => $this->unchanged($devotion, ['title' => 'Changed by ' . $role]),
                default            => [],
            };

            $response = $this->as($role)->call($method, $devotion ? route($route, $devotion) : route($route), $data);

            if (in_array($role, $allowed, true)) {
                $this->assertContains($response->getStatusCode(), [200, 302], "{$role} should be allowed to use {$route}");
            } else {
                $this->assertSame(403, $response->getStatusCode(), "{$role} should be refused {$route}");
            }
        }
    }

    public function test_the_sidebar_link_shows_only_with_view_devotions(): void
    {
        $this->as('Devotion Translator')->get(route('randyimpact.index'))->assertOk()->assertSee(route('devotions.index'));
        $this->as('Usher')->get(route('randyimpact.index'))->assertOk()->assertDontSee(route('devotions.index'));
    }

    // ── creating and validation ────────────────────────────────────────

    public function test_a_new_devotion_is_a_draft_with_its_creator_recorded(): void
    {
        $response = $this->as('Office Staff')->post(route('devotions.store'), $this->payload());

        $devotion = Devotion::sole();
        $response->assertRedirect(route('devotions.show', $devotion))->assertSessionHas('success', 'Devotion saved as a draft.');
        $this->assertSame(Devotion::STATUS_DRAFT, $devotion->status);
        $this->assertSame(Devotion::SOURCE_MANUAL, $devotion->source);
        $this->assertSame($this->users['Office Staff']->id, $devotion->created_by);
        $this->assertSame($this->users['Office Staff']->id, $devotion->updated_by);
        $this->assertNull($devotion->approved_by);
    }

    public function test_required_fields(): void
    {
        $this->as('Pastor')->post(route('devotions.store'), [])
            ->assertSessionHasErrors(['publish_date', 'title', 'scripture_reference', 'body', 'prayer', 'thought_for_the_day']);

        $this->assertSame(0, Devotion::count());
    }

    public function test_the_thought_is_at_most_140_characters(): void
    {
        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['thought_for_the_day' => str_repeat('a', 141)]))
            ->assertSessionHasErrors('thought_for_the_day');

        $twoByteLetters = str_repeat(mb_chr(0x025B, 'UTF-8'), 140);   // 140 characters, 280 bytes: allowed
        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['thought_for_the_day' => $twoByteLetters]))
            ->assertSessionHasNoErrors();

        $this->assertSame($twoByteLetters, Devotion::sole()->thought_for_the_day);
    }

    public function test_a_second_devotion_on_a_date_is_a_validation_error_not_a_crash(): void
    {
        $this->devotion(['publish_date' => '2026-11-25']);

        $this->as('Pastor')->from(route('devotions.create'))->post(route('devotions.store'), $this->payload(['publish_date' => '2026-11-25']))
            ->assertRedirect(route('devotions.create'))
            ->assertSessionHasErrors(['publish_date' => 'There is already a devotion for this date.']);

        $this->assertSame(1, Devotion::count());
    }

    public function test_moving_a_devotion_onto_a_taken_date_is_a_validation_error(): void
    {
        $this->devotion(['publish_date' => '2026-11-25']);
        $other = $this->devotion(['publish_date' => '2026-11-26']);

        $this->as('Pastor')->put(route('devotions.update', $other), $this->unchanged($other, ['publish_date' => '2026-11-25']))
            ->assertSessionHasErrors(['publish_date' => 'There is already a devotion for this date.']);

        $this->assertSame('2026-11-26', $other->fresh()->publish_date->toDateString());
    }

    public function test_a_deleted_devotions_date_can_be_used_again(): void
    {
        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['publish_date' => '2026-11-25']))->assertSessionHasNoErrors();
        $this->as('Pastor')->delete(route('devotions.destroy', Devotion::sole()))->assertRedirect(route('devotions.index'));

        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['publish_date' => '2026-11-25', 'title' => 'Second try']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Devotion::withTrashed()->count());
        $this->assertSame('Second try', Devotion::sole()->title);
    }

    public function test_automatic_theme_is_the_theme_covering_the_date(): void
    {
        $november = $this->theme('2026-11-01', '2026-11-30', 'November: Faith');

        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['publish_date' => '2026-11-25']));
        $covered = Devotion::whereDate('publish_date', '2026-11-25')->sole();
        $this->assertSame($november->id, $covered->theme_id);
        $this->assertFalse($covered->no_theme);

        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['publish_date' => '2026-12-15']));
        $uncovered = Devotion::whereDate('publish_date', '2026-12-15')->sole();
        $this->assertNull($uncovered->theme_id);
        $this->assertTrue($uncovered->no_theme);

        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['publish_date' => '2026-12-16', 'theme_id' => (string) $november->id]));
        $this->assertSame($november->id, Devotion::whereDate('publish_date', '2026-12-16')->sole()->theme_id);   // an explicit choice is kept
    }

    public function test_the_create_page_suggests_the_first_missing_date_and_its_theme(): void
    {
        $theme = $this->theme('2026-11-01', '2026-11-30');
        $this->devotion(['publish_date' => '2026-11-09']);

        $this->as('Pastor')->get(route('devotions.create'))
            ->assertOk()
            ->assertSee('value="2026-11-10"', false)
            ->assertSee('<option value="' . $theme->id . '" selected>', false);

        $this->as('Pastor')->get(route('devotions.create', ['date' => '2026-11-20']))->assertSee('value="2026-11-20"', false);
    }

    public function test_a_reflection_outside_200_to_450_words_saves_with_a_warning(): void
    {
        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['body' => $this->words(50)]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');
        $this->assertStringContainsString('has 50 words; the usual length is 200–450', session('warning'));
        $this->assertSame(1, Devotion::count());

        $this->get(route('devotions.show', Devotion::sole()))->assertSee('has 50 words', false);   // the layout shows it

        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['publish_date' => '2026-11-26', 'body' => $this->words(300)]))
            ->assertSessionMissing('warning');
    }

    public function test_verse_text_comes_from_the_kjv_never_from_the_form(): void
    {
        $this->as('Pastor')->post(route('devotions.store'), $this->payload(['verse_text' => 'Typed by hand']));

        $devotion = Devotion::sole();
        $this->assertSame(self::KJV_ISAIAH_40_31, $devotion->verse_text);

        $this->as('Pastor')->get(route('devotions.edit', $devotion))
            ->assertOk()
            ->assertSee('But they that wait upon the LORD')
            ->assertDontSee('Typed by hand')
            ->assertDontSee('name="verse_text"', false);
    }

    // ── approving ──────────────────────────────────────────────────────

    public function test_approving_records_who_and_when(): void
    {
        $devotion = $this->devotionWithVerse(['publish_date' => '2026-11-25']);

        $this->as('Pastor')->from(route('devotions.show', $devotion))->post(route('devotions.approve', $devotion))
            ->assertSessionHas('success', 'Devotion approved. It goes live on 25 Nov 2026.');

        $devotion->refresh();
        $this->assertSame(Devotion::STATUS_APPROVED, $devotion->status);
        $this->assertSame($this->users['Pastor']->id, $devotion->approved_by);
        $this->assertTrue($devotion->approved_at->equalTo(now()));
    }

    public function test_an_incomplete_devotion_cannot_be_approved(): void
    {
        $devotion = $this->devotion(['prayer' => '', 'thought_for_the_day' => '   ']);

        $this->as('Pastor')->post(route('devotions.approve', $devotion))
            ->assertSessionHas('error', "This devotion can't be approved yet. Missing: prayer, thought for the day.");

        $this->assertSame(Devotion::STATUS_DRAFT, $devotion->fresh()->status);
    }

    public function test_approving_twice_is_refused(): void
    {
        $devotion = $this->devotion(['status' => 'approved', 'approved_by' => $this->users['Super Admin']->id]);

        $this->as('Pastor')->post(route('devotions.approve', $devotion))->assertSessionHas('error', 'This devotion is already approved.');

        $this->assertSame($this->users['Super Admin']->id, $devotion->fresh()->approved_by);
    }

    public function test_withdrawing_returns_it_to_draft_and_clears_the_approval(): void
    {
        $devotion = $this->devotion(['status' => 'approved', 'approved_by' => $this->users['Pastor']->id, 'approved_at' => now()]);

        $this->as('Pastor')->post(route('devotions.withdraw', $devotion))->assertSessionHas('success');

        $devotion->refresh();
        $this->assertSame(Devotion::STATUS_DRAFT, $devotion->status);
        $this->assertNull($devotion->approved_by);
        $this->assertNull($devotion->approved_at);

        $this->as('Pastor')->post(route('devotions.withdraw', $devotion))->assertSessionHas('error', 'This devotion is not approved.');
    }

    // ── editing ────────────────────────────────────────────────────────

    public function test_an_approver_editing_an_approved_devotion_keeps_it_approved(): void
    {
        $approver = $this->users['Super Admin'];
        $devotion = $this->devotion(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);

        $this->as('Pastor')->put(route('devotions.update', $devotion), $this->unchanged($devotion, ['title' => 'Better title']))
            ->assertSessionHas('success', 'Devotion updated.');

        $devotion->refresh();
        $this->assertSame('Better title', $devotion->title);
        $this->assertSame(Devotion::STATUS_APPROVED, $devotion->status);
        $this->assertSame($approver->id, $devotion->approved_by);
        $this->assertSame($this->users['Pastor']->id, $devotion->updated_by);
    }

    public function test_editing_an_approved_devotion_without_approve_permission_returns_it_to_draft(): void
    {
        $devotion = $this->devotion(['status' => 'approved', 'approved_by' => $this->users['Pastor']->id, 'approved_at' => now()]);

        $this->as('Office Staff')->get(route('devotions.edit', $devotion))->assertSee('Saving changes returns it to draft');

        $this->as('Office Staff')->put(route('devotions.update', $devotion), $this->unchanged($devotion, ['title' => 'Typo fixed']))
            ->assertSessionHas('success', 'Devotion updated and returned to draft: it needs to be approved again.');

        $devotion->refresh();
        $this->assertSame('Typo fixed', $devotion->title);
        $this->assertSame(Devotion::STATUS_DRAFT, $devotion->status);
        $this->assertNull($devotion->approved_by);
        $this->assertNull($devotion->approved_at);
    }

    public function test_saving_an_approved_devotion_with_no_changes_keeps_it_approved(): void
    {
        $devotion = $this->devotionWithVerse(['status' => 'approved', 'approved_by' => $this->users['Pastor']->id, 'approved_at' => now()]);

        $this->as('Office Staff')->put(route('devotions.update', $devotion), $this->unchanged($devotion))
            ->assertSessionHas('success', 'Devotion updated.');

        $this->assertSame(Devotion::STATUS_APPROVED, $devotion->fresh()->status);
    }

    public function test_changing_the_text_of_an_ai_draft_marks_it_edited(): void
    {
        $edited = $this->devotion(['source' => 'ai']);
        $authorOnly = $this->devotion(['publish_date' => '2026-11-21', 'source' => 'ai']);

        $this->as('Pastor')->put(route('devotions.update', $edited), $this->unchanged($edited, ['body' => 'A rewritten reflection.']));
        $this->as('Pastor')->put(route('devotions.update', $authorOnly), $this->unchanged($authorOnly, ['author_name' => 'Rev. Owusu']));

        $this->assertTrue($edited->fresh()->ai_edited);
        $this->assertFalse($authorOnly->fresh()->ai_edited);   // not part of the AI text
        $this->assertSame(Devotion::SOURCE_AI, $edited->fresh()->source);
    }

    // ── deleting ───────────────────────────────────────────────────────

    public function test_who_can_delete_what(): void
    {
        $draft = $this->devotion(['publish_date' => '2026-11-20']);
        $approved = $this->devotion(['publish_date' => '2026-11-21', 'status' => 'approved']);

        $this->as('Office Staff')->delete(route('devotions.destroy', $approved))->assertForbidden();
        $this->assertFalse($approved->fresh()->trashed());

        $this->as('Office Staff')->delete(route('devotions.destroy', $draft))
            ->assertRedirect(route('devotions.index'))
            ->assertSessionHas('success', 'The devotion for 20 Nov 2026 was deleted. That date can be used again.');
        $this->assertTrue(Devotion::withTrashed()->find($draft->id)->trashed());

        $this->as('Pastor')->delete(route('devotions.destroy', $approved))->assertRedirect(route('devotions.index'));
        $this->assertTrue(Devotion::withTrashed()->find($approved->id)->trashed());
    }

    public function test_deleted_devotions_are_not_found(): void
    {
        $devotion = $this->devotion();
        $devotion->delete();

        $this->as('Pastor')->get(route('devotions.show', $devotion))->assertNotFound();
        $this->as('Pastor')->get(route('devotions.edit', $devotion))->assertNotFound();
    }

    // ── the list ───────────────────────────────────────────────────────

    public function test_the_next_14_days_strip(): void
    {
        $this->devotion(['publish_date' => '2026-11-09', 'status' => 'approved']);
        $this->devotion(['publish_date' => '2026-11-10', 'status' => 'approved']);
        $this->devotion(['publish_date' => '2026-11-11']);
        $this->devotion(['publish_date' => '2026-11-23', 'status' => 'approved']);   // day 15: outside

        $response = $this->as('Pastor')->get(route('devotions.index'))->assertOk();

        $states = collect($response->viewData('strip'))->mapWithKeys(fn (array $day) => [$day['date']->toDateString() => $day['state']])->all();
        $this->assertCount(14, $states);
        $this->assertSame(['2026-11-09' => 'live', '2026-11-10' => 'approved', '2026-11-11' => 'draft'], array_slice($states, 0, 3, true));
        $this->assertSame(array_fill(0, 11, 'missing'), array_values(array_slice($states, 3)));
        $this->assertSame('2026-11-22', array_key_last($states));

        $response->assertSee(route('devotions.create', ['date' => '2026-11-12']), false);
        $this->as('Devotion Translator')->get(route('devotions.index'))
            ->assertOk()
            ->assertDontSee(route('devotions.create', ['date' => '2026-11-12']), false);   // view-only: no create links
    }

    public function test_the_list_filters_by_status_and_month(): void
    {
        $draft     = $this->devotion(['publish_date' => '2026-11-05']);
        $live      = $this->devotion(['publish_date' => '2026-11-06', 'status' => 'approved']);
        $scheduled = $this->devotion(['publish_date' => '2026-11-20', 'status' => 'approved']);
        $december  = $this->devotion(['publish_date' => '2026-12-01']);

        $ids = fn (array $query) => $this->as('Pastor')->get(route('devotions.index', $query))->viewData('devotions')->pluck('id')->all();

        $this->assertSame([$december->id, $draft->id], $ids(['status' => 'draft']));
        $this->assertSame([$live->id], $ids(['status' => 'live']));
        $this->assertSame([$scheduled->id], $ids(['status' => 'approved']));
        $this->assertSame([$scheduled->id, $live->id, $draft->id], $ids(['month' => '2026-11']));
        $this->as('Pastor')->get(route('devotions.index', ['status' => 'published']))->assertSessionHasErrors('status');
    }

    public function test_the_list_shows_source_and_theme_flags(): void
    {
        $this->devotion(['source' => 'ai', 'ai_edited' => true, 'no_theme' => true]);

        $this->as('Pastor')->get(route('devotions.index'))
            ->assertSee('<span class="badge badge-info">AI</span>', false)
            ->assertSee('<span class="badge badge-gray">edited</span>', false)
            ->assertSee('<span class="badge badge-danger">no theme</span>', false);
    }

    // ── showing ────────────────────────────────────────────────────────

    public function test_html_in_devotion_text_is_escaped(): void
    {
        $devotion = $this->devotion([
            'title' => '<b>Bold</b> title',
            'body'  => "<script>alert('x')</script>\nSecond line",
        ]);

        $this->as('Pastor')->get(route('devotions.show', $devotion))
            ->assertOk()
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('<br />', false)
            ->assertDontSee('<b>Bold</b>', false);

        $this->as('Pastor')->get(route('devotions.index'))
            ->assertSee('&lt;b&gt;Bold&lt;/b&gt; title', false)
            ->assertDontSee('<b>Bold</b>', false);
    }

    public function test_the_page_shows_who_approved_it_and_its_source(): void
    {
        $devotion = $this->devotion([
            'status'      => 'approved',
            'approved_by' => $this->users['Pastor']->id,
            'approved_at' => '2026-11-08 15:30:00',
            'source'      => 'ai',
            'ai_edited'   => true,
        ]);

        $this->as('Devotion Translator')->get(route('devotions.show', $devotion))
            ->assertOk()
            ->assertSee('Pastor User, 8 Nov 2026, 3:30 PM')
            ->assertSee('AI draft')
            ->assertSee('(edited by staff)')
            ->assertDontSee(route('devotions.edit', $devotion))            // view only: no edit
            ->assertDontSee(route('devotions.withdraw', $devotion));      // nor approve / withdraw
    }
}
