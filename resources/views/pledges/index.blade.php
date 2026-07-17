@extends('layouts.app')

@section('title', 'Pledges')

@section('content')

<!-- Summary cards -->
<div class="grid-4" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-hand-holding-heart" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($summary['total_pledged'], 2) }}</div>
            <div class="stat-label">Total Pledged</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-check-circle" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($summary['total_collected'], 2) }}</div>
            <div class="stat-label">Collected</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-hourglass-half" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($summary['outstanding'], 2) }}</div>
            <div class="stat-label">Outstanding</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-list-check" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value">{{ $summary['active_count'] }}</div>
            <div class="stat-label">Active Pledges</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-hand-holding-heart" style="color:#7c3aed; margin-right:8px;"></i>All Pledges</div>
        @can('create finance')
        <a href="{{ route('pledges.create') }}" class="btn-primary btn-sm"><i class="fas fa-plus"></i> New Pledge</a>
        @endcan
    </div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9;">
        <form method="GET" action="{{ route('pledges.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-control" style="min-width:150px;">
                    <option value="">All statuses</option>
                    <option value="Active"    {{ request('status')=='Active' ? 'selected' : '' }}>Active</option>
                    <option value="Fulfilled" {{ request('status')=='Fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                    <option value="Cancelled" {{ request('status')=='Cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="form-label">Purpose</label>
                <select name="purpose" class="form-control" style="min-width:170px;">
                    <option value="">All purposes</option>
                    @foreach($purposes as $purpose)
                    <option value="{{ $purpose->id }}" {{ request('purpose')==$purpose->id ? 'selected' : '' }}>{{ $purpose->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <a href="{{ route('pledges.index') }}" class="btn-outline btn-sm" style="border-color:#e2e8f0; color:#64748b;">Clear</a>
            </div>
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Pledger</th>
                    <th>Purpose</th>
                    <th>Pledged</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Progress</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pledges as $pledge)
                <tr>
                    <td>
                        <a href="{{ route('pledges.show', $pledge) }}" style="font-weight:600; color:#2563eb; text-decoration:none;">{{ $pledge->reference }}</a>
                    </td>
                    <td>{{ $pledge->pledger_label }}</td>
                    <td><span class="badge badge-gray">{{ optional($pledge->purpose)->name }}</span></td>
                    <td>GHS {{ number_format($pledge->amount_pledged, 2) }}</td>
                    <td style="color:#16a34a;">GHS {{ number_format($pledge->total_paid, 2) }}</td>
                    <td style="color:{{ $pledge->balance > 0 ? '#dc2626' : '#64748b' }};">GHS {{ number_format($pledge->balance, 2) }}</td>
                    <td style="min-width:110px;">
                        <div style="background:#f1f5f9; border-radius:20px; height:8px; overflow:hidden;">
                            <div style="background:#16a34a; height:8px; width:{{ $pledge->progress_percent }}%;"></div>
                        </div>
                        <div style="font-size:11px; color:#94a3b8; margin-top:2px;">{{ $pledge->progress_percent }}%</div>
                    </td>
                    <td>
                        @if($pledge->status === 'Fulfilled')
                            <span class="badge badge-success">Fulfilled</span>
                        @elseif($pledge->status === 'Active')
                            <span class="badge badge-info">Active</span>
                        @else
                            <span class="badge badge-danger">Cancelled</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:#94a3b8; padding:30px;">
                        No pledges yet.
                        @can('create finance')
                        <a href="{{ route('pledges.create') }}" style="color:#2563eb;">Record the first pledge</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pledges->hasPages())
    <div style="padding:16px 24px;">
        {{ $pledges->links() }}
    </div>
    @endif
</div>

@endsection