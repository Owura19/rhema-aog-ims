@extends('layouts.app')

@section('title', $event->title)

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <a href="{{ route('events.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Events
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $event->title }}</h2>
        <div style="font-size:13px; color:#64748b;">{{ $event->type }} · {{ $event->duration }}</div>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="{{ route('events.edit', $event) }}" class="btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
        <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Delete this event?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </form>
    </div>
</div>

<!-- Banner -->
@if($event->banner_image)
<div style="margin-bottom:20px;">
    <img src="{{ asset('storage/'.$event->banner_image) }}" style="width:100%; max-height:300px; object-fit:cover; border-radius:12px;">
</div>
@endif

<!-- Stats -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;"><i class="fas fa-users" style="color:#2563eb;"></i></div>
        <div>
            <div class="stat-value">{{ $event->rsvp_count }}</div>
            <div class="stat-label">RSVPs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;"><i class="fas fa-chair" style="color:#16a34a;"></i></div>
        <div>
            <div class="stat-value">{{ $event->capacity ?? '∞' }}</div>
            <div class="stat-label">Capacity</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;"><i class="fas fa-door-open" style="color:#7c3aed;"></i></div>
        <div>
            <div class="stat-value">{{ $event->spots_left ?? '∞' }}</div>
            <div class="stat-label">Spots Left</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:{{ $event->is_free ? '#dcfce7' : '#fef9c3' }};"><i class="fas fa-ticket-alt" style="color:{{ $event->is_free ? '#16a34a' : '#ca8a04' }};"></i></div>
        <div>
            <div class="stat-value" style="font-size:18px;">{{ $event->is_free ? 'Free' : 'GHS '.number_format($event->ticket_price, 2) }}</div>
            <div class="stat-label">Admission</div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">

    <!-- RSVPs List -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list" style="color:#2563eb; margin-right:8px;"></i>RSVP List ({{ $rsvps->count() }})</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Attendee</th>
                        <th>Guests</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rsvps as $rsvp)
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="member-avatar-placeholder" style="width:32px; height:32px; font-size:12px;">
                                    {{ strtoupper(substr($rsvp->attendee_name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600; font-size:13px;">{{ $rsvp->attendee_name }}</div>
                                    @if($rsvp->member)
                                        <div style="font-size:11px; color:#94a3b8;">{{ $rsvp->member->member_id }}</div>
                                    @elseif($rsvp->guest_phone)
                                        <div style="font-size:11px; color:#94a3b8;">{{ $rsvp->guest_phone }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="font-weight:700; color:#1e293b;">{{ $rsvp->guests_count }}</td>
                        <td>
                            @if($rsvp->status === 'Confirmed')
                                <span class="badge badge-success">Confirmed</span>
                            @elseif($rsvp->status === 'Pending')
                                <span class="badge badge-warning">Pending</span>
                            @else
                                <span class="badge badge-danger">Cancelled</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('events.cancel-rsvp', [$event, $rsvp]) }}" onsubmit="return confirm('Cancel this RSVP?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm"><i class="fas fa-times"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center; padding:40px; color:#94a3b8;">
                            No RSVPs yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Panel -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Add RSVP -->
        @if(!$event->is_full)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-plus" style="color:#16a34a; margin-right:8px;"></i>Add RSVP</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('events.rsvp', $event) }}">
                    @csrf
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Member</label>
                        <select name="member_id" class="form-control">
                            <option value="">Guest (not a member)</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Guest Name (if not a member)</label>
                        <input type="text" name="guest_name" class="form-control" placeholder="Full name">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Phone</label>
                        <input type="text" name="guest_phone" class="form-control" placeholder="Phone number">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Number of Guests</label>
                        <input type="number" name="guests_count" value="1" min="1" class="form-control">
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fas fa-check"></i> Confirm RSVP
                    </button>
                </form>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body" style="text-align:center; padding:30px;">
                <i class="fas fa-ban" style="font-size:32px; color:#dc2626; margin-bottom:12px; display:block;"></i>
                <div style="font-weight:700; color:#dc2626;">Event is Full</div>
                <div style="font-size:13px; color:#94a3b8; margin-top:4px;">No more spots available</div>
            </div>
        </div>
        @endif

        <!-- Event Info -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-info-circle" style="color:#7c3aed; margin-right:8px;"></i>Event Info</div>
            </div>
            <div class="card-body" style="padding:0;">
                <table style="width:100%;">
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Status</td>
                        <td style="padding:10px 20px;">
                            @if($event->status === 'Published')
                                <span class="badge badge-success">Published</span>
                            @elseif($event->status === 'Draft')
                                <span class="badge badge-gray">Draft</span>
                            @elseif($event->status === 'Ongoing')
                                <span class="badge badge-info">Ongoing</span>
                            @elseif($event->status === 'Completed')
                                <span class="badge badge-warning">Completed</span>
                            @else
                                <span class="badge badge-danger">Cancelled</span>
                            @endif
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Venue</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $event->venue ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Address</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $event->address ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Time</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">
                            {{ $event->start_time ? \Carbon\Carbon::parse($event->start_time)->format('g:i A') : '—' }}
                            @if($event->end_time)
                                — {{ \Carbon\Carbon::parse($event->end_time)->format('g:i A') }}
                            @endif
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">RSVP Deadline</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $event->rsvp_deadline?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Created By</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $event->createdBy?->name ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if($event->description)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-align-left" style="color:#e8a020; margin-right:8px;"></i>Description</div>
            </div>
            <div class="card-body">
                <p style="font-size:14px; color:#374151; line-height:1.8; margin:0;">{{ $event->description }}</p>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection