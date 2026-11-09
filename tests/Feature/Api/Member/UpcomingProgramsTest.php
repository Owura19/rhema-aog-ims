<?php

namespace Tests\Feature\Api\Member;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/programs/upcoming. Today is Monday 2026-11-09. */
class UpcomingProgramsTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->signInAsMember();
    }

    /** @return list<string> "kind:id" in feed order */
    private function feed(TestResponse $response): array
    {
        return array_map(fn (array $item) => "{$item['kind']}:{$item['id']}", $response->json('data'));
    }

    public function test_the_default_limit_is_5(): void
    {
        foreach (range(10, 16) as $day) {
            $this->service(['service_date' => "2026-11-{$day}"]);
        }

        $response = $this->api('GET', '/programs/upcoming')->assertOk();

        $this->assertCount(5, $response->json('data'));
        $response->assertJsonPath('data.0.date', '2026-11-10')->assertJsonPath('data.4.date', '2026-11-14');
    }

    public function test_up_to_20_can_be_asked_for(): void
    {
        foreach (range(10, 30) as $day) {
            $this->service(['service_date' => "2026-11-{$day}"]);   // 21 services
        }

        $this->assertCount(20, $this->api('GET', '/programs/upcoming?limit=20')->assertOk()->json('data'));
    }

    public static function badLimits(): array
    {
        return ['zero' => ['0'], 'twenty-one' => ['21'], 'text' => ['abc']];
    }

    #[DataProvider('badLimits')]
    public function test_bad_limits_are_422(string $limit): void
    {
        $this->api('GET', "/programs/upcoming?limit={$limit}")
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('limit');
    }

    public function test_only_items_that_have_not_ended_are_listed_with_cancelled_ones_marked(): void
    {
        $retreat   = $this->event(['title' => 'Retreat', 'start_date' => '2026-11-08', 'end_date' => '2026-11-10', 'status' => 'Ongoing']);
        $tonight   = $this->service(['service_date' => '2026-11-09', 'start_time' => '18:30', 'status' => 'Scheduled']);
        $offEvent  = $this->event(['start_date' => '2026-11-11', 'status' => 'Cancelled']);
        $offSvc    = $this->service(['service_date' => '2026-11-12', 'status' => 'Cancelled']);

        $this->event(['start_date' => '2026-11-08']);                                         // ended yesterday
        $this->service(['service_date' => '2026-11-08']);                                     // yesterday
        $this->service(['service_date' => '2026-11-09', 'start_time' => '06:00', 'status' => 'Completed']);   // done today
        $this->event(['start_date' => '2026-11-13', 'status' => 'Completed']);                // marked done
        $this->event(['start_date' => '2026-11-13', 'status' => 'Draft']);
        $this->event(['start_date' => '2026-11-13'])->delete();

        $response = $this->api('GET', '/programs/upcoming?limit=20')->assertOk();

        $this->assertSame(
            ["event:{$retreat->id}", "service:{$tonight->id}", "event:{$offEvent->id}", "service:{$offSvc->id}"],
            $this->feed($response),
        );
        $response->assertJsonPath('data.2.cancelled', true)->assertJsonPath('data.3.cancelled', true);
    }

    public function test_events_and_services_are_merged_in_date_order_before_the_limit(): void
    {
        $this->event(['start_date' => '2026-11-13']);
        $s10 = $this->service(['service_date' => '2026-11-10']);
        $s12 = $this->service(['service_date' => '2026-11-12']);
        $e11 = $this->event(['start_date' => '2026-11-11']);
        $this->service(['service_date' => '2026-11-14']);

        $this->assertSame(
            ["service:{$s10->id}", "event:{$e11->id}", "service:{$s12->id}"],
            $this->feed($this->api('GET', '/programs/upcoming?limit=3')->assertOk()),
        );
    }

    public function test_items_have_the_calendar_shape(): void
    {
        $this->service(['service_date' => '2026-11-10']);
        $this->event(['start_date' => '2026-11-11']);

        $response = $this->api('GET', '/programs/upcoming')->assertOk();

        $keys = ['kind', 'id', 'title', 'type', 'date', 'end_date', 'start_time', 'end_time', 'venue', 'status', 'cancelled'];
        $this->assertSame('service', $response->json('data.0.kind'));
        $this->assertSame($keys, array_keys($response->json('data.0')));
        $this->assertSame('event', $response->json('data.1.kind'));
        $this->assertSame($keys, array_keys($response->json('data.1')));
        $this->assertSame(['data'], array_keys($response->json()));
    }
}
