<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dashes must render as real characters, never as the literal six-character
 * escape text "backslash, u, 2, 0, 1, 3/4" (PHP does not convert such escapes
 * in single-quoted strings, and HTML never converts them at all).
 *
 * The escape text and the dash characters are built with chr()/mb_chr() on
 * purpose, so the test's own source can't be silently "fixed" by an editor
 * turning the escape into a real dash.
 */
class DashDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** The literal text backslash + "u" + hex code, e.g. \ u 2 0 1 3 (6 characters). */
    private static function escapeText(string $hex): string
    {
        return chr(92) . 'u' . $hex;
    }

    private static function enDash(): string
    {
        return mb_chr(0x2013, 'UTF-8');
    }

    private static function emDash(): string
    {
        return mb_chr(0x2014, 'UTF-8');
    }

    private function userWith(string ...$permissions): User
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        $registrar->forgetCachedPermissions();

        return $user;
    }

    public static function dateRanges(): array
    {
        $d = ' ' . self::enDash() . ' ';

        return [
            'from and to' => [['date_from' => '2026-01-01', 'date_to' => '2026-12-31'], 'Jan 01, 2026' . $d . 'Dec 31, 2026'],
            'from only'   => [['date_from' => '2026-01-01'],                            'Jan 01, 2026' . $d . 'Today'],
            'to only'     => [['date_to' => '2026-12-31'],                              'Start' . $d . 'Dec 31, 2026'],
        ];
    }

    public function test_the_helpers_build_the_intended_bytes(): void
    {
        $this->assertSame('5c7532303133', bin2hex(self::escapeText('2013')), 'escape text must be backslash + u2013');
        $this->assertSame('e28093', bin2hex(self::enDash()), 'en dash must be U+2013');
        $this->assertSame('e28094', bin2hex(self::emDash()), 'em dash must be U+2014');
    }

    #[DataProvider('dateRanges')]
    public function test_finance_index_stat_cards_show_an_en_dash_in_the_date_label(array $filters, string $label): void
    {
        $response = $this->actingAs($this->userWith('view finance'))
            ->get(route('finance.index', $filters));

        $response->assertOk();
        $response->assertSee('Income ' . $label, false);
        $response->assertSee('Expenses ' . $label, false);
        $response->assertDontSee(self::escapeText('2013'), false);
    }

    public function test_new_pledge_form_shows_an_em_dash_in_the_service_hint(): void
    {
        $response = $this->actingAs($this->userWith('manage pledges'))
            ->get(route('pledges.create'));

        $response->assertOk();
        $response->assertSee('(optional ' . self::emDash() . ' which service was this pledge made in?)', false);
        $response->assertDontSee(self::escapeText('2014'), false);
    }
}
