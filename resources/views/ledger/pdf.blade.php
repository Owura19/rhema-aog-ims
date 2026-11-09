<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1e293b; }
        .letterhead { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #1a3c5e; padding-bottom: 12px; }
        .letterhead img { height: 70px; margin-bottom: 8px; }
        .letterhead h1 { font-size: 24px; margin: 0; color: #1a3c5e; }
        .letterhead .sub { font-size: 13px; color: #64748b; margin-top: 3px; }
        .meta { margin-bottom: 12px; }
        .meta h2 { font-size: 17px; margin: 0 0 3px 0; }
        .meta .period { font-size: 13px; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 7px 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 12px; text-transform: uppercase; color: #475569; }
        td { font-size: 12.5px; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; border-top: 2px solid #1a3c5e; font-size: 13px; }
        .opening-row td { background: #f8fafc; font-weight: bold; color: #64748b; }
        .footnote { margin-top: 12px; font-size: 11px; color: #94a3b8; }
    </style>
</head>
<body>

    <div class="letterhead">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/aog-logo.png'))) }}">
        <h1>Rhema Assembly of God</h1>
        <div class="sub">Information Management System</div>
    </div>

    <div class="meta">
        <h2>{{ $account->code }} — {{ $account->name }} ({{ $account->type }})</h2>
        <div class="period">
            Period: {{ $from ? \Carbon\Carbon::parse($from)->format('M d, Y') : 'Start' }}
            to {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
            &nbsp;·&nbsp; Generated {{ now()->format('M d, Y \a\t g:i A') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:9%;">Date</th>
                <th style="width:8%;">Ref</th>
                <th style="width:19%;">Description</th>
                <th style="width:34%;">Purpose</th>
                <th class="num" style="width:10%;">Debit</th>
                <th class="num" style="width:10%;">Credit</th>
                <th class="num" style="width:10%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @if($from)
            <tr class="opening-row">
                <td colspan="6">Opening Balance</td>
                <td class="num">{{ number_format($opening, 2) }}</td>
            </tr>
            @endif
            @forelse($rows as $r)
            <tr>
                <td>{{ $r->date->format('M d, Y') }}</td>
                <td>{{ $r->ref }}</td>
                <td>{{ $r->desc }}</td>
                <td>{{ $r->purpose }}</td>
                <td class="num">{{ $r->debit ? number_format($r->debit, 2) : '—' }}</td>
                <td class="num">{{ $r->credit ? number_format($r->credit, 2) : '—' }}</td>
                <td class="num">{{ number_format($r->balance, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:20px;">No entries for this account in the selected period.</td></tr>
            @endforelse
        </tbody>
        @if($rows->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="4" class="num">TOTALS / CLOSING BALANCE</td>
                <td class="num">{{ number_format($totalDebit, 2) }}</td>
                <td class="num">{{ number_format($totalCredit, 2) }}</td>
                <td class="num">{{ number_format($closing, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footnote">
        Balance shown on the account's natural side ({{ in_array($account->type, ['Asset','Expense']) ? 'debit-normal' : 'credit-normal' }}).
    </div>

</body>
</html>
