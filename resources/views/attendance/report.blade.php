@extends('layouts.app')

@section('title', 'Attendance Report')

@section('content')

<div style="margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Attendance Report</h2>
        <div style="font-size:13px; color:#64748b;">Last 12 completed services</div>
    </div>
    <a href="{{ route('services.index') }}" class="btn-outline">
        <i class="fas fa-church"></i> View All Services
    </a>
</div>

@if($reportData->isEmpty())
<div class="card">
    <div style="text-align:center; padding:60px; color:#94a3b8;">
        <i class="fas fa-chart-bar" style="font-size:48px; display:block; margin-bottom:16px;"></i>
        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No completed services yet</div>
        <div style="margin-bottom:20px;">Complete some services to see attendance reports here.</div>
        <a href="{{ route('services.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Create Service</a>
    </div>
</div>
@else

<!-- Summary Cards -->
<div class="grid-3" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-church" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $reportData->count() }}</div>
            <div class="stat-label">Services Tracked</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-user-check" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $reportData->sum('present') }}</div>
            <div class="stat-label">Total Attendances</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-percentage" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $reportData->count() > 0 ? round($reportData->avg('percentage'), 1) : 0 }}%</div>
            <div class="stat-label">Average Attendance</div>
        </div>
    </div>
</div>

<!-- Report Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-chart-bar" style="color:#2563eb; margin-right:8px;"></i>Service Attendance Breakdown</div>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Present</th>
                    <th>Total Members</th>
                    <th>Attendance Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reportData as $data)
                <tr>
                    <td>
                        <a href="{{ route('services.show', $data['service']) }}" style="font-weight:600; color:#2563eb; text-decoration:none;">
                            {{ $data['service']->name }}
                        </a>
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $data['service']->service_date->format('D, M d, Y') }}</td>
                    <td style="font-size:13px; color:#64748b;">{{ $data['service']->service_type }}</td>
                    <td>
                        <span style="font-weight:700; color:#16a34a; font-size:16px;">{{ $data['present'] }}</span>
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $data['total'] }}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="flex:1; background:#f1f5f9; border-radius:20px; height:8px; overflow:hidden;">
                                <div style="width:{{ min($data['percentage'], 100) }}%; height:100%; background:{{ $data['percentage'] >= 70 ? '#16a34a' : ($data['percentage'] >= 40 ? '#ca8a04' : '#dc2626') }}; border-radius:20px;"></div>
                            </div>
                            <span style="font-size:13px; font-weight:700; color:{{ $data['percentage'] >= 70 ? '#16a34a' : ($data['percentage'] >= 40 ? '#ca8a04' : '#dc2626') }}; min-width:40px;">
                                {{ $data['percentage'] }}%
                            </span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endif

@endsection