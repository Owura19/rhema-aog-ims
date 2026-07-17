@extends('layouts.app')

@section('title', 'Ledger — ' . $account->name)

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <a href="{{ route('ledger.index') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to General Ledger</a>
        <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-top:4px;">{{ $account->code }} — {{ $account->name }}</h2>
        <div style="font-size:12.5px;color:#94a3b8;">Account Statement · {{ $account->type }}</div>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
        <div><label style="font-size:12px;color:#64748b;display:block;">From</label><input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto;"></div>
        <div><label style="font-size:12px;color:#64748b;display:block;">To</label><input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto;"></div>
        <button type="submit" class="btn-primary btn-sm">Apply</button>
    </form>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:110px;">Date</th>
                    <th style="width:90px;">Ref</th>
                    <th>Description</th>
                    <th style="text-align:right;">Debit</th>
                    <th style="text-align:right;">Credit</th>
                    <th style="text-align:right;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @if($from)
                <tr style="background:#f8fafc;">
                    <td colspan="5" style="font-weight:600;color:#64748b;">Opening Balance</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($opening, 2) }}</td>
                </tr>
                @endif
                @forelse($rows as $r)
                <tr>
                    <td style="font-size:13px;color:#64748b;">{{ $r->date->format('M d, Y') }}</td>
                    <td style="font-family:monospace;font-size:12px;color:#94a3b8;">{{ $r->ref }}</td>
                    <td style="font-size:13px;color:#475569;">{{ $r->desc }}</td>
                    <td style="text-align:right;">{{ $r->debit ? number_format($r->debit, 2) : '—' }}</td>
                    <td style="text-align:right;">{{ $r->credit ? number_format($r->credit, 2) : '—' }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($r->balance, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:30px;">No entries for this account in the selected period.</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot>
                <tr style="border-top:2px solid #e2e8f0;font-weight:800;">
                    <td colspan="3" style="text-align:right;">TOTALS / CLOSING BALANCE</td>
                    <td style="text-align:right;">{{ number_format($totalDebit, 2) }}</td>
                    <td style="text-align:right;">{{ number_format($totalCredit, 2) }}</td>
                    <td style="text-align:right;color:#1a3c5e;">{{ number_format($closing, 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<div style="margin-top:14px;font-size:12px;color:#94a3b8;">
    Balance shown on the account's natural side ({{ in_array($account->type, ['Asset','Expense']) ? 'debit-normal' : 'credit-normal' }}).
</div>

@endsection