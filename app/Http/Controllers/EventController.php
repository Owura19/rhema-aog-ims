<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        // RSVPs column = people attending: guests of Confirmed RSVPs only (as the app's spots_left).
        $query = Event::withSum(['rsvps as confirmed_guests' => fn ($q) => $q->where('status', 'Confirmed')], 'guests_count')
            ->latest('start_date');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $events = $query->paginate(20)->withQueryString();

        $stats = [
            'total'     => Event::count(),
            'upcoming'  => Event::whereIn('status', ['Published', 'Draft'])->where('start_date', '>=', now())->count(),
            'ongoing'   => Event::where('status', 'Ongoing')->count(),
            'completed' => Event::where('status', 'Completed')->count(),
        ];

        return view('events.index', compact('events', 'stats'));
    }

    public function create()
    {
        return view('events.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'type'           => 'required|string',
            'description'    => 'nullable|string',
            'start_date'     => 'required|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'start_time'     => 'nullable',
            'end_time'       => 'nullable',
            'venue'          => 'nullable|string|max:255',
            'address'        => 'nullable|string|max:255',
            'capacity'       => 'nullable|integer|min:1',
            'ticket_price'   => 'nullable|numeric|min:0',
            'is_free'        => 'boolean',
            'rsvp_required'  => 'boolean',
            'rsvp_deadline'  => 'nullable|date',
            'status'         => 'required|in:Draft,Published,Ongoing,Completed,Cancelled',
            'banner_image'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $validated['is_free']       = $request->has('is_free');
        $validated['rsvp_required'] = $request->has('rsvp_required');
        $validated['created_by']    = auth()->id();

        if ($request->hasFile('banner_image')) {
            $validated['banner_image'] = $request->file('banner_image')->store('events/banners', 'public');
        }

        $event = Event::create($validated);

        return redirect()->route('events.show', $event)
            ->with('success', "Event '{$event->title}' created successfully!");
    }

    public function show(Event $event)
    {
        $event->load('createdBy');

        $rsvps = EventRsvp::where('event_id', $event->id)
            ->with('member')
            ->latest()
            ->get();

        // Members who can be added: anyone without a live (Confirmed or Pending) RSVP.
        // A member whose RSVP was cancelled can be added back — rsvp() reactivates their row.
        $members = Member::where('membership_status', 'Active')
            ->whereNotIn('id', $rsvps->where('status', '!=', 'Cancelled')->whereNotNull('member_id')->pluck('member_id'))
            ->orderBy('first_name')
            ->get();

        $confirmedRsvps = $rsvps->where('status', 'Confirmed')->count();

        return view('events.show', compact('event', 'rsvps', 'members', 'confirmedRsvps'));
    }

    public function edit(Event $event)
    {
        return view('events.edit', compact('event'));
    }

    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'type'           => 'required|string',
            'description'    => 'nullable|string',
            'start_date'     => 'required|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'start_time'     => 'nullable',
            'end_time'       => 'nullable',
            'venue'          => 'nullable|string|max:255',
            'address'        => 'nullable|string|max:255',
            'capacity'       => 'nullable|integer|min:1',
            'ticket_price'   => 'nullable|numeric|min:0',
            'is_free'        => 'boolean',
            'rsvp_required'  => 'boolean',
            'rsvp_deadline'  => 'nullable|date',
            'status'         => 'required|in:Draft,Published,Ongoing,Completed,Cancelled',
            'banner_image'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $validated['is_free']       = $request->has('is_free');
        $validated['rsvp_required'] = $request->has('rsvp_required');

        if ($request->hasFile('banner_image')) {
            $validated['banner_image'] = $request->file('banner_image')->store('events/banners', 'public');
        }

        $event->update($validated);

        return redirect()->route('events.show', $event)
            ->with('success', "Event updated successfully!");
    }

    public function destroy(Event $event)
    {
        $title = $event->title;
        $event->delete();
        return redirect()->route('events.index')
            ->with('success', "Event '{$title}' deleted.");
    }

    /**
     * Staff add (or update) an RSVP.
     *  - A member has ONE row per event (unique event_id + member_id): it is reused whatever its
     *    status and set to Confirmed, so a cancelled member can be re-added.
     *  - A guest (no member) always gets a new row.
     *  - Capacity counts Confirmed places only, minus the member's own confirmed places, and
     *    includes the requested party. The event row is locked while counting (as in the app API).
     */
    public function rsvp(Request $request, Event $event)
    {
        $validated = $request->validate([
            'member_id'    => 'nullable|exists:members,id',
            'guest_name'   => 'nullable|string|max:255',
            'guest_phone'  => 'nullable|string|max:20',
            'guests_count' => 'required|integer|min:1',
            'notes'        => 'nullable|string',
        ]);

        $memberId = $validated['member_id'] ?? null;
        $party    = (int) $validated['guests_count'];

        $refusal = DB::transaction(function () use ($event, $validated, $memberId, $party) {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            $existing = $memberId
                ? EventRsvp::where('event_id', $event->id)->where('member_id', $memberId)->first()
                : null;

            if ($locked->capacity) {
                $confirmed = (int) EventRsvp::where('event_id', $event->id)->where('status', 'Confirmed')->sum('guests_count');
                $others = $confirmed - ($existing?->status === 'Confirmed' ? $existing->guests_count : 0);
                $left = max(0, $locked->capacity - $others);

                if ($party > $left) {
                    return $left === 0
                        ? 'Sorry, this event is fully booked.'
                        : "Sorry, only {$left} " . ($left === 1 ? 'place is' : 'places are') . ' left for this event.';
                }
            }

            $values = [
                'guest_name'   => $validated['guest_name'] ?? null,
                'guest_phone'  => $validated['guest_phone'] ?? null,
                'guests_count' => $party,
                'notes'        => $validated['notes'] ?? null,
                'status'       => 'Confirmed',
            ];

            if ($existing) {
                $existing->update($values);
            } else {
                EventRsvp::create(['event_id' => $event->id, 'member_id' => $memberId] + $values);
            }

            return null;
        });

        if ($refusal !== null) {
            return back()->with('error', $refusal);
        }

        return back()->with('success', 'RSVP confirmed successfully!');
    }

    /**
     * Staff cancel an RSVP. The row is kept as Cancelled (history; the app reads the same
     * status), and the RSVP must belong to the event in the URL.
     */
    public function cancelRsvp(Event $event, EventRsvp $rsvp)
    {
        abort_unless((int) $rsvp->event_id === (int) $event->id, 404);

        if ($rsvp->status !== 'Cancelled') {
            $rsvp->update(['status' => 'Cancelled']);
        }

        return back()->with('success', 'RSVP cancelled.');
    }
}
