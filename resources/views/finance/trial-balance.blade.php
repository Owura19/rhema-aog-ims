@extends('layouts.app')

@section('title', 'Trial Balance')

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('finance.reports-hub') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Financial Reports</a>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-scale-balanced" style="color:#2563eb;margin-right:8px;"></i>Trial Balance</div>
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <label style="font-size:13px;color:#64748b;">As of</label>
            <input type="date" name="as_of" value="{{ $asOf }}" class="form-control" style="width:auto;padding:6px 10px;">
            <button type="submit" class="btn-primary btn-sm">Apply</button>
        </form>
    </div>

    <div class="card-body" style="padding-top:8px;">
        <div style="font-size:12.5px;color:#94a3b8;margin-bottom:12px;">All accounts with journal activity as of {{ \Carbon\Carbon::parse($asOf)->format('M d, Y') }}.</div>

        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account</th>
                        <th>Type</th>
                        <th style="text-align:right;">Debit (GHS)</th>
                        <th style="text-align:right;">Credit (GHS)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $row)
                    <tr>
                        <td style="font-family:monospace;font-size:12.5px;color:#64748b;">{{ $row->account->code }}</td>
                        <td style="font-weight:600;color:#1e293b;">{{ $row->account->name }}</td>
                        <td><span class="badge badge-gray" style="font-size:11px;">{{ $row->account->type }}</span></td>
                        <td style="text-align:right;">{{ $row->debit > 0 ? number_format($row->debit, 2) : '—' }}</td>
                        <td style="text-align:right;">{{ $row->credit > 0 ? number_format($row->credit, 2) : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:30px;">No journal activity yet. Record confirmed transactions with a cash/bank account to populate the ledger.</td></tr>
                    @endforelse
                </tbody>
                @if($accounts->isNotEmpty())
                <tfoot>
                    <tr style="border-top:2px solid #e2e8f0;font-weight:800;">
                        <td colspan="3" style="text-align:right;color:#1e293b;">TOTALS</td>
                        <td style="text-align:right;color:#1e293b;">{{ number_format($totalDebit, 2) }}</td>
                        <td style="text-align:right;color:#1e293b;">{{ number_format($totalCredit, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        @if($accounts->isNotEmpty())
        <div style="margin-top:16px;text-align:center;">
            @if($balanced)
                <span class="badge badge-success" style="font-size:13px;padding:6px 16px;"><i class="fas fa-circle-check"></i> In balance — debits equal credits</span>
            @else
                <span class="badge badge-danger" style="font-size:13px;padding:6px 16px;"><i class="fas fa-triangle-exclamation"></i> Out of balance — please review</span>
            @endif
        </div>
        @endif
    </div>
</div>

@endsection