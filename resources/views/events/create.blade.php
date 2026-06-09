@extends('layouts.app')

@section('title', 'Create Event')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('events.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Events
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Create New Event</h2>
</div>

<form method="POST" action="{{ route('events.store') }}" enctype="multipart/form-data">
@csrf

<!-- Basic Info -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-calendar-alt" style="color:#2563eb; margin-right:8px;"></i>Event Details</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div style="grid-column:span 2;">
                <label class="form-label">Event Title <span style="color:red;">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}" placeholder="e.g. GraceWorld Annual Convention 2026">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Event Type <span style="color:red;">*</span></label>
                <select name="type" class="form-control {{ $errors->has('type') ? 'is-invalid' : '' }}">
                    @foreach(['Convention','Crusade','Wedding','Funeral','Dedication','Anniversary','Conference','Outreach','Youth Program','Children Program','Special Service','Other'] as $type)
                        <option value="{{ $type }}" {{ old('type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Start Date <span style="color:red;">*</span></label>
                <input type="date" name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" class="form-control {{ $errors->has('start_date') ? 'is-invalid' : '' }}">
                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Status <span style="color:red;">*</span></label>
                <select name="status" class="form-control">
                    @foreach(['Draft','Published','Ongoing','Completed','Cancelled'] as $status)
                        <option value="{{ $status }}" {{ old('status', 'Draft') == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Start Time</label>
                <input type="time" name="start_time" value="{{ old('start_time') }}" class="form-control">
            </div>

            <div>
                <label class="form-label">End Time</label>
                <input type="time" name="end_time" value="{{ old('end_time') }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Banner Image</label>
                <input type="file" name="banner_image" class="form-control" accept="image/*">
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Event description, theme, speakers, agenda...">{{ old('description') }}</textarea>
            </div>

        </div>
    </div>
</div>

<!-- Venue -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-map-marker-alt" style="color:#dc2626; margin-right:8px;"></i>Venue & Location</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">
            <div>
                <label class="form-label">Venue Name</label>
                <input type="text" name="venue" value="{{ old('venue') }}" class="form-control" placeholder="e.g. GraceWorld Auditorium">
            </div>
            <div>
                <label class="form-label">Address</label>
                <input type="text" name="address" value="{{ old('address') }}" class="form-control" placeholder="Full address">
            </div>
        </div>
    </div>
</div>

<!-- RSVP & Tickets -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-ticket-alt" style="color:#7c3aed; margin-right:8px;"></i>RSVP & Tickets</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
                <label class="form-label">Capacity</label>
                <input type="number" name="capacity" value="{{ old('capacity') }}" class="form-control" placeholder="Leave blank for unlimited">
            </div>

            <div>
                <label class="form-label">RSVP Deadline</label>
                <input type="date" name="rsvp_deadline" value="{{ old('rsvp_deadline') }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Ticket Price (GHS)</label>
                <input type="number" name="ticket_price" value="{{ old('ticket_price', 0) }}" step="0.01" min="0" class="form-control">
            </div>

            <div style="grid-column:span 3; display:flex; gap:30px;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="is_free" value="1" {{ old('is_free', true) ? 'checked' : '' }} style="width:18px; height:18px;">
                    <span style="font-size:14px; font-weight:600; color:#374151;">Free Event</span>
                </label>
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="rsvp_required" value="1" {{ old('rsvp_required') ? 'checked' : '' }} style="width:18px; height:18px;">
                    <span style="font-size:14px; font-weight:600; color:#374151;">RSVP Required</span>
                </label>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Create Event
    </button>
    <a href="{{ route('events.index') }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection