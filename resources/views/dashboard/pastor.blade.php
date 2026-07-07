@extends('layouts.app')

@section('title', 'Pastor Dashboard')

@section('content')

<div style="margin-bottom:24px;">
    <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Welcome, {{ auth()->user()->name }}</h2>
    <div style="font-size:13px; color:#64748b;">{{ now()->format('l, F d, Y') }}</div>
</div>

<!-- Stats -->
<div class="grid-3" style="margin-bottom:28px;">
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
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-arrow-up" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($stats['income_month'], 2) }}</div>
            <div class="stat-label">Income This Month</div>
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
            <i class="fas fa-calendar-alt" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['upcoming_events'] }}</div>
            <div class="stat-label">Upcoming Events</div>
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
</div>

<div class="grid-main">

    <!-- Recent Services -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-church" style="color:#7c3aed; margin-right:8px;"></i>Recent Services</div>
            <a href="{{ route('services.index') }}" class="btn-outline btn-sm">View All</a>
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
                        <td><span style="font-weight:700;">{{ $service->attendance_logs_count }}</span></td>
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
                        <td colspan="4" style="text-align:center; color:#94a3b8; padding:30px;">No services yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- This Month Attendance -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-bar" style="color:#2563eb; margin-right:8px;"></i>This Month</div>
            </div>
            <div class="card-body">
                @if($monthlyAttendance->isNotEmpty())
                    @foreach($monthlyAttendance as $service)
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <span style="font-size:13px; font-weight:600; color:#1e293b;">{{ Str::limit($service->name, 20) }}</span>
                            <span style="font-size:13px; color:#64748b;">{{ $service->attendance_logs_count }}</span>
                        </div>
                        <div style="background:#f1f5f9; border-radius:20px; height:6px; overflow:hidden;">
                            <div style="width:{{ $stats['active_members'] > 0 ? min(($service->attendance_logs_count / $stats['active_members']) * 100, 100) : 0 }}%; height:100%; background:#2563eb; border-radius:20px;"></div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div style="text-align:center; color:#94a3b8; font-size:13px; padding:20px 0;">No completed services this month.</div>
                @endif
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-calendar-alt" style="color:#7c3aed; margin-right:8px;"></i>Upcoming Events</div>
                <a href="{{ route('events.index') }}" class="btn-outline btn-sm">View All</a>
            </div>
            <div style="padding:0;">
                @forelse($upcomingEvents as $event)
                <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:13px; font-weight:600; color:#1e293b;">{{ $event->title }}</div>
                    <div style="font-size:12px; color:#64748b;">{{ $event->start_date->format('M d, Y') }}</div>
                    <div style="font-size:12px; color:#94a3b8;">{{ $event->venue ?? 'Venue TBD' }}</div>
                </div>
                @empty
                <div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">No upcoming events.</div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@endsection