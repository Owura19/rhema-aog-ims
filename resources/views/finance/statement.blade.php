@extends('layouts.app')

@section('title', 'Income & Expenditure Statement')

@section('content')

<!-- Date range picker -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('finance.statement') }}" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>
            <div>
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Generate</button>
            </div>
            <div style="margin-left:auto;">
                <a href="{{ route('finance.statement.pdf', ['from' => $from, 'to' => $to]) }}" class="btn-outline">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary -->
<div class="grid-3" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-arrow-up" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($totalIncome, 2) }}</div>
            <div class="stat-label">Total Income</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">
            <i class="fas fa-arrow-down" style="color:#dc2626;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($totalExpense, 2) }}</div>
            <div class="stat-label">Total Expenditure</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:{{ $netBalance >= 0 ? '#dbeafe' : '#fef9c3' }};">
            <i class="fas fa-scale-balanced" style="color:{{ $netBalance >= 0 ? '#2563eb' : '#ca8a04' }};"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px; color:{{ $netBalance >= 0 ? '#16a34a' : '#dc2626' }};">GHS {{ number_format($netBalance, 2) }}</div>
            <div class="stat-label">Net Balance</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-invoice-dollar" style="color:#1a3c5e; margin-right:8px;"></i>Income & Expenditure Statement</div>
        <div style="font-size:13px; color:#64748b;">{{ \Carbon\Carbon::parse($from)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}</div>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:90px;">Ref</th>
                    <th>Account</th>
                    <th style="text-align:right; width:160px;">Amount (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <!-- INCOME -->
                <tr style="background:#dcfce7;">
                    <td colspan="3" style="font-weight:800; color:#15803d;">INCOME — INFLOWS</td>
                </tr>
                @foreach($income as $group)
                <tr style="background:#f8fafc;">
                    <td><span class="badge badge-gray">{{ $group->ref }}</span></td>
                    <td style="font-weight:700; color:#1e293b;">{{ $group->name }}</td>
                    <td style="text-align:right; font-weight:700;">{{ number_format($group->total, 2) }}</td>
                </tr>
                @foreach($group->children as $child)
                    @if($child->total != 0)
                    <tr>
                        <td style="font-size:12px; color:#94a3b8;">{{ $child->ref }}</td>
                        <td style="padding-left:32px; color:#64748b;">{{ $child->name }}</td>
                        <td style="text-align:right; color:#64748b;">{{ number_format($child->total, 2) }}</td>
                    </tr>
                    @endif
                @endforeach
                @endforeach
                <tr style="border-top:2px solid #16a34a;">
                    <td colspan="2" style="font-weight:800; color:#15803d;">TOTAL INCOME</td>
                    <td style="text-align:right; font-weight:800; color:#15803d;">{{ number_format($totalIncome, 2) }}</td>
                </tr>

                <!-- EXPENDITURE -->
                <tr style="background:#fee2e2;">
                    <td colspan="3" style="font-weight:800; color:#b91c1c; padding-top:16px;">EXPENDITURE — OUTFLOWS</td>
                </tr>
                @foreach($expense as $group)
                <tr style="background:#f8fafc;">
                    <td><span class="badge badge-gray">{{ $group->ref }}</span></td>
                    <td style="font-weight:700; color:#1e293b;">{{ $group->name }}</td>
                    <td style="text-align:right; font-weight:700;">{{ number_format($group->total, 2) }}</td>
                </tr>
                @foreach($group->children as $child)
                    @if($child->total != 0)
                    <tr>
                        <td style="font-size:12px; color:#94a3b8;">{{ $child->ref }}</td>
                        <td style="padding-left:32px; color:#64748b;">{{ $child->name }}</td>
                        <td style="text-align:right; color:#64748b;">{{ number_format($child->total, 2) }}</td>
                    </tr>
                    @endif
                @endforeach
                @endforeach
                <tr style="border-top:2px solid #dc2626;">
                    <td colspan="2" style="font-weight:800; color:#b91c1c;">TOTAL EXPENDITURE</td>
                    <td style="text-align:right; font-weight:800; color:#b91c1c;">{{ number_format($totalExpense, 2) }}</td>
                </tr>

                <!-- NET -->
                <tr style="border-top:3px double #1a3c5e; background:#f0f4f8;">
                    <td colspan="2" style="font-weight:800; color:#1a3c5e; font-size:15px;">NET BALANCE (Inflows − Outflows)</td>
                    <td style="text-align:right; font-weight:800; font-size:15px; color:{{ $netBalance >= 0 ? '#15803d' : '#b91c1c' }};">{{ number_format($netBalance, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endsection