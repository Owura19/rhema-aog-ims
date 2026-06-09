<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Member;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::withCount('rsvps')->latest('start_date');

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

        $members = Member::where('membership_status', 'Active')
            ->whereNotIn('id', $rsvps->whereNotNull('member_id')->pluck('member_id'))
            ->orderBy('first_name')
            ->get();

        return view('events.show', compact('event', 'rsvps', 'members'));
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

    public function rsvp(Request $request, Event $event)
    {
        $validated = $request->validate([
            'member_id'    => 'nullable|exists:members,id',
            'guest_name'   => 'nullable|string|max:255',
            'guest_phone'  => 'nullable|string|max:20',
            'guests_count' => 'required|integer|min:1',
            'notes'        => 'nullable|string',
        ]);

        if ($event->is_full) {
            return back()->with('error', 'Sorry, this event is fully booked.');
        }

        EventRsvp::updateOrCreate(
            [
                'event_id'  => $event->id,
                'member_id' => $validated['member_id'] ?? null,
            ],
            [
                'guest_name'   => $validated['guest_name'] ?? null,
                'guest_phone'  => $validated['guest_phone'] ?? null,
                'guests_count' => $validated['guests_count'],
                'notes'        => $validated['notes'] ?? null,
                'status'       => 'Confirmed',
            ]
        );

        return back()->with('success', 'RSVP confirmed successfully!');
    }

    public function cancelRsvp(Event $event, EventRsvp $rsvp)
    {
        $rsvp->delete();
        return back()->with('success', 'RSVP cancelled.');
    }
}