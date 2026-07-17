@extends('layouts.app')

@section('title', $harvest->name)

@section('content')

<div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
    <a href="{{ route('harvests.index') }}" style="color:#64748b; text-decoration:none; font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Harvest Campaigns</a>
    @can('create finance')
    <a href="{{ route('harvests.edit', $harvest) }}" class="btn-outline btn-sm"><i class="fas fa-pen"></i> Edit Campaign</a>
    @endcan
</div>

<!-- Top stats -->
<div class="grid-4" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-bullseye" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($harvest->target_amount, 2) }}</div>
            <div class="stat-label">Target</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-hand-holding-heart" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($harvest->total_pledged, 2) }}</div>
            <div class="stat-label">Total Pledged</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-check-circle" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($harvest->total_paid, 2) }}</div>
            <div class="stat-label">Collected</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-hourglass-half" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($harvest->outstanding, 2) }}</div>
            <div class="stat-label">Outstanding</div>
        </div>
    </div>
</div>

<!-- Progress card -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-wheat-awn" style="color:#ca8a04; margin-right:8px;"></i>{{ $harvest->name }}</div>
        <div style="font-size:13px; color:#64748b;">
            @if($harvest->harvest_date)
                @php $days = $harvest->days_to_harvest; @endphp
                @if($days > 0)
                    <i class="fas fa-calendar"></i> {{ $days }} days to harvest ({{ $harvest->harvest_date->format('M d, Y') }})
                @elseif($days === 0)
                    <i class="fas fa-calendar-check"></i> Harvest is today!
                @else
                    <i class="fas fa-calendar-check"></i> Harvest was {{ $harvest->harvest_date->format('M d, Y') }}
                @endif
            @endif
        </div>
    </div>
    <div class="card-body">
        <!-- Collected vs target -->
        <div style="margin-bottom:6px; display:flex; justify-content:space-between; font-size:13px;">
            <span style="color:#64748b;">Collected toward target</span>
            <span style="font-weight:700;">{{ $harvest->target_progress }}%</span>
        </div>
        <div style="background:#f1f5f9; border-radius:20px; height:14px; overflow:hidden; margin-bottom:18px; position:relative;">
            <div style="background:#16a34a; height:14px; width:{{ $harvest->target_progress }}%;"></div>
        </div>

        <!-- Pledged vs target -->
        <div style="margin-bottom:6px; display:flex; justify-content:space-between; font-size:13px;">
            <span style="color:#64748b;">Pledged toward target</span>
            <span style="font-weight:700;">{{ $harvest->pledged_progress }}%</span>
        </div>
        <div style="background:#f1f5f9; border-radius:20px; height:14px; overflow:hidden;">
            <div style="background:#2563eb; height:14px; width:{{ $harvest->pledged_progress }}%;"></div>
        </div>

        @if($harvest->description)
        <div style="margin-top:16px; color:#64748b; font-size:14px;">{{ $harvest->description }}</div>
        @endif
    </div>
</div>

<!-- Pledgers list -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users" style="color:#2563eb; margin-right:8px;"></i>Harvest Pledgers ({{ $harvest->pledgers_count }})</div>
        @can('create finance')
        <a href="{{ route('pledges.create') }}" class="btn-primary btn-sm"><i class="fas fa-plus"></i> Add Pledge</a>
        @endcan
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Pledger</th>
                    <th style="text-align:right;">Pledged</th>
                    <th style="text-align:right;">Paid</th>
                    <th style="text-align:right;">Balance</th>
                    <th style="width:120px;">Progress</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pledges as $pledge)
                <tr>
                    <td>
                        <a href="{{ route('pledges.show', $pledge) }}" style="font-weight:600; color:#2563eb; text-decoration:none;">{{ $pledge->pledger_label }}</a>
                        <div style="font-size:11px; color:#94a3b8;">{{ $pledge->reference }}</div>
                    </td>
                    <td style="text-align:right;">{{ number_format($pledge->amount_pledged, 2) }}</td>
                    <td style="text-align:right; color:#16a34a;">{{ number_format($pledge->total_paid, 2) }}</td>
                    <td style="text-align:right; color:{{ $pledge->balance > 0 ? '#dc2626' : '#64748b' }};">{{ number_format($pledge->balance, 2) }}</td>
                    <td>
                        <div style="background:#f1f5f9; border-radius:20px; height:8px; overflow:hidden;">
                            <div style="background:#16a34a; height:8px; width:{{ $pledge->progress_percent }}%;"></div>
                        </div>
                        <div style="font-size:11px; color:#94a3b8; margin-top:2px;">{{ $pledge->progress_percent }}%</div>
                    </td>
                    <td>
                        @if($pledge->status === 'Fulfilled')
                            <span class="badge badge-success">Fulfilled</span>
                        @else
                            <span class="badge badge-info">Active</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:#94a3b8; padding:30px;">
                        No pledges tied to this harvest yet. Create a pledge and select this campaign.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection