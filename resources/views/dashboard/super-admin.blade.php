@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<!-- Stats Row 1 -->
<div class="grid-4" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-users" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total_members'] }}</div>
            <div class="stat-label">Total Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-user-check" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['active_members'] }}</div>
            <div class="stat-label">Active Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-church" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['services_month'] }}</div>
            <div class="stat-label">Services This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-fingerprint" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['active_devices'] }}</div>
            <div class="stat-label">Active Devices</div>
        </div>
    </div>
</div>

<!-- Stats Row 2 -->
<div class="grid-4" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-arrow-up" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($stats['income_month'], 2) }}</div>
            <div class="stat-label">Income This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">
            <i class="fas fa-arrow-down" style="color:#dc2626;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($stats['expense_month'], 2) }}</div>
            <div class="stat-label">Expenses This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-home" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total_groups'] }}</div>
            <div class="stat-label">Active Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-calendar-alt" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['upcoming_events'] }}</div>
            <div class="stat-label">Upcoming Events</div>
        </div>
    </div>
</div>

<div class="grid-main">

    <!-- Recent Services -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-church" style="color:#7c3aed; margin-right:8px;"></i>Recent Services</div>
            <a href="{{ route('services.create') }}" class="btn-primary btn-sm"><i class="fas fa-plus"></i> New Service</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Attendance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentServices as $service)
                    <tr>
                        <td>
                            <a href="{{ route('services.show', $service) }}" style="font-weight:600; color:#2563eb; text-decoration:none;">{{ $service->name }}</a>
                            <div style="font-size:12px; color:#94a3b8;">{{ $service->service_type }}</div>
                        </td>
                        <td style="font-size:13px; color:#64748b;">{{ $service->service_date->format('M d, Y') }}</td>
                        <td><span style="font-weight:700;">{{ $service->attendance_logs_count }}</span> <span style="font-size:12px; color:#94a3b8;">present</span></td>
                        <td>
                            @if($service->status === 'Completed')
                                <span class="badge badge-success">Completed</span>
                            @elseif($service->status === 'Ongoing')
                                <span class="badge badge-info">Ongoing</span>
                            @elseif($service->status === 'Scheduled')
                                <span class="badge badge-warning">Scheduled</span>
                            @else
                                <span class="badge badge-danger">Cancelled</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center; color:#94a3b8; padding:30px;">
                            No services yet. <a href="{{ route('services.create') }}" style="color:#2563eb;">Create first service</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-bolt" style="color:#e8a020; margin-right:8px;"></i>Quick Actions</div>
            </div>
            <div class="card-body" style="display:flex; flex-direction:column; gap:10px;">
                <a href="{{ route('members.create') }}" class="btn-primary"><i class="fas fa-user-plus"></i> Add Member</a>
                <a href="{{ route('services.create') }}" class="btn-primary" style="background:#7c3aed;"><i class="fas fa-church"></i> Create Service</a>
                <a href="{{ route('finance.create') }}" class="btn-primary" style="background:#16a34a;"><i class="fas fa-money-bill-wave"></i> Record Transaction</a>
                <a href="{{ route('events.create') }}" class="btn-primary" style="background:#2563eb;"><i class="fas fa-calendar-alt"></i> Create Event</a>
            </div>
        </div>

        <!-- Upcoming Events -->
        @if($upcomingEvents->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-calendar-alt" style="color:#2563eb; margin-right:8px;"></i>Upcoming Events</div>
            </div>
            <div style="padding:0;">
                @foreach($upcomingEvents as $event)
                <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:13px; font-weight:600; color:#1e293b;">{{ $event->title }}</div>
                    <div style="font-size:12px; color:#64748b;">{{ $event->start_date->format('M d, Y') }} · {{ $event->venue ?? 'Venue TBD' }}</div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Recent Members -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-users" style="color:#2563eb; margin-right:8px;"></i>Recent Members</div>
            </div>
            <div style="padding:0;">
                @forelse($recentMembers as $member)
                <div style="display:flex; align-items:center; gap:10px; padding:10px 20px; border-bottom:1px solid #f1f5f9;">
                    <div class="member-avatar-placeholder" style="width:32px; height:32px; font-size:12px;">{{ strtoupper(substr($member->first_name,0,1)) }}</div>
                    <div style="flex:1;">
                        <div style="font-size:13px; font-weight:600; color:#1e293b;">{{ $member->full_name }}</div>
                        <div style="font-size:11px; color:#94a3b8;">{{ $member->member_id }}</div>
                    </div>
                    <span class="badge {{ $member->membership_status === 'Active' ? 'badge-success' : 'badge-gray' }}" style="font-size:11px;">{{ $member->membership_status }}</span>
                </div>
                @empty
                <div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">No members yet</div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@endsection