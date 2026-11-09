<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 15px; color: #1e293b; }
        .letterhead { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #1a3c5e; padding-bottom: 12px; }
        .letterhead img { height: 70px; margin-bottom: 8px; }
        .letterhead h1 { font-size: 24px; margin: 0; color: #1a3c5e; }
        .letterhead .sub { font-size: 13px; color: #64748b; margin-top: 3px; }
        .meta { margin-bottom: 12px; }
        .meta h2 { font-size: 17px; margin: 0 0 3px 0; }
        .meta .period { font-size: 13px; color: #475569; }
        .stats { display: table; width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .stats-row { display: table-row; }
        .stat-box { display: table-cell; border: 1px solid #e2e8f0; padding: 8px 12px; width: 33%; }
        .stat-box .label { font-size: 12px; text-transform: uppercase; color: #64748b; }
        .stat-box .value { font-size: 18px; font-weight: bold; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 6px 7px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 13px; text-transform: uppercase; color: #475569; }
        td { font-size: 13px; }
        .num { text-align: right; white-space: nowrap; }
        .income { color: #16a34a; font-weight: bold; }
        .expense { color: #dc2626; font-weight: bold; }
        tfoot td { font-weight: bold; border-top: 2px solid #1a3c5e; font-size: 12px; }
        .footnote { margin-top: 10px; font-size: 10px; color: #94a3b8; }
    </style>
</head>
<body>

    <div class="letterhead">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/aog-logo.png'))) }}">
        <h1>Rhema Assembly of God</h1>
        <div class="sub">Information Management System</div>
    </div>

    <div class="meta">
        <h2>Transactions</h2>
        <div class="period">
            @if(!empty(array_filter($filters)))
                Filters:
                @if(!empty($filters['search'])) Search "{{ $filters['search'] }}" @endif
                @if(!empty($filters['type'])) &middot; Type: {{ $filters['type'] }} @endif
                @if(!empty($filters['category'])) &middot; Category: {{ $filters['category'] }} @endif
                @if(!empty($filters['payment_method'])) &middot; Method: {{ $filters['payment_method'] }} @endif
                @if(!empty($filters['date_from'])) &middot; From: {{ $filters['date_from'] }} @endif
                @if(!empty($filters['date_to'])) &middot; To: {{ $filters['date_to'] }} @endif
            @else
                Period: {{ $rangeLabel }}
            @endif
            &nbsp;&middot;&nbsp; {{ $transactions->count() }} transactions
            &nbsp;&middot;&nbsp; Generated {{ now()->format('M d, Y \a\t g:i A') }}
        </div>
    </div>

    <div class="stats">
        <div class="stats-row">
            <div class="stat-box">
                <div class="label">Total Income</div>
                <div class="value income">GHS {{ number_format($stats['total_income'], 2) }}</div>
            </div>
            <div class="stat-box">
                <div class="label">Total Expense</div>
                <div class="value expense">GHS {{ number_format($stats['total_expense'], 2) }}</div>
            </div>
            <div class="stat-box">
                <div class="label">Net Balance</div>
                <div class="value" style="color: {{ $stats['net_balance'] >= 0 ? '#16a34a' : '#dc2626' }};">GHS {{ number_format($stats['net_balance'], 2) }}</div>
            </div>
        </div>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:11%;">Reference</th>
                <th style="width:19%;">Payer</th>
                <th style="width:12%;">Type</th>
                <th class="num" style="width:12%;">Amount</th>
                <th style="width:14%;">Method</th>
                <th style="width:11%;">Date</th>
                <th style="width:12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $t)
            <tr>
                <td>{{ $t->reference }}</td>
                <td>{{ $t->payer_label }}</td>
                <td>{{ $t->type }}</td>
                <td class="num {{ $t->category === 'Income' ? 'income' : 'expense' }}">
                    {{ $t->category === 'Expense' ? '-' : '+' }} {{ number_format($t->amount, 2) }}
                </td>
                <td>{{ $t->payment_method }}</td>
                <td>{{ $t->transaction_date->format('M d, Y') }}</td>
                <td>{{ $t->status }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:20px;">No transactions found for this selection.</td></tr>
            @endforelse
        </tbody>
        @if($transactions->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="3" class="num">TOTALS</td>
                <td class="num">GHS {{ number_format($stats['total_income'] + $stats['total_expense'], 2) }}</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footnote">
        Generated from Rhema Assembly of God Information Management System.
    </div>

</body>
</html>
