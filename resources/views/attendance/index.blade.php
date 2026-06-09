@extends('layouts.app')

@section('title', 'Attendance Logs')

@section('content')

<div style="margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Attendance Logs</h2>
        <div style="font-size:13px; color:#64748b;">All attendance records across all services</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-clipboard-list" style="color:#2563eb; margin-right:8px;"></i>All Records</div>
    </div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <form method="GET" action="{{ route('attendance.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <select name="service_id" class="form-control" style="width:220px;">
                <option value="">All Services</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" {{ request('service_id') == $service->id ? 'selected' : '' }}>
                        {{ $service->name }} — {{ $service->service_date->format('M d, Y') }}
                    </option>
                @endforeach
            </select>
            <select name="member_id" class="form-control" style="width:200px;">
                <option value="">All Members</option>
                @foreach($members as $member)
                    <option value="{{ $member->id }}" {{ request('member_id') == $member->id ? 'selected' : '' }}>
                        {{ $member->full_name }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="form-control" style="width:150px;">
                <option value="">All Statuses</option>
                @foreach(['Present','Late','Absent','Excused'] as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filter</button>
            @if(request()->hasAny(['service_id','member_id','status']))
                <a href="{{ route('attendance.index') }}" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Check-in Time</th>
                    <th>Method</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="member-avatar-placeholder">{{ strtoupper(substr($log->member->first_name,0,1)) }}</div>
                            <div>
                                <div style="font-weight:600;">{{ $log->member->full_name }}</div>
                                <div style="font-size:12px; color:#94a3b8;">{{ $log->member->member_id }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:13px;">{{ $log->churchService->name }}</div>
                        <div style="font-size:12px; color:#94a3b8;">{{ $log->churchService->service_date->format('M d, Y') }}</div>
                    </td>
                    <td>
                        @if($log->status === 'Present')
                            <span class="badge badge-success">Present</span>
                        @elseif($log->status === 'Late')
                            <span class="badge badge-warning">Late</span>
                        @elseif($log->status === 'Absent')
                            <span class="badge badge-danger">Absent</span>
                        @else
                            <span class="badge badge-info">Excused</span>
                        @endif
                    </td>
                    <td style="font-size:13px; color:#64748b;">
                        {{ $log->check_in_time ? $log->check_in_time->format('M d, Y g:i A') : '—' }}
                    </td>
                    <td>
                        @if($log->check_in_method === 'Biometric')
                            <span class="badge badge-info"><i class="fas fa-fingerprint"></i> Biometric</span>
                        @else
                            <span class="badge badge-gray">Manual</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('attendance.destroy', $log) }}" onsubmit="return confirm('Remove this record?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-clipboard-list" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No attendance records found</div>
                        <div>Create a service and start marking attendance.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; color:#64748b;">
            Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} records
        </div>
        {{ $logs->links() }}
    </div>
    @endif
</div>

@endsection