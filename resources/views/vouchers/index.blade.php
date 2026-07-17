@extends('layouts.app')

@section('title', 'Payment Vouchers')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-size:20px;font-weight:700;color:#1e293b;">Payment Vouchers</h2>
    <a href="{{ route('vouchers.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New Voucher</a>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" style="margin-bottom:16px;"><i class="fas fa-triangle-exclamation"></i> {{ session('error') }}</div>@endif

<!-- Stats -->
<div class="grid-3" style="margin-bottom:22px;">
    <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;"><i class="fas fa-clock" style="color:#d97706;"></i></div><div><div class="stat-value">{{ $stats['pending'] }}</div><div class="stat-label">Pending Approval</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;"><i class="fas fa-circle-check" style="color:#2563eb;"></i></div><div><div class="stat-value">{{ $stats['approved'] }}</div><div class="stat-label">Approved (awaiting payment)</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;"><i class="fas fa-money-bill-wave" style="color:#16a34a;"></i></div><div><div class="stat-value">GHS {{ number_format($stats['paid'], 2) }}</div><div class="stat-label">Total Paid</div></div></div>
</div>

<!-- Filter -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:14px 18px;">
        <form method="GET" style="display:flex;gap:10px;align-items:center;">
            <label style="font-size:13px;color:#64748b;">Status</label>
            <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <option value="">All</option>
                @foreach(['Pending','Approved','Paid','Rejected','Cancelled'] as $s)
                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Voucher No</th>
                    <th>Date</th>
                    <th>Payee</th>
                    <th>Purpose</th>
                    <th style="text-align:right;">Amount</th>
                    <th style="text-align:center;">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($vouchers as $v)
                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:12.5px;">{{ $v->voucher_no }}</td>
                    <td style="font-size:13px;color:#64748b;">{{ $v->voucher_date->format('M d, Y') }}</td>
                    <td style="font-weight:600;color:#1e293b;">{{ $v->payee }}</td>
                    <td style="font-size:13px;color:#64748b;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $v->description }}</td>
                    <td style="text-align:right;font-weight:600;">GHS {{ number_format($v->amount, 2) }}</td>
                    <td style="text-align:center;"><span class="badge" style="background:{{ $v->status_color }}20;color:{{ $v->status_color }};font-weight:600;">{{ $v->status }}</span></td>
                    <td style="text-align:right;"><a href="{{ route('vouchers.show', $v) }}" style="color:#2563eb;text-decoration:none;font-size:13px;font-weight:600;">View</a></td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:36px;">
                    <i class="fas fa-file-invoice-dollar" style="font-size:26px;color:#cbd5e1;"></i>
                    <div style="margin-top:10px;">No vouchers yet. Create one to get started.</div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vouchers->hasPages())<div style="padding:16px 22px;">{{ $vouchers->links() }}</div>@endif
</div>

@endsection