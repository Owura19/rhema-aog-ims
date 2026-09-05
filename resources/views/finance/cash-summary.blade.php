@extends('layouts.app')
@section('title', 'Receipts & Payments')
@section('content')

<!-- Date range picker -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('finance.cash-summary') }}" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
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
                <button onclick="window.print()" class="btn-outline" type="button"><i class="fas fa-print"></i> Print</button>
            </div>
        </form>
    </div>
</div>

<div style="max-width:1000px; margin:0 auto;">

    <!-- Document header -->
    <div style="text-align:center; border-bottom:2px solid #1a3c5e; padding-bottom:14px; margin-bottom:6px;">
        <h2 style="font-size:22px; font-weight:800; color:#1a3c5e; margin:0;">Receipts &amp; Payments</h2>
        <div style="font-size:13px; color:#64748b; margin-top:4px;">
            {{ \Carbon\Carbon::parse($from)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
            &middot; Cash, Mobile Money &amp; Cheque only
        </div>
    </div>

    <!-- Summary strip -->
    <div style="display:flex; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; margin:18px 0 24px;">
        <div style="flex:1; padding:14px 20px; border-right:1px solid #e2e8f0;">
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Total Receipts</div>
            <div style="font-size:19px; font-weight:800; color:#16a34a; margin-top:2px;">GHS {{ number_format($totalReceipts, 2) }}</div>
        </div>
        <div style="flex:1; padding:14px 20px; border-right:1px solid #e2e8f0;">
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Total Payments</div>
            <div style="font-size:19px; font-weight:800; color:#dc2626; margin-top:2px;">GHS {{ number_format($totalPayments, 2) }}</div>
        </div>
        <div style="flex:1; padding:14px 20px;">
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Net Cash</div>
            <div style="font-size:19px; font-weight:800; color:{{ $netCash >= 0 ? '#1a3c5e' : '#dc2626' }}; margin-top:2px;">GHS {{ number_format($netCash, 2) }}</div>
        </div>
    </div>

    <!-- Receipts -->
    <div style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-bottom:24px;">
        <div style="background:#16a34a; color:#fff; padding:10px 16px; font-weight:700; font-size:14px;"><i class="fas fa-arrow-down"></i> Receipts</div>
        <table class="table" style="width:100%; margin:0;">
            <thead>
                <tr><th>Account</th><th style="text-align:right;">Amount (GHS)</th></tr>
            </thead>
            <tbody>
                @forelse($receipts as $r)
                <tr>
                    <td>{{ $r->account }}</td>
                    <td style="text-align:right; color:#16a34a; font-weight:600;">{{ number_format($r->total, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="2" style="text-align:center; color:#94a3b8; padding:20px;">No cash receipts in this period.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #16a34a; font-weight:800; background:#f0fdf4;">
                    <td style="color:#166534;">TOTAL RECEIPTS</td>
                    <td style="text-align:right; color:#166534;">GHS {{ number_format($totalReceipts, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Payments -->
    <div style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-bottom:24px;">
        <div style="background:#dc2626; color:#fff; padding:10px 16px; font-weight:700; font-size:14px;"><i class="fas fa-arrow-up"></i> Cash Payments</div>
        <table class="table" style="width:100%; margin:0;">
            <thead>
                <tr><th>Account</th><th style="text-align:right;">Amount (GHS)</th></tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                <tr>
                    <td>{{ $p->account }}</td>
                    <td style="text-align:right; color:#dc2626; font-weight:600;">{{ number_format($p->total, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="2" style="text-align:center; color:#94a3b8; padding:20px;">No cash payments in this period.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #dc2626; font-weight:800; background:#fef2f2;">
                    <td style="color:#991b1b;">TOTAL PAYMENTS</td>
                    <td style="text-align:right; color:#991b1b;">GHS {{ number_format($totalPayments, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Net -->
    <div style="border:2px solid #1a3c5e; border-radius:8px; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
        <div style="font-weight:800; color:#1a3c5e; font-size:15px;">NET CASH MOVEMENT</div>
        <div style="font-weight:800; font-size:18px; color:{{ $netCash >= 0 ? '#16a34a' : '#dc2626' }};">GHS {{ number_format($netCash, 2) }}</div>
    </div>

</div>
@endsection
