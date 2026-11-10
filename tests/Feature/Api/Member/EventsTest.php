<?php

namespace Tests\Feature\Api\Member;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/events and /api/v1/events/{id}. Today is Monday 2026-11-09. */
class EventsTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->signInAsMember();
    }

    public function test_upcoming_lists_events_that_have_not_ended_earliest_first_with_cancelled_ones_marked(): void
    {
        $retreat   = $this->event(['title' => 'Retreat', 'start_date' => '2026-11-08', 'end_date' => '2026-11-10', 'status' => 'Ongoing']);
        $tonight   = $this->event(['title' => 'Tonight', 'start_date' => '2026-11-09', 'start_time' => '18:00']);
        $cancelled = $this->event(['title' => 'Called off', 'start_date' => '2026-11-15', 'status' => 'Cancelled']);
        $later     = $this->event(['title' => 'Later', 'start_date' => '2026-11-20']);

        $this->event(['title' => 'Draft', 'start_date' => '2026-11-12', 'status' => 'Draft']);
        $this->event(['title' => 'Marked done', 'start_date' => '2026-11-12', 'status' => 'Completed']);
        $this->event(['title' => 'Yesterday', 'start_date' => '2026-11-08']);
        $this->event(['title' => 'Deleted', 'start_date' => '2026-11-13'])->delete();

        $response = $this->api('GET', '/events')->assertOk();

        $this->assertSame([$retreat->id, $tonight->id, $cancelled->id, $later->id], $this->ids($response));
        $response->assertJsonPath('data.2.status', 'Cancelled')
            ->assertJsonPath('data.2.rsvp.open', false)
            ->assertJsonPath('data.2.rsvp.closed_reason', 'cancelled');

        $this->assertSame($this->ids($response), $this->ids($this->api('GET', '/events?scope=upcoming')));
    }

    public function test_recent_lists_events_from_the_last_30_days_most_recent_first(): void
    {
        $doneToday = $this->event(['title' => 'Done today', 'start_date' => '2026-11-09', 'status' => 'Completed']);
        $yesterday = $this->event(['title' => 'Yesterday', 'start_date' => '2026-11-08']);
        $edge      = $this->event(['title' => '30 days ago', 'start_date' => '2026-10-10']);

        $this->event(['title' => '31 days ago', 'start_date' => '2026-10-09']);
        $this->event(['title' => 'Cancelled', 'start_date' => '2026-11-05', 'status' => 'Cancelled']);
        $this->event(['title' => 'Draft', 'start_date' => '2026-11-05', 'status' => 'Draft']);
        $this->event(['title' => 'Upcoming', 'start_date' => '2026-11-20']);

        $response = $this->api('GET', '/events?scope=recent')->assertOk();

        $this->assertSame([$doneToday->id, $yesterday->id, $edge->id], $this->ids($response));
        $response->assertJsonPath('data.0.rsvp.closed_reason', 'ended');
    }

    public function test_one_event_has_this_shape(): void
    {
        $event = $this->event([
            'title'         => 'Youth Convention',
            'type'          => 'Convention',
            'description'   => 'Three days of worship.',
            'start_date'    => '2026-11-20',
            'end_date'      => '2026-11-22',
            'start_time'    => '09:00',
            'end_time'      => '17:30',
            'venue'         => 'Main Auditorium',
            'address'       => 'Spintex Road',
            'banner_image'  => 'events/banners/youth.jpg',
            'is_free'       => false,
            'ticket_price'  => 50,
            'capacity'      => 100,
            'rsvp_required' => true,
            'rsvp_deadline' => '2026-11-18',
        ]);

        $this->api('GET', "/events/{$event->id}")->assertOk()->assertExactJson(['data' => [
            'id'           => $event->id,
            'title'        => 'Youth Convention',
            'type'         => 'Convention',
            'description'  => 'Three days of worship.',
            'start_date'   => '2026-11-20',
            'end_date'     => '2026-11-22',
            'start_time'   => '09:00',
            'end_time'     => '17:30',
            'venue'        => 'Main Auditorium',
            'address'      => 'Spintex Road',
            'banner_url'   => asset('storage/events/banners/youth.jpg'),
            'is_free'      => false,
            'ticket_price' => '50.00',
            'status'       => 'Published',
            'rsvp'         => [
                'required'      => true,
                'deadline'      => '2026-11-18',
                'capacity'      => 100,
                'spots_left'    => 100,
                'open'          => true,
                'closed_reason' => null,
                'mine'          => null,
            ],
        ]]);
    }

    public function test_draft_deleted_and_missing_events_are_404(): void
    {
        $draft = $this->event(['status' => 'Draft']);
        $deleted = $this->event();
        $deleted->delete();

        foreach (["/events/{$draft->id}", "/events/{$deleted->id}", '/events/999999999', '/events/abc', '/events/99999999999999999999'] as $uri) {
            $this->api('GET', $uri)->assertNotFound()->assertJsonPath('code', 'not_found');
        }
    }

    public function test_spots_left_counts_confirmed_guests_only_and_other_members_stay_private(): void
    {
        $event = $this->event(['capacity' => 10]);
        $kofi = $this->member(['first_name' => 'Kofi', 'last_name' => 'Boateng']);
        $esi  = $this->member(['first_name' => 'Esi', 'last_name' => 'Owusu']);
        $yaw  = $this->member(['first_name' => 'Yaw', 'last_name' => 'Asante']);
        $this->rsvp($event, $kofi, 3, 'Confirmed', ['notes' => 'Kofi needs parking']);
        $this->rsvp($event, $esi, 2);
        $this->rsvp($event, $yaw, 4, 'Cancelled');

        $response = $this->api('GET', "/events/{$event->id}")->assertOk();

        $response->assertJsonPath('data.rsvp.spots_left', 5)->assertJsonPath('data.rsvp.mine', null);
        foreach (['Kofi', 'Boateng', 'Esi', 'Yaw', 'parking'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }

        $this->rsvp($event, $this->me, 2);

        $this->api('GET', "/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.rsvp.spots_left', 3)
            ->assertJsonPath('data.rsvp.mine', [
                'guests_count' => 2,
                'status'       => 'Confirmed',
                'created_at'   => '2026-11-09T10:00:00+00:00',
            ]);
    }

    public static function closedReasons(): array
    {
        return [
            'deadline was yesterday'  => [['rsvp_deadline' => '2026-11-08'], false, 'deadline_passed'],
            'deadline is today'       => [['rsvp_deadline' => '2026-11-09'], true, null],
            'ended yesterday'         => [['start_date' => '2026-11-08'], false, 'ended'],
            'marked completed'        => [['start_date' => '2026-11-09', 'status' => 'Completed'], false, 'ended'],
            'cancelled'               => [['status' => 'Cancelled'], false, 'cancelled'],
            'paid event is still open' => [['is_free' => false, 'ticket_price' => 20], true, null],
        ];
    }

    #[DataProvider('closedReasons')]
    public function test_rsvp_open_and_closed_reason(array $attributes, bool $open, ?string $reason): void
    {
        $event = $this->event($attributes);

        $this->api('GET', "/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.rsvp.open', $open)
            ->assertJsonPath('data.rsvp.closed_reason', $reason);
    }

    public function test_a_full_event_is_closed_except_to_members_already_booked(): void
    {
        $event = $this->event(['capacity' => 2]);
        $this->rsvp($event, $this->member(['first_name' => 'Kofi']), 2);

        $this->api('GET', "/events/{$event->id}")
            ->assertJsonPath('data.rsvp.open', false)
            ->assertJsonPath('data.rsvp.closed_reason', 'full')
            ->assertJsonPath('data.rsvp.spots_left', 0);

        $shared = $this->event(['capacity' => 2]);
        $this->rsvp($shared, $this->member(['first_name' => 'Esi']), 1);
        $this->rsvp($shared, $this->me, 1);

        $this->api('GET', "/events/{$shared->id}")
            ->assertJsonPath('data.rsvp.open', true)            // I can still change my own booking
            ->assertJsonPath('data.rsvp.spots_left', 0);
    }

    public function test_it_paginates(): void
    {
        foreach (['2026-11-20', '2026-11-21', '2026-11-22'] as $date) {
            $this->event(['start_date' => $date]);
        }

        $response = $this->api('GET', '/events?per_page=2')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $response->assertJsonPath('meta.total', 3);
    }

    public static function badQueries(): array
    {
        return [
            'unknown scope' => ['scope=past', 'scope'],
            'per_page 0'    => ['per_page=0', 'per_page'],
            'per_page 51'   => ['per_page=51', 'per_page'],
        ];
    }

    #[DataProvider('badQueries')]
    public function test_bad_queries_are_422(string $query, string $field): void
    {
        $this->api('GET', "/events?{$query}")->assertStatus(422)->assertJsonValidationErrorFor($field);
    }
}
