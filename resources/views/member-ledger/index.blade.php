@extends('layouts.app')

@section('title', 'Member Giving Report')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <a href="{{ route('finance.reports-hub') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Financial Reports</a>
        <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-top:4px;">Member Giving Report</h2>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
        <div><label style="font-size:12px;color:#64748b;display:block;">From</label><input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto;"></div>
        <div><label style="font-size:12px;color:#64748b;display:block;">To</label><input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto;"></div>
        <button type="submit" class="btn-primary btn-sm">Apply</button>
    </form>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;align-items:center;gap:14px;">
        <div class="stat-icon" style="background:#dcfce7;"><i class="fas fa-hand-holding-heart" style="color:#16a34a;"></i></div>
        <div>
            <div class="stat-value">GHS {{ number_format($grandTotal, 2) }}</div>
            <div class="stat-label">Total Member Giving · {{ \Carbon\Carbon::parse($from)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Member</th>
                    <th style="text-align:center;">Gifts</th>
                    <th style="text-align:right;">Total Given (GHS)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($givers as $i => $g)
                <tr>
                    <td style="color:#94a3b8;">{{ $i + 1 }}</td>
                    <td style="font-weight:600;color:#1e293b;">{{ $g->member->full_name ?? ($g->member->first_name.' '.$g->member->last_name) }}</td>
                    <td style="text-align:center;color:#64748b;">{{ $g->gifts }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($g->total, 2) }}</td>
                    <td style="text-align:right;"><a href="{{ route('member-ledger.show', $g->member) }}" style="color:#2563eb;text-decoration:none;font-size:12.5px;font-weight:600;">Statement</a></td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:36px;">
                    <i class="fas fa-hand-holding-heart" style="font-size:26px;color:#cbd5e1;"></i>
                    <div style="margin-top:10px;">No member giving recorded in this period.</div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:12px;font-size:12px;color:#94a3b8;">Only giving linked to a registered member is shown. Anonymous / walk-in giving is not included here.</div>

@endsection