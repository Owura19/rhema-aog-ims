<?php

namespace Tests\Feature\Api;

use App\Models\DayBornOffering;
use App\Models\DayBornWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/day-born/latest and /api/v1/day-born. */
class DayBornApiTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();   // 2026-11-09
        $this->signInAsMember();
    }

    /** @param array<int, string> $amounts weekday => amount (others 0) */
    private function week(string $date, array $amounts, string $status = DayBornWeek::STATUS_PUBLISHED, array $attributes = []): DayBornWeek
    {
        $week = DayBornWeek::create($attributes + ['service_date' => $date, 'status' => $status, 'notes' => 'Counted by ushers']);
        foreach (range(0, 6) as $weekday) {
            DayBornOffering::create(['week_id' => $week->id, 'weekday' => $weekday, 'amount' => $amounts[$weekday] ?? '0']);
        }

        return $week;
    }

    public function test_access(): void
    {
        $this->getJson('/api/v1/day-born/latest')->assertUnauthorized();

        $this->token = User::factory()->create(['must_change_password' => true])->createAppToken('Phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->api('GET', '/day-born/latest')->assertForbidden()->assertJsonPath('code', 'password_change_required');

        $this->token = User::factory()->create(['member_id' => null])->createAppToken('Office PC')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->api('GET', '/day-born/latest')->assertOk();   // no member record needed
        $this->api('GET', '/day-born')->assertOk();
    }

    public function test_nothing_published_yet_is_null(): void
    {
        $this->week('2026-11-08', [5 => '100'], DayBornWeek::STATUS_DRAFT);

        $this->api('GET', '/day-born/latest')->assertOk()->assertExactJson(['data' => null]);
        $this->api('GET', '/day-born')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_latest_published_week_ranked_with_exact_keys(): void
    {
        $this->week('2026-11-01', [0 => '10']);
        $this->week('2026-11-04', [3 => '5'], DayBornWeek::STATUS_PUBLISHED, ['special_service' => true]);
        $this->week('2026-11-08', [0 => '99'], DayBornWeek::STATUS_DRAFT);   // newer, but a draft
        $this->week('2026-10-25', [1 => '1']);

        $response = $this->api('GET', '/day-born/latest')->assertOk();

        $response->assertJsonPath('data.service_date', '2026-11-04')->assertJsonPath('data.special_service', true);
        $this->assertSame(['service_date', 'special_service', 'total', 'groups'], array_keys($response->json('data')));
        $this->assertStringNotContainsString('Counted by ushers', $response->getContent());
    }

    public function test_groups_ranked_with_shared_positions(): void
    {
        $this->week('2026-11-08', [0 => '50', 1 => '20', 2 => '50', 4 => '0.50', 5 => '70.25']);

        $this->api('GET', '/day-born/latest')->assertOk()->assertExactJson(['data' => [
            'service_date'    => '2026-11-08',
            'special_service' => false,
            'total'           => '190.75',
            'groups'          => [
                ['position' => 1, 'weekday' => 'Friday', 'akan' => ['male' => 'Kofi', 'female' => 'Afua'], 'label' => 'Kofi/Afua', 'amount' => '70.25'],
                ['position' => 2, 'weekday' => 'Sunday', 'akan' => ['male' => 'Kwasi', 'female' => 'Akosua'], 'label' => 'Kwasi/Akosua', 'amount' => '50.00'],
                ['position' => 2, 'weekday' => 'Tuesday', 'akan' => ['male' => 'Kwabena', 'female' => 'Abenaa'], 'label' => 'Kwabena/Abenaa', 'amount' => '50.00'],
                ['position' => 4, 'weekday' => 'Monday', 'akan' => ['male' => 'Kwadwo', 'female' => 'Adwoa'], 'label' => 'Kwadwo/Adwoa', 'amount' => '20.00'],
                ['position' => 5, 'weekday' => 'Thursday', 'akan' => ['male' => 'Yaw', 'female' => 'Yaa'], 'label' => 'Yaw/Yaa', 'amount' => '0.50'],
                ['position' => 6, 'weekday' => 'Wednesday', 'akan' => ['male' => 'Kwaku', 'female' => 'Akua'], 'label' => 'Kwaku/Akua', 'amount' => '0.00'],
                ['position' => 6, 'weekday' => 'Saturday', 'akan' => ['male' => 'Kwame', 'female' => 'Amma'], 'label' => 'Kwame/Amma', 'amount' => '0.00'],
            ],
        ]]);
    }

    public function test_previous_weeks_newest_first_and_paged(): void
    {
        foreach (['2026-10-04', '2026-10-11', '2026-10-18', '2026-10-25'] as $date) {
            $this->week($date, [0 => '1']);
        }
        $this->week('2026-11-01', [0 => '1'], DayBornWeek::STATUS_DRAFT);

        $first = $this->api('GET', '/day-born?per_page=3')->assertOk();
        $this->assertSame(['2026-10-25', '2026-10-18', '2026-10-11'], array_column($first->json('data'), 'service_date'));
        $first->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2);

        $second = $this->api('GET', '/day-born?per_page=3&page=2')->assertOk();
        $this->assertSame(['2026-10-04'], array_column($second->json('data'), 'service_date'));

        $this->api('GET', '/day-born?per_page=31')->assertUnprocessable();
    }
}
