@extends('layouts.app')

@section('title', 'Cell Groups & Departments')

@section('content')

<div class="grid-4" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-users" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-label">Total Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-check-circle" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['active'] }}</div>
            <div class="stat-label">Active</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-home" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['cell_groups'] }}</div>
            <div class="stat-label">Cell Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-building" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['departments'] }}</div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users" style="color:#2563eb; margin-right:8px;"></i>Cell Groups & Departments</div>
        <a href="{{ route('cellgroups.create') }}" class="btn-primary">
            <i class="fas fa-plus"></i> New Group
        </a>
    </div>

    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Leader</th>
                    <th>Members</th>
                    <th>Meeting</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $group)
                <tr>
                    <td>
                        <div style="font-weight:600; color:#1e293b;">{{ $group->name }}</div>
                        @if($group->description)
                            <div style="font-size:12px; color:#94a3b8;">{{ Str::limit($group->description, 50) }}</div>
                        @endif
                    </td>
                    <td><span style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:3px 8px; border-radius:4px;">{{ $group->code }}</span></td>
                    <td>
                        @if($group->type === 'Cell Group')
                            <span class="badge badge-info">Cell Group</span>
                        @elseif($group->type === 'Department')
                            <span class="badge badge-warning">Department</span>
                        @elseif($group->type === 'Ministry')
                            <span class="badge badge-success">Ministry</span>
                        @else
                            <span class="badge badge-gray">Team</span>
                        @endif
                    </td>
                    <td style="font-size:13px;">{{ $group->leader?->full_name ?? '—' }}</td>
                    <td>
                        <span style="font-weight:700; color:#1e293b;">{{ $group->cell_group_members_count }}</span>
                        <span style="font-size:12px; color:#94a3b8;"> members</span>
                    </td>
                    <td style="font-size:13px; color:#64748b;">
                        @if($group->meeting_day)
                            {{ $group->meeting_day }}
                            @if($group->meeting_time)
                                · {{ \Carbon\Carbon::parse($group->meeting_time)->format('g:i A') }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($group->status === 'Active')
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-gray">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('cellgroups.show', $group) }}" class="btn-outline btn-sm"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('cellgroups.edit', $group) }}" class="btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="{{ route('cellgroups.destroy', $group) }}" onsubmit="return confirm('Delete this group?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-users" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No groups yet</div>
                        <a href="{{ route('cellgroups.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Create First Group</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($groups->hasPages())
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9;">
        {{ $groups->links() }}
    </div>
    @endif
</div>

@endsection