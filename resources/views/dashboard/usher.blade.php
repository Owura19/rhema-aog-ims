@extends('layouts.app')

@section('title', 'Usher Dashboard')

@section('content')

<div style="margin-bottom:24px;">
    <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Welcome, {{ auth()->user()->name }}</h2>
    <div style="font-size:13px; color:#64748b;">Usher · {{ now()->format('l, F d, Y') }}</div>
</div>

<!-- Stats -->
<div class="grid-3" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-church" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['today_services'] }}</div>
            <div class="stat-label">Services Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-users" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total_members'] }}</div>
            <div class="stat-label">Active Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-clipboard-check" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['marked_today'] }}</div>
            <div class="stat-label">Marked Today</div>
        </div>
    </div>
</div>

<div class="grid-main">

    <!-- Today's Services -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-church" style="color:#2563eb; margin-right:8px;"></i>Today's Services</div>
        </div>

        @if($todayServices->isNotEmpty())
            @foreach($todayServices as $service)
            <div style="padding:20px 24px; border-bottom:1px solid #f1f5f9;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                    <div>
                        <div style="font-size:16px; font-weight:700; color:#1e293b;">{{ $service->name }}</div>
                        <div style="font-size:13px; color:#64748b;">
                            {{ \Carbon\Carbon::parse($service->start_time)->format('g:i A') }}
                            @if($service->venue) · {{ $service->venue }} @endif
                        </div>
                    </div>
                    @if($service->status === 'Ongoing')
                        <span class="badge badge-info" style="font-size:13px; padding:6px 14px;">Ongoing</span>
                    @else
                        <span class="badge badge-warning" style="font-size:13px; padding:6px 14px;">Scheduled</span>
                    @endif
                </div>

                <a href="{{ route('services.show', $service) }}" class="btn-primary" style="width:100%; justify-content:center;">
                    <i class="fas fa-clipboard-list"></i> Mark Attendance
                </a>
            </div>
            @endforeach
        @else
            <div style="text-align:center; padding:60px; color:#94a3b8;">
                <i class="fas fa-church" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No services today</div>
                <div style="font-size:13px;">Check upcoming services below.</div>
            </div>
        @endif
    </div>

    <!-- Right Column -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-bolt" style="color:#e8a020; margin-right:8px;"></i>Quick Actions</div>
            </div>
            <div class="card-body" style="display:flex; flex-direction:column; gap:10px;">
                <a href="{{ route('services.index') }}" class="btn-primary"><i class="fas fa-church"></i> All Services</a>
                <a href="{{ route('attendance.index') }}" class="btn-outline"><i class="fas fa-clipboard-list"></i> Attendance Logs</a>
                <a href="{{ route('members.index') }}" class="btn-outline"><i class="fas fa-users"></i> Members List</a>
            </div>
        </div>

        <!-- Upcoming Services -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-calendar" style="color:#7c3aed; margin-right:8px;"></i>Upcoming Services</div>
            </div>
            <div style="padding:0;">
                @forelse($recentServices as $service)
                <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#1e293b;">{{ $service->name }}</div>
                            <div style="font-size:12px; color:#64748b;">
                                {{ $service->service_date->format('M d') }}
                                @if($service->start_time)
                                    · {{ \Carbon\Carbon::parse($service->start_time)->format('g:i A') }}
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('services.show', $service) }}" class="btn-outline btn-sm">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                @empty
                <div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">No upcoming services.</div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@endsection