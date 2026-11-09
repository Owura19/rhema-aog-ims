<?php

namespace Tests\Feature\Api\Member;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/calendar. Today is Monday 2026-11-09. */
class CalendarTest extends TestCase
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

    public function test_the_default_range_is_today_plus_29_days(): void
    {
        $this->service(['service_date' => '2026-11-08']);                       // yesterday: out
        $today    = $this->service(['service_date' => '2026-11-09']);
        $lastDay  = $this->service(['service_date' => '2026-12-08']);
        $this->service(['service_date' => '2026-12-09']);                       // day 31: out
        $event    = $this->event(['start_date' => '2026-12-08']);
        $this->event(['start_date' => '2026-12-09']);                           // out

        $response = $this->api('GET', '/calendar')->assertOk();

        $response->assertJsonPath('meta', ['from' => '2026-11-09', 'to' => '2026-12-08']);
        // On 2026-12-08 the event has no start time, so it comes before the 07:30 service.
        $this->assertSame(["service:{$today->id}", "event:{$event->id}", "service:{$lastDay->id}"], $this->feed($response));
    }

    public function test_a_custom_range(): void
    {
        $inside = $this->service(['service_date' => '2026-12-20']);
        $this->service(['service_date' => '2026-11-20']);

        $response = $this->api('GET', '/calendar?from=2026-12-01&to=2026-12-31')->assertOk();

        $response->assertJsonPath('meta', ['from' => '2026-12-01', 'to' => '2026-12-31']);
        $this->assertSame(["service:{$inside->id}"], $this->feed($response));
    }

    public function test_the_range_can_be_92_days_but_not_93(): void
    {
        $this->api('GET', '/calendar?from=2026-11-09&to=2027-02-08')   // 92 days inclusive
            ->assertOk()
            ->assertJsonPath('meta.to', '2027-02-08');

        $this->api('GET', '/calendar?from=2026-11-09&to=2027-02-09')   // 93
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrorFor('to');
    }

    public static function badRanges(): array
    {
        return [
            'month 13'                 => ['from=2026-13-01', 'from'],
            'February 30'              => ['from=2026-02-30', 'from'],
            'day-month-year'           => ['from=09-11-2026', 'from'],
            'no leading zero'          => ['from=2026-11-9', 'from'],
            'not a date'               => ['to=soon', 'to'],
            'to before from'           => ['from=2026-11-10&to=2026-11-09', 'to'],
            'to before the default from' => ['to=2026-11-01', 'to'],
        ];
    }

    #[DataProvider('badRanges')]
    public function test_bad_ranges_are_422(string $query, string $field): void
    {
        $this->api('GET', "/calendar?{$query}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrorFor($field);
    }

    public function test_multi_day_events_overlapping_either_edge_are_included(): void
    {
        $acrossStart = $this->event(['title' => 'Across the start', 'start_date' => '2026-11-28', 'end_date' => '2026-12-02']);
        $acrossEnd   = $this->event(['title' => 'Across the end', 'start_date' => '2026-12-09', 'end_date' => '2026-12-12']);
        $wholeRange  = $this->event(['title' => 'Whole range', 'start_date' => '2026-11-20', 'end_date' => '2026-12-20']);
        $this->event(['title' => 'Ends the day before', 'start_date' => '2026-11-25', 'end_date' => '2026-11-30']);
        $this->event(['title' => 'Starts the day after', 'start_date' => '2026-12-11', 'end_date' => '2026-12-12']);

        $response = $this->api('GET', '/calendar?from=2026-12-01&to=2026-12-10')->assertOk();

        $this->assertSame(["event:{$wholeRange->id}", "event:{$acrossStart->id}", "event:{$acrossEnd->id}"], $this->feed($response));
        $response->assertJsonPath('data.1.date', '2026-11-28')->assertJsonPath('data.1.end_date', '2026-12-02');
    }

    public function test_draft_and_deleted_events_are_left_out_and_every_service_status_is_shown(): void
    {
        $this->event(['status' => 'Draft', 'start_date' => '2026-11-15']);
        $this->event(['start_date' => '2026-11-15'])->delete();
        $completedEvent = $this->event(['status' => 'Completed', 'start_date' => '2026-11-09']);

        $services = collect(['Scheduled', 'Ongoing', 'Completed', 'Cancelled'])
            ->map(fn (string $status, int $i) => $this->service(['status' => $status, 'service_date' => '2026-11-1' . $i]));

        $response = $this->api('GET', '/calendar')->assertOk();

        $this->assertSame(
            ["event:{$completedEvent->id}", ...$services->map(fn ($s) => "service:{$s->id}")->all()],
            $this->feed($response),
        );
    }

    public function test_cancelled_items_are_included_and_marked(): void
    {
        $event = $this->event(['status' => 'Cancelled', 'start_date' => '2026-11-12']);
        $service = $this->service(['status' => 'Cancelled', 'service_date' => '2026-11-13']);
        $this->service(['service_date' => '2026-11-14']);

        $response = $this->api('GET', '/calendar')->assertOk();

        $response->assertJsonPath('data.0.id', $event->id)
            ->assertJsonPath('data.0.status', 'Cancelled')
            ->assertJsonPath('data.0.cancelled', true)
            ->assertJsonPath('data.1.id', $service->id)
            ->assertJsonPath('data.1.cancelled', true)
            ->assertJsonPath('data.2.cancelled', false);
    }

    public function test_items_are_ordered_by_date_then_time_then_services_first_then_id(): void
    {
        $saturday  = $this->event(['start_date' => '2026-11-14', 'start_time' => '20:00']);
        $allDay    = $this->event(['start_date' => '2026-11-15']);                                  // no time: first that day
        $early     = $this->service(['service_date' => '2026-11-15', 'start_time' => '07:30']);
        $nineEvent = $this->event(['start_date' => '2026-11-15', 'start_time' => '09:00']);
        $nineSvc   = $this->service(['service_date' => '2026-11-15', 'start_time' => '09:00']);       // service before event at 09:00
        $nineEvent2 = $this->event(['start_date' => '2026-11-15', 'start_time' => '09:00']);          // same time and kind: by id

        $this->assertSame([
            "event:{$saturday->id}",
            "event:{$allDay->id}",
            "service:{$early->id}",
            "service:{$nineSvc->id}",
            "event:{$nineEvent->id}",
            "event:{$nineEvent2->id}",
        ], $this->feed($this->api('GET', '/calendar')->assertOk()));
    }

    public function test_each_kind_has_exactly_these_fields(): void
    {
        $staff = User::factory()->create();
        $event = $this->event([
            'title'      => 'Youth Convention',
            'type'       => 'Convention',
            'start_date' => '2026-11-20',
            'end_date'   => '2026-11-22',
            'start_time' => '18:00',
            'venue'      => 'Main Auditorium',
            'capacity'   => 100,
            'created_by' => $staff->id,
        ]);
        $this->rsvp($event, $this->me, 2);
        $service = $this->service([
            'name'              => 'Sunday First Service',
            'service_type'      => 'Sunday Service',
            'service_date'      => '2026-11-15',
            'start_time'        => '07:30',
            'end_time'          => '09:30',
            'venue'             => 'Main Auditorium',
            'description'       => 'Ushers: arrive 30 minutes early',
            'biometric_enabled' => true,
            'created_by'        => $staff->id,
        ]);

        $response = $this->api('GET', '/calendar')->assertOk();

        $response->assertExactJson([
            'data' => [
                [
                    'kind'       => 'service',
                    'id'         => $service->id,
                    'title'      => 'Sunday First Service',
                    'type'       => 'Sunday Service',
                    'date'       => '2026-11-15',
                    'end_date'   => null,
                    'start_time' => '07:30',
                    'end_time'   => '09:30',
                    'venue'      => 'Main Auditorium',
                    'status'     => 'Scheduled',
                    'cancelled'  => false,
                ],
                [
                    'kind'       => 'event',
                    'id'         => $event->id,
                    'title'      => 'Youth Convention',
                    'type'       => 'Convention',
                    'date'       => '2026-11-20',
                    'end_date'   => '2026-11-22',
                    'start_time' => '18:00',
                    'end_time'   => null,
                    'venue'      => 'Main Auditorium',
                    'status'     => 'Published',
                    'cancelled'  => false,
                ],
            ],
            'meta' => ['from' => '2026-11-09', 'to' => '2026-12-08'],
        ]);
        $this->assertStringNotContainsString('Ushers', $response->getContent());
    }
}
