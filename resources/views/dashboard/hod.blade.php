@extends('layouts.app')

@section('title', 'HOD Dashboard')

@section('content')

<div style="margin-bottom:24px;">
    <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Welcome, {{ auth()->user()->name }}</h2>
    <div style="font-size:13px; color:#64748b;">Head of Department · {{ now()->format('l, F d, Y') }}</div>
</div>

<!-- Stats -->
<div class="grid-4" style="margin-bottom:28px;">
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
            <i class="fas fa-building" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['my_groups'] }}</div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-church" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['services_month'] }}</div>
            <div class="stat-label">Services This Month</div>
        </div>
    </div>
</div>

<div class="grid-main">

    <!-- My Groups -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-building" style="color:#7c3aed; margin-right:8px;"></i>Departments & Groups</div>
            <a href="{{ route('cellgroups.index') }}" class="btn-outline btn-sm">View All</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Type</th>
                        <th>Leader</th>
                        <th>Members</th>
                        <th>Meeting</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($myGroups as $group)
                    <tr>
                        <td>
                            <a href="{{ route('cellgroups.show', $group) }}" style="font-weight:600; color:#2563eb; text-decoration:none;">{{ $group->name }}</a>
                            <div style="font-size:12px; color:#94a3b8;">{{ $group->code }}</div>
                        </td>
                        <td><span class="badge badge-info">{{ $group->type }}</span></td>
                        <td style="font-size:13px; color:#64748b;">{{ $group->leader?->full_name ?? '—' }}</td>
                        <td><span style="font-weight:700;">{{ $group->cell_group_members_count ?? 0 }}</span></td>
                        <td style="font-size:13px; color:#64748b;">{{ $group->meeting_day ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align:center; color:#94a3b8; padding:30px;">No groups yet.</td>
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
                <a href="{{ route('members.index') }}" class="btn-primary"><i class="fas fa-users"></i> View Members</a>
                <a href="{{ route('cellgroups.create') }}" class="btn-primary" style="background:#7c3aed;"><i class="fas fa-plus"></i> New Group</a>
                <a href="{{ route('services.index') }}" class="btn-outline"><i class="fas fa-church"></i> Church Services</a>
                <a href="{{ route('attendance.index') }}" class="btn-outline"><i class="fas fa-clipboard-list"></i> Attendance Logs</a>
            </div>
        </div>

        <!-- Recent Services -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-church" style="color:#2563eb; margin-right:8px;"></i>Recent Services</div>
            </div>
            <div style="padding:0;">
                @forelse($recentServices as $service)
                <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:13px; font-weight:600; color:#1e293b;">{{ $service->name }}</div>
                    <div style="font-size:12px; color:#64748b;">{{ $service->service_date->format('M d, Y') }} · {{ $service->attendance_logs_count }} present</div>
                </div>
                @empty
                <div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">No services yet.</div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@endsection