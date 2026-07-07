@extends('layouts.app')

@section('title', $service->name)

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <a href="{{ route('services.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $service->name }}</h2>
        <div style="font-size:13px; color:#64748b;">{{ $service->service_date->format('l, F d, Y') }} · {{ \Carbon\Carbon::parse($service->start_time)->format('g:i A') }}</div>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="{{ route('services.edit', $service) }}" class="btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
    </div>
</div>

<!-- Stats -->
<div class="grid-5" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;"><i class="fas fa-user-check" style="color:#16a34a;"></i></div>
        <div><div class="stat-value">{{ $stats['present'] }}</div><div class="stat-label">Present</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;"><i class="fas fa-clock" style="color:#ca8a04;"></i></div>
        <div><div class="stat-value">{{ $stats['late'] }}</div><div class="stat-label">Late</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;"><i class="fas fa-user-times" style="color:#dc2626;"></i></div>
        <div><div class="stat-value">{{ $stats['absent'] }}</div><div class="stat-label">Absent</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;"><i class="fas fa-users" style="color:#2563eb;"></i></div>
        <div><div class="stat-value">{{ $stats['total'] }}</div><div class="stat-label">Total Members</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;"><i class="fas fa-percentage" style="color:#7c3aed;"></i></div>
        <div><div class="stat-value">{{ $service->attendance_percentage }}%</div><div class="stat-label">Attendance</div></div>
    </div>
</div>

<div class="grid-main">

    <!-- Attendance List -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list" style="color:#2563eb; margin-right:8px;"></i>Attendance Records</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Status</th>
                        <th>Check-in Time</th>
                        <th>Method</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendance as $log)
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
                            {{ $log->check_in_time ? $log->check_in_time->format('g:i A') : '—' }}
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
                        <td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">
                            No attendance records yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Panel -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Manual Mark -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-hand-pointer" style="color:#e8a020; margin-right:8px;"></i>Mark Attendance</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('attendance.mark') }}">
                    @csrf
                    <input type="hidden" name="church_service_id" value="{{ $service->id }}">
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Member</label>
                        <select name="member_id" class="form-control" required>
                            <option value="">Select member...</option>
                            @foreach($unmarkedMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->full_name }} ({{ $member->member_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="Present">Present</option>
                            <option value="Late">Late</option>
                            <option value="Absent">Absent</option>
                            <option value="Excused">Excused</option>
                        </select>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes">
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fas fa-check"></i> Mark Attendance
                    </button>
                </form>
            </div>
        </div>

        <!-- Biometric Sync -->
        @if($service->biometric_enabled)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-fingerprint" style="color:#2563eb; margin-right:8px;"></i>Biometric Sync</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('attendance.sync-biometric') }}">
                    @csrf
                    <input type="hidden" name="church_service_id" value="{{ $service->id }}">
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Select Device</label>
                        <select name="biometric_device_id" class="form-control" required>
                            <option value="">Select device...</option>
                            @foreach(\App\Models\BiometricDevice::where('is_active', true)->get() as $device)
                                <option value="{{ $device->id }}">{{ $device->name }} ({{ $device->ip_address }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%; background:#2563eb;">
                        <i class="fas fa-sync"></i> Sync from Device
                    </button>
                    <div style="font-size:11px; color:#94a3b8; margin-top:8px; text-align:center;">
                        Pulls fingerprint punch records from the ZKTeco device
                    </div>
                </form>
            </div>
        </div>
        @endif

        <!-- Service Info -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-info-circle" style="color:#7c3aed; margin-right:8px;"></i>Service Info</div>
            </div>
            <div class="card-body" style="padding:0;">
                <table style="width:100%;">
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Type</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $service->service_type }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Venue</td>
                        <td style="padding:10px 20px; font-size:13px; color:#1e293b;">{{ $service->venue ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Status</td>
                        <td style="padding:10px 20px;">
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
                    <tr>
                        <td style="padding:10px 20px; font-size:12px; color:#94a3b8; font-weight:600;">Biometric</td>
                        <td style="padding:10px 20px;">
                            @if($service->biometric_enabled)
                                <span class="badge badge-success"><i class="fas fa-fingerprint"></i> Enabled</span>
                            @else
                                <span class="badge badge-gray">Disabled</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

    </div>
</div>

@endsection