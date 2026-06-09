@extends('layouts.app')

@section('title', 'Cell Leader Dashboard')

@section('content')

<div style="margin-bottom:24px;">
    <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Welcome, {{ auth()->user()->name }}</h2>
    <div style="font-size:13px; color:#64748b;">Cell Leader · {{ now()->format('l, F d, Y') }}</div>
</div>

<!-- Stats -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-home" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['my_groups'] }}</div>
            <div class="stat-label">My Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-users" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total_members'] }}</div>
            <div class="stat-label">Group Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-church" style="color:#16a34a;"></i>
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
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">

    <!-- My Cell Groups -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        @forelse($myGroups as $group)
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title"><i class="fas fa-home" style="color:#7c3aed; margin-right:8px;"></i>{{ $group->name }}</div>
                    <div style="font-size:12px; color:#94a3b8; margin-top:2px;">{{ $group->code }} · {{ $group->type }}</div>
                </div>
                <a href="{{ route('cellgroups.show', $group) }}" class="btn-outline btn-sm">View Group</a>
            </div>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($group->members->take(5) as $member)
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="member-avatar-placeholder" style="width:32px; height:32px; font-size:12px;">{{ strtoupper(substr($member->first_name,0,1)) }}</div>
                                    <div>
                                        <div style="font-size:13px; font-weight:600;">{{ $member->full_name }}</div>
                                        <div style="font-size:11px; color:#94a3b8;">{{ $member->member_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($member->pivot->role === 'Leader')
                                    <span class="badge badge-warning">Leader</span>
                                @elseif($member->pivot->role === 'Assistant Leader')
                                    <span class="badge badge-info">Asst. Leader</span>
                                @else
                                    <span class="badge badge-gray">Member</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $member->membership_status === 'Active' ? 'badge-success' : 'badge-gray' }}">
                                    {{ $member->membership_status }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" style="text-align:center; color:#94a3b8; padding:20px;">No members yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($group->members->count() > 5)
            <div style="padding:12px 20px; border-top:1px solid #f1f5f9; text-align:center;">
                <a href="{{ route('cellgroups.show', $group) }}" style="font-size:13px; color:#2563eb;">View all {{ $group->members->count() }} members</a>
            </div>
            @endif
        </div>
        @empty
        <div class="card">
            <div style="text-align:center; padding:60px; color:#94a3b8;">
                <i class="fas fa-home" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No groups assigned</div>
                <div>Contact your administrator to be assigned to a cell group.</div>
            </div>
        </div>
        @endforelse
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
                <a href="{{ route('services.index') }}" class="btn-outline"><i class="fas fa-church"></i> Church Services</a>
                <a href="{{ route('attendance.index') }}" class="btn-outline"><i class="fas fa-clipboard-list"></i> Attendance</a>
                <a href="{{ route('events.index') }}" class="btn-outline"><i class="fas fa-calendar-alt"></i> Events</a>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-calendar-alt" style="color:#7c3aed; margin-right:8px;"></i>Upcoming Events</div>
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

        <!-- Meeting Info -->
        @if($myGroups->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-calendar-check" style="color:#16a34a; margin-right:8px;"></i>Meeting Schedule</div>
            </div>
            <div style="padding:0;">
                @foreach($myGroups as $group)
                <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:13px; font-weight:600; color:#1e293b;">{{ $group->name }}</div>
                    <div style="font-size:12px; color:#64748b;">
                        {{ $group->meeting_day ?? 'Day TBD' }}
                        @if($group->meeting_time)
                            · {{ \Carbon\Carbon::parse($group->meeting_time)->format('g:i A') }}
                        @endif
                    </div>
                    <div style="font-size:12px; color:#94a3b8;">{{ $group->meeting_venue ?? 'Venue TBD' }}</div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>

@endsection