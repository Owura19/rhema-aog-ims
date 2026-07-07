@extends('layouts.app')

@section('title', 'Visitors')

@section('content')

<div class="grid-5" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-user-friends" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-label">Total Visitors</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-calendar-check" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['this_month'] }}</div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-clock" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['pending'] }}</div>
            <div class="stat-label">Pending Follow-up</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-user-plus" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['first_time'] }}</div>
            <div class="stat-label">First Time (Month)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-church" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['joined'] }}</div>
            <div class="stat-label">Joined as Members</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user-friends" style="color:#2563eb; margin-right:8px;"></i>Visitors</div>
        <a href="{{ route('visitors.create') }}" class="btn-primary">
            <i class="fas fa-plus"></i> Record Visitor
        </a>
    </div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <form method="GET" action="{{ route('visitors.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, phone..." class="form-control" style="width:220px;">
            <select name="follow_up_status" class="form-control" style="width:170px;">
                <option value="">All Statuses</option>
                @foreach(['Pending','Called','Visited','Attended Again','Joined','No Response','Not Interested'] as $status)
                    <option value="{{ $status }}" {{ request('follow_up_status') == $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            <select name="visit_type" class="form-control" style="width:150px;">
                <option value="">All Types</option>
                @foreach(['First Time','Second Time','Third Time','Regular'] as $type)
                    <option value="{{ $type }}" {{ request('visit_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="width:150px;">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" style="width:150px;">
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filter</button>
            @if(request()->hasAny(['search','follow_up_status','visit_type','date_from','date_to']))
                <a href="{{ route('visitors.index') }}" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Phone</th>
                    <th>Visit Date</th>
                    <th>Type</th>
                    <th>How Heard</th>
                    <th>Follow-up</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($visitors as $visitor)
                <tr>
                    <td>
                        <div style="font-weight:600; color:#1e293b;">{{ $visitor->full_name }}</div>
                        @if($visitor->email)
                            <div style="font-size:12px; color:#94a3b8;">{{ $visitor->email }}</div>
                        @endif
                        @if($visitor->converted_to_member)
                            <span class="badge badge-success" style="font-size:10px;">Member</span>
                        @endif
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $visitor->phone ?? '—' }}</td>
                    <td style="font-size:13px; color:#64748b;">{{ $visitor->visit_date->format('M d, Y') }}</td>
                    <td>
                        @if($visitor->visit_type === 'First Time')
                            <span class="badge badge-info">First Time</span>
                        @elseif($visitor->visit_type === 'Second Time')
                            <span class="badge badge-warning">Second Time</span>
                        @elseif($visitor->visit_type === 'Third Time')
                            <span class="badge" style="background:#f3e8ff; color:#7c3aed;">Third Time</span>
                        @else
                            <span class="badge badge-success">Regular</span>
                        @endif
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $visitor->how_heard ?? '—' }}</td>
                    <td>
                        @php $color = $visitor->follow_up_status_color; @endphp
                        <span class="badge badge-{{ $color }}">{{ $visitor->follow_up_status }}</span>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('visitors.show', $visitor) }}" class="btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('visitors.edit', $visitor) }}" class="btn-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                            @if(!$visitor->converted_to_member)
                            <form method="POST" action="{{ route('visitors.convert', $visitor) }}" onsubmit="return confirm('Convert {{ $visitor->full_name }} to a full member?')">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm" style="background:#16a34a;" title="Convert to Member"><i class="fas fa-user-plus"></i></button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('visitors.destroy', $visitor) }}" onsubmit="return confirm('Delete this visitor?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-user-friends" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No visitors recorded yet</div>
                        <a href="{{ route('visitors.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Record First Visitor</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($visitors->hasPages())
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9;">
        {{ $visitors->links() }}
    </div>
    @endif
</div>

@endsection