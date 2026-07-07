@extends('layouts.app')

@section('title', 'Members')

@section('content')

<!-- Stats Row -->
<div class="grid-4" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-users" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-label">Total Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-user-check" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['active'] }}</div>
            <div class="stat-label">Active</div>
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
        <div class="stat-icon" style="background:#fee2e2;">
            <i class="fas fa-user-minus" style="color:#dc2626;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['inactive'] }}</div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>
</div>

<!-- Members Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users" style="color:#2563eb; margin-right:8px;"></i>All Members</div>
        <a href="{{ route('members.create') }}" class="btn-primary">
            <i class="fas fa-user-plus"></i> Add Member
        </a>
    </div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <form method="GET" action="{{ route('members.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, ID, email, phone..." class="form-control" style="width:280px;">
            <select name="status" class="form-control" style="width:160px;">
                <option value="">All Statuses</option>
                @foreach(['Active','Inactive','Visitor','Transferred','Deceased'] as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            <select name="gender" class="form-control" style="width:130px;">
                <option value="">All Genders</option>
                <option value="Male" {{ request('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                <option value="Female" {{ request('gender') == 'Female' ? 'selected' : '' }}>Female</option>
            </select>
            <select name="member_type" class="form-control" style="width:160px;">
                <option value="">All Types</option>
                @foreach(['Full Member','Associate','Visitor'] as $type)
                    <option value="{{ $type }}" {{ request('member_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Search</button>
            @if(request()->hasAny(['search','status','gender','member_type']))
                <a href="{{ route('members.index') }}" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>ID</th>
                    <th>Contact</th>
                    <th>Gender</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $member)
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            @if($member->photo)
                                <img src="{{ asset('storage/'.$member->photo) }}" class="member-avatar">
                            @else
                                <div class="member-avatar-placeholder">{{ strtoupper(substr($member->first_name,0,1)) }}</div>
                            @endif
                            <div>
                                <div style="font-weight:600; color:#1e293b;">{{ $member->full_name }}</div>
                                <div style="font-size:12px; color:#94a3b8;">{{ $member->email ?? '—' }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:3px 8px; border-radius:4px;">{{ $member->member_id }}</span></td>
                    <td style="font-size:13px;">{{ $member->phone ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $member->gender === 'Male' ? 'badge-info' : 'badge-warning' }}">
                            {{ $member->gender }}
                        </span>
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $member->member_type }}</td>
                    <td>
                        @if($member->membership_status === 'Active')
                            <span class="badge badge-success">Active</span>
                        @elseif($member->membership_status === 'Visitor')
                            <span class="badge badge-warning">Visitor</span>
                        @elseif($member->membership_status === 'Inactive')
                            <span class="badge badge-danger">Inactive</span>
                        @elseif($member->membership_status === 'Transferred')
                            <span class="badge badge-info">Transferred</span>
                        @else
                            <span class="badge badge-gray">{{ $member->membership_status }}</span>
                        @endif
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $member->date_joined?->format('M d, Y') ?? '—' }}</td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('members.show', $member) }}" class="btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('members.edit', $member) }}" class="btn-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="return confirm('Are you sure you want to remove {{ $member->full_name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-users" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No members found</div>
                        <div style="margin-bottom:20px;">{{ request()->hasAny(['search','status','gender','member_type']) ? 'Try adjusting your search filters.' : 'Get started by adding your first member.' }}</div>
                        @if(!request()->hasAny(['search','status','gender','member_type']))
                            <a href="{{ route('members.create') }}" class="btn-primary"><i class="fas fa-user-plus"></i> Add First Member</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($members->hasPages())
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; color:#64748b;">
            Showing {{ $members->firstItem() }} to {{ $members->lastItem() }} of {{ $members->total() }} members
        </div>
        {{ $members->links() }}
    </div>
    @endif
</div>

@endsection