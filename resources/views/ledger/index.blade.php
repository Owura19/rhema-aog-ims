@extends('layouts.app')

@section('title', 'General Ledger')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <a href="{{ route('finance.reports-hub') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Financial Reports</a>
        <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-top:4px;">General Ledger</h2>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <label style="font-size:13px;color:#64748b;">As of</label>
        <input type="date" name="as_of" value="{{ $asOf }}" class="form-control" style="width:auto;">
        <button type="submit" class="btn-primary btn-sm">Apply</button>
    </form>
</div>

<div style="font-size:12.5px;color:#94a3b8;margin-bottom:14px;">Every account with its total debits, credits, and balance as of {{ \Carbon\Carbon::parse($asOf)->format('M d, Y') }}. Click an account to view its full statement.</div>

@php
    $byType = $accounts->groupBy(fn($r) => $r->account->type);
    $typeOrder = ['Asset','Liability','Equity','Income','Expense'];
@endphp

@foreach($typeOrder as $type)
    @if($byType->has($type))
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div class="card-title">{{ $type }} Accounts</div></div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:90px;">Code</th>
                        <th>Account</th>
                        <th style="text-align:right;">Total Debit</th>
                        <th style="text-align:right;">Total Credit</th>
                        <th style="text-align:right;">Balance (GHS)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byType->get($type) as $row)
                    <tr style="{{ $row->active ? '' : 'opacity:.55;' }}">
                        <td style="font-family:monospace;font-size:12.5px;color:#64748b;">{{ $row->account->code }}</td>
                        <td style="font-weight:600;color:#1e293b;">{{ $row->account->name }}</td>
                        <td style="text-align:right;">{{ $row->debit ? number_format($row->debit, 2) : '—' }}</td>
                        <td style="text-align:right;">{{ $row->credit ? number_format($row->credit, 2) : '—' }}</td>
                        <td style="text-align:right;font-weight:700;">{{ number_format($row->balance, 2) }}</td>
                        <td style="text-align:right;"><a href="{{ route('ledger.show', $row->account) }}" style="color:#2563eb;text-decoration:none;font-size:12.5px;font-weight:600;">Statement</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endforeach

@endsection