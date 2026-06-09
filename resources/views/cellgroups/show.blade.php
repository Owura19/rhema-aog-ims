@extends('layouts.app')

@section('title', $cellgroup->name)

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <a href="{{ route('cellgroups.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Groups
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $cellgroup->name }}</h2>
        <div style="font-size:13px; color:#64748b;">{{ $cellgroup->code }} · {{ $cellgroup->type }}</div>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="{{ route('cellgroups.edit', $cellgroup) }}" class="btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
        <form method="POST" action="{{ route('cellgroups.destroy', $cellgroup) }}" onsubmit="return confirm('Delete this group?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">

    <!-- Members List -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-users" style="color:#2563eb; margin-right:8px;"></i>Members ({{ $members->count() }})</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $cgMember)
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="member-avatar-placeholder">{{ strtoupper(substr($cgMember->member->first_name,0,1)) }}</div>
                                <div>
                                    <div style="font-weight:600;">{{ $cgMember->member->full_name }}</div>
                                    <div style="font-size:12px; color:#94a3b8;">{{ $cgMember->member->member_id }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($cgMember->role === 'Leader')
                                <span class="badge badge-warning">Leader</span>
                            @elseif($cgMember->role === 'Assistant Leader')
                                <span class="badge badge-info">Asst. Leader</span>
                            @else
                                <span class="badge badge-gray">Member</span>
                            @endif
                        </td>
                        <td style="font-size:13px; color:#64748b;">{{ $cgMember->joined_date?->format('M d, Y') ?? '—' }}</td>
                        <td>
                            @if($cgMember->status === 'Active')
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-gray">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('cellgroups.remove-member', [$cellgroup, $cgMember->member]) }}" onsubmit="return confirm('Remove this member?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm"><i class="fas fa-user-minus"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">
                            No members yet. Add members using the form on the right.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Panel -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Add Member -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-plus" style="color:#16a34a; margin-right:8px;"></i>Add Member</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('cellgroups.add-member', $cellgroup) }}">
                    @csrf
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Member</label>
                        <select name="member_id" class="form-control" required>
                            <option value="">Select member...</option>
                            @foreach($availableMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->full_name }} ({{ $member->member_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="Member">Member</option>
                            <option value="Assistant Leader">Assistant Leader</option>
                            <option value="Leader">Leader</option>
                            <option value="Secretary">Secretary</option>
                            <option value="Treasurer">Treasurer</option>
                        </select>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Joined Date</label>
                        <input type="date" name="joined_date" value="{{ now()->format('Y-m-d') }}" class="form-control">
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fas fa-user-plus"></i> Add to Group
                    </button>
                </form>
            </div>
        </div>

        <!-- Group Info -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-info-circle" style="color:#7c3aed; margin-right:8px;"></i>Group Info</div>
            </div>
            <div class="card-body" style="padding:0;">
                <table style="width:100%;">
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Type</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $cellgroup->type }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Leader</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $cellgroup->leader?->full_name ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Asst. Leader</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $cellgroup->assistantLeader?->full_name ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Meeting Day</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $cellgroup->meeting_day ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Meeting Time</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">
                            {{ $cellgroup->meeting_time ? \Carbon\Carbon::parse($cellgroup->meeting_time)->format('g:i A') : '—' }}
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Venue</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $cellgroup->meeting_venue ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Status</td>
                        <td style="padding:10px 20px;">
                            @if($cellgroup->status === 'Active')
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-gray">Inactive</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        @if($cellgroup->description)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-sticky-note" style="color:#e8a020; margin-right:8px;"></i>Description</div>
            </div>
            <div class="card-body">
                <p style="font-size:14px; color:#374151; line-height:1.8; margin:0;">{{ $cellgroup->description }}</p>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection