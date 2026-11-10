<?php

namespace Tests\Feature\Devotions;

use App\Models\Devotion;
use App\Models\DevotionTheme;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Devotions\Concerns\DevotionHelpers;
use Tests\TestCase;

/** The devotion theme pages ('manage devotions'). Today is Monday 2026-11-09. */
class DevotionThemeWebTest extends TestCase
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

        foreach (self::ROLES as $role) {
            $this->users[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['title' => 'Walking by Faith', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'notes' => 'Hebrews 11 heroes.'];
    }

    public static function routes(): array
    {
        return [
            'index'   => ['GET', 'devotion-themes.index', false],
            'create'  => ['GET', 'devotion-themes.create', false],
            'store'   => ['POST', 'devotion-themes.store', false],
            'edit'    => ['GET', 'devotion-themes.edit', true],
            'update'  => ['PUT', 'devotion-themes.update', true],
            'destroy' => ['DELETE', 'devotion-themes.destroy', true],
        ];
    }

    #[DataProvider('routes')]
    public function test_only_devotion_managers_can_use_the_theme_pages(string $method, string $route, bool $needsTheme): void
    {
        foreach (self::ROLES as $role) {
            $theme = $needsTheme ? $this->theme('2026-11-01', '2026-11-30') : null;
            $data = in_array($method, ['POST', 'PUT'], true) ? $this->payload() : [];

            $response = $this->as($role)->call($method, $theme ? route($route, $theme) : route($route), $data);

            if (in_array($role, ['Super Admin', 'Pastor', 'Office Staff'], true)) {
                $this->assertContains($response->getStatusCode(), [200, 302], "{$role} should be allowed to use {$route}");
            } else {
                $this->assertSame(403, $response->getStatusCode(), "{$role} should be refused {$route}");
            }
        }
    }

    public function test_a_theme_can_be_created_and_records_its_creator(): void
    {
        $this->as('Pastor')->post(route('devotion-themes.store'), $this->payload())
            ->assertRedirect(route('devotion-themes.index'))
            ->assertSessionHas('success', 'Theme "Walking by Faith" added.');

        $theme = DevotionTheme::sole();
        $this->assertSame('2026-11-01', $theme->starts_on->toDateString());
        $this->assertSame('2026-11-30', $theme->ends_on->toDateString());
        $this->assertSame($this->users['Pastor']->id, $theme->created_by);
    }

    public function test_validation(): void
    {
        $this->as('Pastor')->post(route('devotion-themes.store'), [])->assertSessionHasErrors(['title', 'starts_on', 'ends_on']);
        $this->as('Pastor')->post(route('devotion-themes.store'), $this->payload(['ends_on' => '2026-10-31']))->assertSessionHasErrors('ends_on');
        $this->assertSame(0, DevotionTheme::count());

        $this->as('Pastor')->post(route('devotion-themes.store'), $this->payload(['starts_on' => '2026-11-15', 'ends_on' => '2026-11-15']))
            ->assertSessionHasNoErrors();   // a one-day theme is fine
    }

    public function test_a_theme_can_be_edited(): void
    {
        $theme = $this->theme('2026-11-01', '2026-11-30', 'Old title');

        $this->as('Office Staff')->put(route('devotion-themes.update', $theme), $this->payload(['title' => 'New title', 'ends_on' => '2026-12-06']))
            ->assertRedirect(route('devotion-themes.index'));

        $theme->refresh();
        $this->assertSame('New title', $theme->title);
        $this->assertSame('2026-12-06', $theme->ends_on->toDateString());
    }

    public function test_overlapping_themes_are_allowed_and_the_latest_start_applies(): void
    {
        $this->as('Pastor')->post(route('devotion-themes.store'), $this->payload(['title' => 'November: Faith']))->assertSessionHasNoErrors();
        $this->as('Pastor')->post(route('devotion-themes.store'), $this->payload(['title' => 'Thanksgiving week', 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-12']))
            ->assertSessionHasNoErrors();

        $this->as('Pastor')->get(route('devotion-themes.index'))
            ->assertOk()
            ->assertSee("Today's theme: <strong>Thanksgiving week</strong>", false);
    }

    public function test_deleting_a_theme_unlinks_and_flags_its_devotions(): void
    {
        $theme = $this->theme('2026-11-01', '2026-11-30');
        $devotion = $this->devotion(['theme_id' => $theme->id]);
        $deleted = $this->devotion(['publish_date' => '2026-11-21', 'theme_id' => $theme->id]);
        $deleted->delete();

        $this->as('Pastor')->delete(route('devotion-themes.destroy', $theme))->assertRedirect(route('devotion-themes.index'));

        $this->assertSame(0, DevotionTheme::count());
        $devotion->refresh();
        $this->assertNull($devotion->theme_id);
        $this->assertTrue($devotion->no_theme);
        $this->assertTrue(Devotion::withTrashed()->find($deleted->id)->no_theme);
    }

    public function test_the_themes_button_is_only_for_devotion_managers(): void
    {
        $this->as('Office Staff')->get(route('devotions.index'))->assertSee(route('devotion-themes.index'));
        $this->as('Devotion Translator')->get(route('devotions.index'))->assertOk()->assertDontSee(route('devotion-themes.index'));
    }
}
