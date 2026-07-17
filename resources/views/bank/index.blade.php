@extends('layouts.app')

@section('title', 'Bank Transactions')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-size:20px;font-weight:700;color:#1e293b;">Bank Transactions</h2>
    <a href="{{ route('bank.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New Deposit / Withdrawal</a>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>@endif

<!-- Current balances -->
<div class="grid-3" style="margin-bottom:22px;">
    @foreach($accounts as $a)
    <div class="stat-card">
        <div class="stat-icon" style="background:{{ $a->code == '1200' ? '#dbeafe' : '#dcfce7' }};">
            <i class="fas {{ $a->code == '1200' ? 'fa-building-columns' : 'fa-money-bill-wave' }}" style="color:{{ $a->code == '1200' ? '#2563eb' : '#16a34a' }};"></i>
        </div>
        <div>
            <div class="stat-value">GHS {{ number_format($balances[$a->id] ?? 0, 2) }}</div>
            <div class="stat-label">{{ $a->name }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-right-left" style="color:#2563eb;margin-right:8px;"></i>Transfer History</div></div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>From</th>
                    <th>To</th>
                    <th style="text-align:right;">Amount (GHS)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $t)
                    @php
                        $debitLine = $t->lines->firstWhere('debit', '>', 0);
                        $creditLine = $t->lines->firstWhere('credit', '>', 0);
                        $amount = $debitLine ? $debitLine->debit : 0;
                    @endphp
                    <tr>
                        <td style="font-size:13px;color:#64748b;">{{ \Carbon\Carbon::parse($t->entry_date)->format('M d, Y') }}</td>
                        <td style="font-family:monospace;font-size:12px;color:#94a3b8;">{{ $t->reference }}</td>
                        <td style="font-size:13px;color:#475569;">{{ $t->description }}</td>
                        <td style="font-size:13px;">{{ $creditLine && $creditLine->account ? $creditLine->account->name : '—' }}</td>
                        <td style="font-size:13px;">{{ $debitLine && $debitLine->account ? $debitLine->account->name : '—' }}</td>
                        <td style="text-align:right;font-weight:700;">{{ number_format($amount, 2) }}</td>
                        <td style="text-align:right;white-space:nowrap;">
                            <a href="{{ route('bank.edit', $t) }}" style="color:#2563eb;text-decoration:none;font-size:12.5px;font-weight:600;margin-right:10px;">Edit</a>
                            <form method="POST" action="{{ route('bank.destroy', $t) }}" style="display:inline;" onsubmit="return confirm('Delete this bank transaction? This will reverse its effect on the balances.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#dc2626;font-size:12.5px;font-weight:600;cursor:pointer;padding:0;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:36px;">
                    <i class="fas fa-right-left" style="font-size:26px;color:#cbd5e1;"></i>
                    <div style="margin-top:10px;">No bank transactions yet.</div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transfers->hasPages())<div style="padding:16px 22px;">{{ $transfers->links() }}</div>@endif
</div>

@endsection