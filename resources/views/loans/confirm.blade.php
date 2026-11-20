@extends('layouts.app')

@section('title', 'Confirm New Loan')

@php
    $acct = fn ($id) => ($a = $accounts->get((int) $id)) ? "{$a->code} — {$a->name}" : null;
    $counter = $data['opening_counter_account_id'] === 'opening_equity' ? '3200 — Opening Balance Equity (created now)' : $acct($data['opening_counter_account_id']);
@endphp

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('loans.create') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back (nothing saved yet)</a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Confirm: loan from {{ $data['lender'] }}</h2>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-book" style="color:#1a3c5e; margin-right:8px;"></i>Ledger entry for the opening balance</div></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Account</th><th style="text-align:right;">Debit</th><th style="text-align:right;">Credit</th></tr></thead>
            <tbody>
                <tr><td>{{ \Carbon\Carbon::parse($data['opening_date'])->format('d M Y') }}</td><td>{{ $counter }}</td><td class="money" style="text-align:right;">@cedis($data['opening_balance'])</td><td></td></tr>
                <tr><td></td><td>{{ $acct($data['liability_account_id']) }}</td><td></td><td class="money" style="text-align:right;">@cedis($data['opening_balance'])</td></tr>
            </tbody>
        </table>
    </div>
    <div style="padding:12px 24px; font-size:13px; color:#475569;">
        From {{ \Carbon\Carbon::parse($data['repayment_start_date'])->format('d M Y') }}, every Confirmed {{ implode(' and ', $data['counted_types']) }} transaction repays this loan,
        paid from {{ $data['repayment_source'] === 'fixed' ? $acct($data['repayment_account_id']) : 'the account each payment was received into' }}.
        Income totals don't change.
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-magnifying-glass" style="color:#2563eb; margin-right:8px;"></i>Payments that already count ({{ $existing->count() }})</div></div>
    @if($existing->isEmpty())
        <div style="padding:16px 24px; font-size:13px; color:#64748b;">None yet: no counted transactions are dated on or after the start date.</div>
    @else
        <div style="padding:12px 24px; font-size:13px; color:#b45309;">
            These {{ $existing->count() }} transactions (@cedis($existing->sum('amount')) in total) are already on or after the start date. They will be counted as repayments when you confirm.
        </div>
        <div class="table-wrap">
            <table class="table table-cards">
                <thead><tr><th>Transaction</th><th>Date</th><th>Type</th><th style="text-align:right;">Amount</th></tr></thead>
                <tbody>
                @foreach($existing as $tx)
                    <tr>
                        <td data-label="Transaction" style="font-family:monospace;">{{ $tx->reference }}</td>
                        <td data-label="Date">{{ $tx->transaction_date->format('d M Y') }}</td>
                        <td data-label="Type">{{ $tx->type }}</td>
                        <td data-label="Amount" class="money" style="text-align:right;">@cedis($tx->amount)</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<form method="POST" action="{{ route('loans.store') }}">
    @csrf
    <input type="hidden" name="confirmed" value="1">
    @foreach($input as $name => $value)
        @if(is_array($value))
            @foreach($value as $v)<input type="hidden" name="{{ $name }}[]" value="{{ $v }}">@endforeach
        @else
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach
    <div class="toolbar" style="justify-content:flex-start;">
        <button type="submit" class="btn-primary toolbar-main"><i class="fas fa-check"></i> Confirm and record the loan</button>
        <a href="{{ route('loans.index') }}" class="btn-outline">Cancel</a>
    </div>
</form>

@endsection
