<?php

namespace Tests\Feature\Api\Member;

use App\Models\EventRsvp;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** POST and DELETE /api/v1/events/{id}/rsvp. Today is Monday 2026-11-09. */
class EventRsvpTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    private Member $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->signInAsMember();
        $this->other = $this->member(['first_name' => 'Kofi', 'last_name' => 'Boateng']);
    }

    private function mine(int $eventId): ?EventRsvp
    {
        return EventRsvp::where('event_id', $eventId)->where('member_id', $this->me->id)->first();
    }

    // ── RSVP ────────────────────────────────────────────────────────────

    public function test_an_rsvp_is_created_once_and_then_updated(): void
    {
        $event = $this->event();

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 2])
            ->assertCreated()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.rsvp.mine.guests_count', 2)
            ->assertJsonPath('data.rsvp.mine.status', 'Confirmed');

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 3, 'notes' => 'Wheelchair access please'])
            ->assertOk()
            ->assertJsonPath('data.rsvp.mine.guests_count', 3);

        $this->api('POST', "/events/{$event->id}/rsvp")   // no body: party of 1, notes kept
            ->assertOk()
            ->assertJsonPath('data.rsvp.mine.guests_count', 1);

        $this->assertSame(1, EventRsvp::where('event_id', $event->id)->count());
        $this->assertSame('Wheelchair access please', $this->mine($event->id)->notes);
    }

    public function test_the_member_always_comes_from_the_token(): void
    {
        $event = $this->event();

        $this->api('POST', "/events/{$event->id}/rsvp", ['member_id' => $this->other->id])->assertCreated();

        $this->assertSame($this->me->id, EventRsvp::sole()->member_id);
    }

    public function test_draft_deleted_and_missing_events_are_404(): void
    {
        $draft = $this->event(['status' => 'Draft']);
        $deleted = $this->event();
        $deleted->delete();

        foreach ([$draft->id, $deleted->id, 999999999] as $id) {
            $this->api('POST', "/events/{$id}/rsvp")->assertNotFound()->assertJsonPath('code', 'not_found');
            $this->api('DELETE', "/events/{$id}/rsvp")->assertNotFound();
        }
        $this->api('POST', '/events/99999999999999999999/rsvp')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->assertSame(0, EventRsvp::count());
    }

    public static function closedEvents(): array
    {
        return [
            'cancelled'              => [['status' => 'Cancelled'], 'event_cancelled'],
            'ended yesterday'        => [['start_date' => '2026-11-08'], 'event_ended'],
            'marked completed today' => [['start_date' => '2026-11-09', 'status' => 'Completed'], 'event_ended'],
            'deadline was yesterday' => [['rsvp_deadline' => '2026-11-08'], 'rsvp_closed'],
        ];
    }

    #[DataProvider('closedEvents')]
    public function test_closed_events_refuse_rsvps(array $attributes, string $code): void
    {
        $event = $this->event($attributes);

        $this->api('POST', "/events/{$event->id}/rsvp")->assertStatus(409)->assertJsonPath('code', $code);

        $this->assertNull($this->mine($event->id));
    }

    public function test_the_deadline_day_itself_is_open(): void
    {
        $event = $this->event(['rsvp_deadline' => '2026-11-09']);

        $this->api('POST', "/events/{$event->id}/rsvp")->assertCreated();
    }

    public function test_a_paid_event_can_be_rsvped(): void
    {
        $event = $this->event(['is_free' => false, 'ticket_price' => 50]);

        $this->api('POST', "/events/{$event->id}/rsvp")
            ->assertCreated()
            ->assertJsonPath('data.is_free', false)
            ->assertJsonPath('data.ticket_price', '50.00');
    }

    public function test_capacity_is_respected(): void
    {
        $event = $this->event(['capacity' => 5]);
        $this->rsvp($event, $this->other, 4);

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 2])
            ->assertStatus(409)
            ->assertJsonPath('code', 'event_full')
            ->assertJsonPath('spots_left', 1);
        $this->assertNull($this->mine($event->id));

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 1])->assertCreated();
    }

    public function test_changing_my_party_is_not_blocked_by_my_own_places(): void
    {
        $event = $this->event(['capacity' => 5]);
        $this->rsvp($event, $this->other, 2);
        $this->rsvp($event, $this->me, 3);   // the event is now full

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 3])->assertOk();

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 4])
            ->assertStatus(409)
            ->assertJsonPath('code', 'event_full')
            ->assertJsonPath('spots_left', 3);
        $this->assertSame(3, $this->mine($event->id)->guests_count);
    }

    public static function badParties(): array
    {
        return ['zero' => [0], 'eleven' => [11], 'text' => ['abc'], 'fraction' => [2.5]];
    }

    #[DataProvider('badParties')]
    public function test_the_party_must_be_1_to_10(mixed $guests): void
    {
        $event = $this->event();

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => $guests])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('guests_count');
    }

    public function test_a_party_of_10_is_allowed(): void
    {
        $event = $this->event();

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 10])->assertCreated();
    }

    // ── Cancel ──────────────────────────────────────────────────────────

    public function test_cancelling_keeps_the_row_marked_cancelled_and_frees_the_places(): void
    {
        $event = $this->event(['capacity' => 5]);
        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 3])->assertCreated();

        $this->api('DELETE', "/events/{$event->id}/rsvp")->assertNoContent();

        $this->assertSame('Cancelled', $this->mine($event->id)->status);
        $this->assertSame(1, EventRsvp::count());
        $this->api('GET', "/events/{$event->id}")
            ->assertJsonPath('data.rsvp.spots_left', 5)
            ->assertJsonPath('data.rsvp.mine.status', 'Cancelled');
    }

    public function test_rsvping_again_reactivates_the_same_row(): void
    {
        $event = $this->event();
        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 2])->assertCreated();
        $rowId = $this->mine($event->id)->id;
        $this->api('DELETE', "/events/{$event->id}/rsvp")->assertNoContent();

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 1])
            ->assertOk()
            ->assertJsonPath('data.rsvp.mine.status', 'Confirmed');

        $this->assertSame($rowId, $this->mine($event->id)->id);
        $this->assertSame(1, EventRsvp::count());
    }

    public function test_cancelling_with_nothing_to_cancel_is_still_204(): void
    {
        $event = $this->event();

        $this->api('DELETE', "/events/{$event->id}/rsvp")->assertNoContent();
        $this->rsvp($event, $this->me, 1, 'Cancelled');
        $this->api('DELETE', "/events/{$event->id}/rsvp")->assertNoContent();

        $this->assertSame('Cancelled', $this->mine($event->id)->status);
    }

    public function test_cancelling_after_the_event_has_ended_is_refused(): void
    {
        $event = $this->event(['start_date' => '2026-11-08']);
        $this->rsvp($event, $this->me, 2);

        $this->api('DELETE', "/events/{$event->id}/rsvp")->assertStatus(409)->assertJsonPath('code', 'event_ended');

        $this->assertSame('Confirmed', $this->mine($event->id)->status);
    }

    public function test_an_rsvp_to_a_cancelled_event_can_still_be_cancelled(): void
    {
        $event = $this->event(['status' => 'Cancelled']);
        $this->rsvp($event, $this->me, 2);

        $this->api('DELETE', "/events/{$event->id}/rsvp")->assertNoContent();

        $this->assertSame('Cancelled', $this->mine($event->id)->status);
    }

    public function test_a_pending_rsvp_holds_no_place_and_can_be_confirmed_or_cancelled(): void
    {
        $event = $this->event(['capacity' => 3]);
        $this->rsvp($event, $this->other, 2);
        $pending = $this->rsvp($event, $this->me, 2, 'Pending');

        $this->api('GET', "/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.rsvp.spots_left', 1)
            ->assertJsonPath('data.rsvp.mine.status', 'Pending')
            ->assertJsonPath('data.rsvp.open', true);

        $this->api('POST', "/events/{$event->id}/rsvp", ['guests_count' => 1])
            ->assertOk()
            ->assertJsonPath('data.rsvp.mine.status', 'Confirmed');
        $this->assertSame($pending->id, $this->mine($event->id)->id);
        $this->assertSame(2, EventRsvp::count());

        $this->api('DELETE', "/events/{$event->id}/rsvp")->assertNoContent();
        $this->assertSame('Cancelled', $this->mine($event->id)->status);

        // Pending → Cancelled directly
        $second = $this->event(['title' => 'Second event']);
        $this->rsvp($second, $this->me, 1, 'Pending');

        $this->api('DELETE', "/events/{$second->id}/rsvp")->assertNoContent();
        $this->assertSame('Cancelled', $this->mine($second->id)->status);
    }

    public function test_cancelling_never_touches_other_members(): void
    {
        $event = $this->event();
        $theirs = $this->rsvp($event, $this->other, 2);
        $this->rsvp($event, $this->me, 1);

        $this->api('DELETE', "/events/{$event->id}/rsvp")->assertNoContent();

        $this->assertSame('Confirmed', $theirs->fresh()->status);
    }
}
