@extends('layouts.app')

@section('title', 'Finance Report')

@section('content')

<div style="margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Finance Report</h2>
        <div style="font-size:13px; color:#64748b;">
            {{ $month ? \Carbon\Carbon::create()->month($month)->format('F') . ' ' . $year : 'Full Year ' . $year }}
        </div>
    </div>
    <a href="{{ route('finance.create') }}" class="btn-primary">
        <i class="fas fa-plus"></i> Record Transaction
    </a>
</div>

<!-- Year/Month Filter -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-body" style="padding:16px 24px;">
        <form method="GET" action="{{ route('finance.report') }}" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <label class="form-label" style="margin:0;">Year:</label>
            <select name="year" class="form-control" style="width:120px;">
                @for($y = now()->year; $y >= now()->year - 5; $y--)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <label class="form-label" style="margin:0;">Month:</label>
            <select name="month" class="form-control" style="width:150px;">
                <option value="">Full Year</option>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                    </option>
                @endfor
            </select>
            <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-arrow-up" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:20px;">GHS {{ number_format($income, 2) }}</div>
            <div class="stat-label">Total Income</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">
            <i class="fas fa-arrow-down" style="color:#dc2626;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:20px;">GHS {{ number_format($expense, 2) }}</div>
            <div class="stat-label">Total Expenses</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:{{ $balance >= 0 ? '#dcfce7' : '#fee2e2' }};">
            <i class="fas fa-balance-scale" style="color:{{ $balance >= 0 ? '#16a34a' : '#dc2626' }};"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:20px; color:{{ $balance >= 0 ? '#16a34a' : '#dc2626' }};">
                GHS {{ number_format($balance, 2) }}
            </div>
            <div class="stat-label">Net Balance</div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">

    <!-- By Type -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-pie" style="color:#7c3aed; margin-right:8px;"></i>Breakdown by Type</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Count</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($byType as $row)
                    <tr>
                        <td style="font-weight:600;">{{ $row->type }}</td>
                        <td>
                            <span class="badge {{ $row->category === 'Income' ? 'badge-success' : 'badge-danger' }}">
                                {{ $row->category }}
                            </span>
                        </td>
                        <td style="color:#64748b;">{{ $row->count }}</td>
                        <td style="font-weight:700; color:{{ $row->category === 'Income' ? '#16a34a' : '#dc2626' }};">
                            GHS {{ number_format($row->total, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;">
                            No transactions recorded yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Breakdown -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-calendar-alt" style="color:#2563eb; margin-right:8px;"></i>Monthly Breakdown</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Income</th>
                        <th>Expense</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $monthlyGrouped = $monthly->groupBy('month');
                        $hasData = false;
                    @endphp
                    @for($m = 1; $m <= 12; $m++)
                        @php
                            $monthData = $monthlyGrouped->get($m, collect());
                            $inc = $monthData->where('category', 'Income')->sum('total');
                            $exp = $monthData->where('category', 'Expense')->sum('total');
                            $net = $inc - $exp;
                        @endphp
                        @if($inc > 0 || $exp > 0)
                            @php $hasData = true; @endphp
                            <tr>
                                <td style="font-weight:600;">{{ \Carbon\Carbon::create()->month($m)->format('F') }}</td>
                                <td style="color:#16a34a; font-weight:600;">GHS {{ number_format($inc, 2) }}</td>
                                <td style="color:#dc2626; font-weight:600;">GHS {{ number_format($exp, 2) }}</td>
                                <td style="font-weight:700; color:{{ $net >= 0 ? '#16a34a' : '#dc2626' }};">
                                    GHS {{ number_format($net, 2) }}
                                </td>
                            </tr>
                        @endif
                    @endfor
                    @if(!$hasData)
                        <tr>
                            <td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;">
                                No data for this period.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Top Givers -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-trophy" style="color:#e8a020; margin-right:8px;"></i>Top Givers</div>
    </div>
    @if($topGivers->isNotEmpty())
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Total Given</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topGivers as $index => $giver)
                <tr>
                    <td>
                        @if($index === 0)
                            <span style="font-size:20px;">🥇</span>
                        @elseif($index === 1)
                            <span style="font-size:20px;">🥈</span>
                        @elseif($index === 2)
                            <span style="font-size:20px;">🥉</span>
                        @else
                            <span style="color:#94a3b8; font-weight:600;">{{ $index + 1 }}</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="member-avatar-placeholder" style="width:32px; height:32px; font-size:12px;">
                                {{ strtoupper(substr($giver->member->first_name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;">{{ $giver->member->full_name }}</div>
                                <div style="font-size:12px; color:#94a3b8;">{{ $giver->member->member_id }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-weight:700; font-size:16px; color:#16a34a;">
                        GHS {{ number_format($giver->total, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="text-align:center; padding:40px; color:#94a3b8;">
        <i class="fas fa-trophy" style="font-size:36px; display:block; margin-bottom:12px;"></i>
        <div>No giving records for this period.</div>
    </div>
    @endif
</div>

@endsection