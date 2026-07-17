@extends('layouts.app')

@section('title', 'Giving Statement — ' . ($member->full_name ?? $member->first_name))

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <a href="{{ route('member-ledger.index') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Giving Report</a>
        <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-top:4px;">{{ $member->full_name ?? ($member->first_name.' '.$member->last_name) }}</h2>
        <div style="font-size:12.5px;color:#94a3b8;">Giving Statement</div>
    </div>
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:8px;align-items:flex-end;">
            <div><label style="font-size:12px;color:#64748b;display:block;">From</label><input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto;"></div>
            <div><label style="font-size:12px;color:#64748b;display:block;">To</label><input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto;"></div>
            <button type="submit" class="btn-primary btn-sm">Apply</button>
        </form>
        <a href="{{ route('member-ledger.print', $member) }}?from={{ $from }}&to={{ $to }}" target="_blank" class="btn-outline btn-sm"><i class="fas fa-print"></i> Print</a>
    </div>
</div>

<!-- Summary by type -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-chart-simple" style="color:#16a34a;margin-right:8px;"></i>Summary by Type</div></div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead><tr><th>Giving Type</th><th style="text-align:center;">Gifts</th><th style="text-align:right;">Total (GHS)</th></tr></thead>
            <tbody>
                @forelse($summary as $s)
                <tr>
                    <td style="font-weight:600;color:#1e293b;">{{ $s->type }}</td>
                    <td style="text-align:center;color:#64748b;">{{ $s->gifts }}</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($s->total, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:20px;">No giving in this period.</td></tr>
                @endforelse
            </tbody>
            @if($summary->isNotEmpty())
            <tfoot><tr style="border-top:2px solid #e2e8f0;font-weight:800;"><td colspan="2" style="text-align:right;">TOTAL</td><td style="text-align:right;color:#16a34a;">GHS {{ number_format($total, 2) }}</td></tr></tfoot>
            @endif
        </table>
    </div>
</div>

<!-- Itemized list -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-list" style="color:#2563eb;margin-right:8px;"></i>All Contributions</div></div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Method</th><th style="text-align:right;">Amount (GHS)</th></tr></thead>
            <tbody>
                @forelse($items as $it)
                <tr>
                    <td style="font-size:13px;color:#64748b;">{{ \Carbon\Carbon::parse($it->transaction_date)->format('M d, Y') }}</td>
                    <td style="font-weight:600;color:#1e293b;">{{ $it->type }}</td>
                    <td style="font-family:monospace;font-size:12px;color:#94a3b8;">{{ $it->reference }}</td>
                    <td style="font-size:13px;color:#64748b;">{{ $it->payment_method }}</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($it->amount, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px;">No contributions in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection