@extends('layouts.app')

@section('title', 'Ledger — ' . $account->name)

@section('content')

<div id="ledger-toolbar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <a href="{{ route('ledger.index') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to General Ledger</a>
        <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-top:4px;">{{ $account->code }} — {{ $account->name }}</h2>
        <div style="font-size:12.5px;color:#94a3b8;">Account Statement · {{ $account->type }}</div>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
        <div><label style="font-size:12px;color:#64748b;display:block;">From</label><input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto;"></div>
        <div><label style="font-size:12px;color:#64748b;display:block;">To</label><input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto;"></div>
        <button type="submit" class="btn-primary btn-sm">Apply</button>
        <button type="button" class="btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <a href="{{ route('ledger.pdf', ['account' => $account->id, 'from' => $from, 'to' => $to]) }}" class="btn-primary btn-sm" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-file-pdf"></i> Export PDF</a>
    </form>
</div>

<div id="ledger-print-header" style="display:none;">
    <h2 style="font-size:18px;font-weight:800;margin-bottom:2px;">{{ config('app.short_name') }}</h2>
    <div style="font-size:13px;margin-bottom:2px;">{{ $account->code }} — {{ $account->name }} ({{ $account->type }})</div>
    <div style="font-size:12px;color:#475569;margin-bottom:14px;">
        Period: {{ $from ? \Carbon\Carbon::parse($from)->format('M d, Y') : 'Start' }} to {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
    </div>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:110px;">Date</th>
                    <th style="width:90px;">Ref</th>
                    <th>Description</th>
                    <th>Purpose</th>
                    <th style="text-align:right;">Debit</th>
                    <th style="text-align:right;">Credit</th>
                    <th style="text-align:right;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @if($from)
                <tr style="background:#f8fafc;">
                    <td colspan="6" style="font-weight:600;color:#64748b;">Opening Balance</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($opening, 2) }}</td>
                </tr>
                @endif
                @forelse($rows as $r)
                <tr>
                    <td style="font-size:13px;color:#64748b;">{{ $r->date->format('M d, Y') }}</td>
                    <td style="font-family:monospace;font-size:12px;color:#94a3b8;">{{ $r->ref }}</td>
                    <td style="font-size:13px;color:#475569;">{{ $r->desc }}</td>
                    <td style="font-size:13px;color:#475569;">{{ $r->purpose }}</td>
                    <td style="text-align:right;">{{ $r->debit ? number_format($r->debit, 2) : '—' }}</td>
                    <td style="text-align:right;">{{ $r->credit ? number_format($r->credit, 2) : '—' }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($r->balance, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:30px;">No entries for this account in the selected period.</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot>
                <tr style="border-top:2px solid #e2e8f0;font-weight:800;">
                    <td colspan="4" style="text-align:right;">TOTALS / CLOSING BALANCE</td>
                    <td style="text-align:right;">{{ number_format($totalDebit, 2) }}</td>
                    <td style="text-align:right;">{{ number_format($totalCredit, 2) }}</td>
                    <td style="text-align:right;color:#1a3c5e;">{{ number_format($closing, 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<div id="ledger-footnote" style="margin-top:14px;font-size:12px;color:#94a3b8;">
    Balance shown on the account's natural side ({{ in_array($account->type, ['Asset','Expense']) ? 'debit-normal' : 'credit-normal' }}).
</div>

<style>
    @media print {
        .sidebar, .topbar, #ledger-toolbar, #ledger-footnote { display: none !important; }
        #ledger-print-header { display: block !important; }
        .card { box-shadow: none !important; border: none !important; }
        body { background: #fff !important; }

        /* Force the table to fit the printed page width instead of being clipped */
        .card > div { overflow-x: visible !important; width: 100% !important; }
        table.table { width: 100% !important; table-layout: fixed; font-size: 10px; }
        table.table th, table.table td { padding: 4px 6px !important; }
        table.table th { white-space: nowrap !important; }
        table.table th:nth-child(3), table.table td:nth-child(3),
        table.table th:nth-child(4), table.table td:nth-child(4) {
            white-space: normal !important;
            word-break: break-word;
        }
        table.table td:nth-child(1), table.table td:nth-child(2),
        table.table td:nth-child(5), table.table td:nth-child(6), table.table td:nth-child(7) {
            white-space: nowrap !important;
        }
        table.table th:nth-child(1), table.table td:nth-child(1) { width: 9%; }
        table.table th:nth-child(2), table.table td:nth-child(2) { width: 7%; }
        table.table th:nth-child(3), table.table td:nth-child(3) { width: 18%; }
        table.table th:nth-child(4), table.table td:nth-child(4) { width: 32%; }
        table.table th:nth-child(5), table.table td:nth-child(5) { width: 11%; }
        table.table th:nth-child(6), table.table td:nth-child(6) { width: 11%; }
        table.table th:nth-child(7), table.table td:nth-child(7) { width: 12%; }
    }
</style>

@endsection
