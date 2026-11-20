@extends('layouts.app')

@section('title', 'Loans')

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-landmark" style="color:#1a3c5e; margin-right:8px;"></i>Loans</div>
        @can('edit finance')
        <div class="toolbar">
            <a href="{{ route('loans.create') }}" class="btn-primary btn-sm toolbar-main"><i class="fas fa-plus"></i> New Loan</a>
        </div>
        @endcan
    </div>
    @if($loans->isEmpty())
        <div style="text-align:center; padding:40px; color:#94a3b8;">No loans recorded.</div>
    @else
    <div class="table-wrap">
        <table class="table table-cards">
            <thead>
                <tr><th>Lender</th><th>Counted from</th><th style="text-align:right;">Opening</th><th style="text-align:right;">Repaid</th><th style="text-align:right;">Balance</th><th>Status</th></tr>
            </thead>
            <tbody>
            @foreach($loans as $loan)
                <tr>
                    <td data-label="Lender"><a href="{{ route('loans.show', $loan) }}" style="font-weight:600;">{{ $loan->lender }}</a>
                        <div style="font-size:12px; color:#64748b;">{{ implode(' + ', $loan->counted_types) }}</div></td>
                    <td data-label="Counted from">{{ $loan->repayment_start_date->format('d M Y') }}</td>
                    <td data-label="Opening" class="money" style="text-align:right;">@cedis($loan->opening_balance)</td>
                    <td data-label="Repaid" class="money" style="text-align:right; color:#16a34a;">@cedis($loan->repaidTotal())</td>
                    <td data-label="Balance" class="money" style="text-align:right; font-weight:700;">@cedis($loan->balance())</td>
                    <td data-label="Status"><span class="badge {{ $loan->status === 'Active' ? 'badge-warning' : 'badge-success' }}">{{ $loan->status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
