@extends('layouts.app')

@section('title', 'Events & Programs')

@section('content')

<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-calendar-alt" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-label">Total Events</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-calendar-check" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['upcoming'] }}</div>
            <div class="stat-label">Upcoming</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-spinner" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['ongoing'] }}</div>
            <div class="stat-label">Ongoing</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-check-double" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['completed'] }}</div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-calendar-alt" style="color:#2563eb; margin-right:8px;"></i>Events & Programs</div>
        <a href="{{ route('events.create') }}" class="btn-primary">
            <i class="fas fa-plus"></i> New Event
        </a>
    </div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <form method="GET" action="{{ route('events.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <select name="type" class="form-control" style="width:180px;">
                <option value="">All Types</option>
                @foreach(['Convention','Crusade','Wedding','Funeral','Dedication','Anniversary','Conference','Outreach','Youth Program','Children Program','Special Service','Other'] as $type)
                    <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control" style="width:150px;">
                <option value="">All Statuses</option>
                @foreach(['Draft','Published','Ongoing','Completed','Cancelled'] as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filter</button>
            @if(request()->hasAny(['type','status']))
                <a href="{{ route('events.index') }}" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Venue</th>
                    <th>RSVPs</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                <tr>
                    <td>
                        <div style="font-weight:600; color:#1e293b;">{{ $event->title }}</div>
                        @if($event->is_free)
                            <span class="badge badge-success" style="font-size:11px;">Free</span>
                        @else
                            <span class="badge badge-info" style="font-size:11px;">GHS {{ number_format($event->ticket_price, 2) }}</span>
                        @endif
                    </td>
                    <td><span class="badge badge-gray">{{ $event->type }}</span></td>
                    <td style="font-size:13px; color:#64748b;">{{ $event->duration }}</td>
                    <td style="font-size:13px; color:#64748b;">{{ $event->venue ?? '—' }}</td>
                    <td>
                        <span style="font-weight:700; color:#1e293b;">{{ $event->rsvps_count }}</span>
                        @if($event->capacity)
                            <span style="font-size:12px; color:#94a3b8;"> / {{ $event->capacity }}</span>
                        @endif
                    </td>
                    <td>
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
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('events.show', $event) }}" class="btn-outline btn-sm"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('events.edit', $event) }}" class="btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Delete this event?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-calendar-alt" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No events yet</div>
                        <a href="{{ route('events.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Create First Event</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($events->hasPages())
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9;">
        {{ $events->links() }}
    </div>
    @endif
</div>

@endsection