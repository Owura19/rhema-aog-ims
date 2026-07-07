@extends('layouts.app')

@section('title', 'Church Services')

@section('content')

<div class="grid-4" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-church" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-label">Total Services</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-clock" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['scheduled'] }}</div>
            <div class="stat-label">Scheduled</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-check-circle" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['completed'] }}</div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-calendar" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['this_month'] }}</div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-church" style="color:#2563eb; margin-right:8px;"></i>Church Services</div>
        <a href="{{ route('services.create') }}" class="btn-primary">
            <i class="fas fa-plus"></i> New Service
        </a>
    </div>

    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Attendance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                <tr>
                    <td>
                        <div style="font-weight:600; color:#1e293b;">{{ $service->name }}</div>
                        <div style="font-size:12px; color:#94a3b8;">{{ $service->venue ?? 'No venue set' }}</div>
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $service->service_type }}</td>
                    <td style="font-size:13px;">{{ $service->service_date->format('D, M d, Y') }}</td>
                    <td style="font-size:13px; color:#64748b;">{{ \Carbon\Carbon::parse($service->start_time)->format('g:i A') }}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-weight:700; color:#1e293b;">{{ $service->attendance_logs_count }}</span>
                            <span style="font-size:12px; color:#94a3b8;">present</span>
                        </div>
                    </td>
                    <td>
                        @if($service->status === 'Scheduled')
                            <span class="badge badge-warning">Scheduled</span>
                        @elseif($service->status === 'Ongoing')
                            <span class="badge badge-info">Ongoing</span>
                        @elseif($service->status === 'Completed')
                            <span class="badge badge-success">Completed</span>
                        @else
                            <span class="badge badge-danger">Cancelled</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('services.show', $service) }}" class="btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('services.edit', $service) }}" class="btn-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="{{ route('services.destroy', $service) }}" onsubmit="return confirm('Delete this service?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-church" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No services yet</div>
                        <a href="{{ route('services.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Create First Service</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($services->hasPages())
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9;">
        {{ $services->links() }}
    </div>
    @endif
</div>

@endsection