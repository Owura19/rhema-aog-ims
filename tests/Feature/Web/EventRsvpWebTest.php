<?php

namespace Tests\Feature\Web;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The staff events pages with RSVPs that the app can now mark Cancelled (or that are Pending):
 * counts use Confirmed RSVPs only, cancelled members can be re-added, and a member keeps one row.
 */
class EventRsvpWebTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;     // Super Admin: all event permissions
    private User $noEvents;  // Office Staff: no event permissions

    protected function setUp(): void
    {
        parent::setUp();

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $eventPermissions = ['view events', 'create events', 'edit events', 'delete events'];
        foreach ([...$eventPermissions, 'view members'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('Super Admin', 'web')->syncPermissions([...$eventPermissions, 'view members']);
        Role::findOrCreate('Office Staff', 'web')->syncPermissions(['view members']);

        $registrar->forgetCachedPermissions();

        $this->staff    = User::factory()->create()->assignRole('Super Admin');
        $this->noEvents = User::factory()->create()->assignRole('Office Staff');
    }

    private function event(array $attributes = []): Event
    {
        return Event::create($attributes + [
            'title'      => 'Youth Convention',
            'type'       => 'Convention',
            'start_date' => now()->addDays(10)->toDateString(),
            'status'     => 'Published',
        ]);
    }

    private function member(string $firstName, array $attributes = []): Member
    {
        return Member::create($attributes + [
            'first_name'        => $firstName,
            'last_name'         => 'Test',
            'membership_status' => 'Active',
        ]);
    }

    private function rsvp(Event $event, ?Member $member, int $guests, string $status = 'Confirmed', array $attributes = []): EventRsvp
    {
        return EventRsvp::create($attributes + [
            'event_id'     => $event->id,
            'member_id'    => $member?->id,
            'guests_count' => $guests,
            'status'       => $status,
        ]);
    }

    /** Confirmed 3 + 2 + guest 1 = 6 people in 3 RSVPs; Pending 4 and Cancelled 5 don't count. */
    private function eventWithMixedRsvps(): Event
    {
        $event = $this->event(['capacity' => 50]);
        $this->rsvp($event, $this->member('Abena'), 3);
        $this->rsvp($event, $this->member('Kwame'), 2);
        $this->rsvp($event, null, 1, 'Confirmed', ['guest_name' => 'Visiting Pastor']);
        $this->rsvp($event, $this->member('Esi'), 4, 'Pending');
        $this->rsvp($event, $this->member('Yaw'), 5, 'Cancelled');

        return $event;
    }

    private function addRsvp(Event $event, array $data): TestResponse
    {
        return $this->actingAs($this->staff)
            ->from(route('events.show', $event))
            ->post(route('events.rsvp', $event), $data + ['guests_count' => 1])
            ->assertRedirect(route('events.show', $event));
    }

    // ── counts ─────────────────────────────────────────────────────────

    public function test_the_index_counts_confirmed_guests_only(): void
    {
        $event = $this->eventWithMixedRsvps();

        $response = $this->actingAs($this->staff)->get(route('events.index'))->assertOk();

        $this->assertSame(6, (int) $response->viewData('events')->firstWhere('id', $event->id)->confirmed_guests);
        $response->assertSee('<span style="font-weight:700; color:#1e293b;">6</span>', false)
            ->assertSee(' / 50', false);
    }

    public function test_the_show_heading_counts_confirmed_rsvps_only(): void
    {
        $event = $this->eventWithMixedRsvps();

        $this->actingAs($this->staff)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('RSVP List (3 confirmed)')
            ->assertDontSee('RSVP List (5)');
    }

    public function test_the_rsvp_list_still_shows_pending_and_cancelled_badges(): void
    {
        $event = $this->eventWithMixedRsvps();

        $this->actingAs($this->staff)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('<span class="badge badge-warning">Pending</span>', false)
            ->assertSee('<span class="badge badge-danger">Cancelled</span>', false)
            ->assertSee('<span class="badge badge-success">Confirmed</span>', false);
    }

    // ── the "add attendee" dropdown ────────────────────────────────────

    public function test_the_dropdown_offers_cancelled_members_but_not_confirmed_or_pending_ones(): void
    {
        $event = $this->event();
        $confirmed = $this->member('Abena');
        $pending   = $this->member('Esi');
        $cancelled = $this->member('Yaw');
        $free      = $this->member('Kofi');
        $inactive  = $this->member('Ama', ['membership_status' => 'Inactive']);
        $this->rsvp($event, $confirmed, 1);
        $this->rsvp($event, $pending, 1, 'Pending');
        $this->rsvp($event, $cancelled, 1, 'Cancelled');

        $offered = $this->actingAs($this->staff)->get(route('events.show', $event))
            ->assertOk()
            ->viewData('members')
            ->pluck('id')
            ->all();

        $this->assertContains($cancelled->id, $offered);
        $this->assertContains($free->id, $offered);
        $this->assertNotContains($confirmed->id, $offered);
        $this->assertNotContains($pending->id, $offered);
        $this->assertNotContains($inactive->id, $offered);   // unchanged: Active members only
    }

    // ── adding an RSVP ─────────────────────────────────────────────────

    public static function earlierStatuses(): array
    {
        return ['cancelled' => ['Cancelled'], 'pending' => ['Pending']];
    }

    #[DataProvider('earlierStatuses')]
    public function test_re_adding_a_member_reuses_their_row_and_confirms_it(string $status): void
    {
        $event = $this->event();
        $member = $this->member('Yaw');
        $row = $this->rsvp($event, $member, 2, $status);

        $this->addRsvp($event, ['member_id' => $member->id, 'guests_count' => 1])
            ->assertSessionHas('success');

        $this->assertSame(1, EventRsvp::where('event_id', $event->id)->where('member_id', $member->id)->count());
        $fresh = $row->fresh();
        $this->assertSame('Confirmed', $fresh->status);
        $this->assertSame(1, $fresh->guests_count);
    }

    public function test_guest_rsvps_never_overwrite_each_other(): void
    {
        $event = $this->event();

        $this->addRsvp($event, ['guest_name' => 'Guest One', 'guests_count' => 2])->assertSessionHas('success');
        $this->addRsvp($event, ['guest_name' => 'Guest Two', 'guests_count' => 1])->assertSessionHas('success');

        $this->assertSame(['Guest One', 'Guest Two'], EventRsvp::where('event_id', $event->id)->orderBy('id')->pluck('guest_name')->all());
    }

    // ── capacity ───────────────────────────────────────────────────────

    public function test_capacity_includes_the_requested_party(): void
    {
        $event = $this->event(['capacity' => 5]);
        $this->rsvp($event, $this->member('Abena'), 4);
        $newcomer = $this->member('Kofi');

        $this->addRsvp($event, ['member_id' => $newcomer->id, 'guests_count' => 2])
            ->assertSessionHas('error', 'Sorry, only 1 place is left for this event.');
        $this->assertSame(0, EventRsvp::where('member_id', $newcomer->id)->count());

        $this->addRsvp($event, ['member_id' => $newcomer->id, 'guests_count' => 1])->assertSessionHas('success');
    }

    public function test_a_members_own_confirmed_places_do_not_block_changing_their_party(): void
    {
        $event = $this->event(['capacity' => 5]);
        $this->rsvp($event, $this->member('Abena'), 2);
        $member = $this->member('Kwame');
        $mine = $this->rsvp($event, $member, 3);   // the event is now full

        $this->addRsvp($event, ['member_id' => $member->id, 'guests_count' => 3])->assertSessionHas('success');

        $this->addRsvp($event, ['member_id' => $member->id, 'guests_count' => 4])
            ->assertSessionHas('error', 'Sorry, only 3 places are left for this event.');
        $this->assertSame(3, $mine->fresh()->guests_count);
    }

    public function test_pending_and_cancelled_places_do_not_count(): void
    {
        $event = $this->event(['capacity' => 3]);
        $this->rsvp($event, $this->member('Esi'), 3, 'Pending');
        $this->rsvp($event, $this->member('Yaw'), 3, 'Cancelled');

        $this->addRsvp($event, ['member_id' => $this->member('Kofi')->id, 'guests_count' => 3])->assertSessionHas('success');
    }

    public function test_a_fully_booked_event_says_so(): void
    {
        $event = $this->event(['capacity' => 2]);
        $this->rsvp($event, $this->member('Abena'), 2);

        $this->addRsvp($event, ['guest_name' => 'Late guest'])
            ->assertSessionHas('error', 'Sorry, this event is fully booked.');
        $this->assertSame(1, EventRsvp::where('event_id', $event->id)->count());
    }

    // ── cancelling (staff) ─────────────────────────────────────────────

    public function test_cancelling_through_another_events_url_is_404_and_changes_nothing(): void
    {
        $event = $this->event();
        $other = $this->event(['title' => 'Another event']);
        $rsvp = $this->rsvp($event, $this->member('Abena'), 2);

        $this->actingAs($this->staff)
            ->delete(route('events.cancel-rsvp', [$other, $rsvp]))
            ->assertNotFound();

        $this->assertSame('Confirmed', $rsvp->fresh()?->status);
    }

    public function test_a_web_cancel_keeps_the_row_as_cancelled_and_the_counts_drop(): void
    {
        $event = $this->eventWithMixedRsvps();
        $abena = EventRsvp::where('event_id', $event->id)->where('status', 'Confirmed')->where('guests_count', 3)->sole();

        $this->actingAs($this->staff)
            ->from(route('events.show', $event))
            ->delete(route('events.cancel-rsvp', [$event, $abena]))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHas('success', 'RSVP cancelled.');

        $this->assertSame('Cancelled', $abena->fresh()?->status);
        $this->assertSame(5, EventRsvp::where('event_id', $event->id)->count());

        $index = $this->get(route('events.index'))->assertOk();
        $this->assertSame(3, (int) $index->viewData('events')->firstWhere('id', $event->id)->confirmed_guests);
        $this->get(route('events.show', $event))->assertOk()->assertSee('RSVP List (2 confirmed)');
    }

    public function test_after_a_web_cancel_re_adding_the_member_reuses_the_same_row(): void
    {
        $event = $this->event();
        $member = $this->member('Kwame');
        $row = $this->rsvp($event, $member, 2);

        $this->actingAs($this->staff)->delete(route('events.cancel-rsvp', [$event, $row]));
        $this->addRsvp($event, ['member_id' => $member->id, 'guests_count' => 1])->assertSessionHas('success');

        $rows = EventRsvp::where('event_id', $event->id)->where('member_id', $member->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($row->id, $rows->first()->id);
        $this->assertSame('Confirmed', $rows->first()->status);
    }

    // ── permissions (unchanged) ────────────────────────────────────────

    public function test_permission_guards_are_unchanged(): void
    {
        $event = $this->event();
        $rsvp = $this->rsvp($event, $this->member('Abena'), 1);

        $this->actingAs($this->noEvents);
        $this->get(route('events.index'))->assertForbidden();
        $this->get(route('events.show', $event))->assertForbidden();
        $this->post(route('events.rsvp', $event), ['guests_count' => 1])->assertForbidden();
        $this->delete(route('events.cancel-rsvp', [$event, $rsvp]))->assertForbidden();
        $this->assertSame(1, EventRsvp::count());

        $this->actingAs($this->staff);
        $this->get(route('events.index'))->assertOk();
        $this->get(route('events.show', $event))->assertOk();
    }
}
