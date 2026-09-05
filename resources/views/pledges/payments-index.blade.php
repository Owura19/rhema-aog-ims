@extends('layouts.app')
@section('title', 'Pledge Payments')
@section('content')
<div style="margin-bottom:20px;">
    <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Pledge Payments</h2>
    <div style="font-size:13px; color:#64748b;">All recorded pledge payments, filterable by year</div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="text-align:center; padding:20px;">
        <div style="font-size:13px; color:#64748b; margin-bottom:4px;">{{ $summaryCount }} payment(s) in this view</div>
        <div style="font-size:24px; font-weight:700; color:#16a34a;">GHS {{ number_format($summaryTotal, 2) }}</div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('payments.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or reference..." class="form-control" style="min-width:220px;">
            </div>
            <div>
                <label class="form-label">Year</label>
                <select name="year" class="form-control" style="min-width:130px;">
                    <option value="">All years</option>
                    @foreach($years as $y)
                    <option value="{{ $y }}" {{ request('year')==$y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Purpose</label>
                <select name="purpose" class="form-control" style="min-width:170px;">
                    <option value="">All purposes</option>
                    @foreach($purposes as $purpose)
                    <option value="{{ $purpose->id }}" {{ request('purpose')==$purpose->id ? 'selected' : '' }}>{{ $purpose->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Method</label>
                <select name="method" class="form-control" style="min-width:150px;">
                    <option value="">All methods</option>
                    @foreach(['Cash','Mobile Money','Bank Transfer','Cheque','Other'] as $m)
                    <option value="{{ $m }}" {{ request('method')==$m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <a href="{{ route('payments.index') }}" class="btn-outline btn-sm" style="border-color:#e2e8f0; color:#64748b;">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Pledger</th>
                    <th>Purpose</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Pledge Ref</th>
                    <th>Receipt</th>
                    @can('edit pledges')<th>Actions</th>@endcan
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->payment_date->format('M d, Y') }}</td>
                    <td style="font-weight:600; color:#1e293b;">{{ optional($payment->pledge)->pledger_label ?? '—' }}</td>
                    <td style="font-size:13px; color:#64748b;">{{ optional(optional($payment->pledge)->purpose)->name ?? '—' }}</td>
                    <td style="font-weight:600; color:#16a34a;">GHS {{ number_format($payment->amount, 2) }}</td>
                    <td><span class="badge badge-gray">{{ $payment->payment_method }}</span></td>
                    <td>
                        @if($payment->pledge)
                        <a href="{{ route('pledges.show', $payment->pledge) }}" style="color:#4f46e5; font-size:13px;">{{ $payment->pledge->reference }}</a>
                        @else
                        —
                        @endif
                    </td>
                    <td>
                        @if($payment->transaction_id)
                        <a href="{{ route('finance.receipt.print', $payment->transaction_id) }}" target="_blank" style="color:#2563eb; font-weight:600; font-size:13px;"><i class="fas fa-print"></i> Print</a>
                        @else
                        —
                        @endif
                    </td>
                    @can('edit pledges')
                    <td style="white-space:nowrap;">
                        @if($payment->pledge)
                        <form method="POST" action="{{ route('pledges.payments.destroy', [$payment->pledge, $payment]) }}" style="display:inline;" onsubmit="return confirm('Delete this payment? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:13px;" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                        @else
                        —
                        @endif
                    </td>
                    @endcan
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:30px;">No payments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">{{ $payments->links() }}</div>
</div>
@endsection
