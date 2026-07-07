@extends('layouts.app')

@section('title', 'Data Entry Dashboard')

@section('content')

<div style="margin-bottom:24px;">
    <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Welcome, {{ auth()->user()->name }}</h2>
    <div style="font-size:13px; color:#64748b;">Data Entry · {{ now()->format('l, F d, Y') }}</div>
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
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-user-clock" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['visitors'] }}</div>
            <div class="stat-label">Visitors</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-user-plus" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['added_today'] }}</div>
            <div class="stat-label">Added Today</div>
        </div>
    </div>
</div>

<div class="grid-main">

    <!-- Recent Members -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-users" style="color:#2563eb; margin-right:8px;"></i>Recent Members</div>
            <a href="{{ route('members.create') }}" class="btn-primary btn-sm"><i class="fas fa-plus"></i> Add Member</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>ID</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentMembers as $member)
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="member-avatar-placeholder" style="width:32px; height:32px; font-size:12px;">{{ strtoupper(substr($member->first_name,0,1)) }}</div>
                                <div>
                                    <div style="font-size:13px; font-weight:600;">{{ $member->full_name }}</div>
                                    <div style="font-size:11px; color:#94a3b8;">{{ $member->email ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span style="font-family:monospace; font-size:12px; background:#f1f5f9; padding:2px 6px; border-radius:4px;">{{ $member->member_id }}</span></td>
                        <td style="font-size:13px; color:#64748b;">{{ $member->phone ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $member->membership_status === 'Active' ? 'badge-success' : ($member->membership_status === 'Visitor' ? 'badge-warning' : 'badge-gray') }}">
                                {{ $member->membership_status }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <a href="{{ route('members.show', $member) }}" class="btn-outline btn-sm"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('members.edit', $member) }}" class="btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align:center; color:#94a3b8; padding:30px;">No members yet.</td>
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
                <a href="{{ route('members.create') }}" class="btn-primary"><i class="fas fa-user-plus"></i> Add New Member</a>
                <a href="{{ route('members.index') }}" class="btn-outline"><i class="fas fa-users"></i> View All Members</a>
                <a href="{{ route('attendance.index') }}" class="btn-outline"><i class="fas fa-clipboard-list"></i> Attendance Logs</a>
            </div>
        </div>

        <!-- Tips -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-lightbulb" style="color:#e8a020; margin-right:8px;"></i>Tips</div>
            </div>
            <div class="card-body">
                <div style="font-size:13px; color:#374151; line-height:2;">
                    <div><i class="fas fa-check-circle" style="color:#16a34a; margin-right:6px;"></i> Always verify member details before saving</div>
                    <div><i class="fas fa-check-circle" style="color:#16a34a; margin-right:6px;"></i> Add phone number for easy contact</div>
                    <div><i class="fas fa-check-circle" style="color:#16a34a; margin-right:6px;"></i> Set correct membership status</div>
                    <div><i class="fas fa-check-circle" style="color:#16a34a; margin-right:6px;"></i> Link members to their family</div>
                    <div><i class="fas fa-check-circle" style="color:#16a34a; margin-right:6px;"></i> Upload member photo if available</div>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection