@extends('layouts.app')

@section('title', 'Confirm Harvest Links')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('harvests.link-payments') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Link payments (nothing linked yet)</a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Confirm: link {{ count($plan) }} {{ count($plan) === 1 ? 'payment' : 'payments' }}</h2>
    <div style="font-size:13px; color:#64748b;">This adds the amounts below to the campaigns' Total Paid. Finance totals and the ledger do not change.</div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-list-check" style="color:#2563eb; margin-right:8px;"></i>What will be added</div></div>
    <div class="table-wrap">
        <table class="table table-cards">
            <thead>
                <tr><th>Campaign</th><th style="text-align:right;">Payments</th><th style="text-align:right;">Amount added</th><th style="text-align:right;">New giver pledges</th><th>To check</th></tr>
            </thead>
            <tbody>
            @foreach($summary as $s)
                <tr class="summary-row" data-harvest="{{ $s['harvest']->id }}">
                    <td data-label="Campaign" style="font-weight:600;">{{ $s['harvest']->name }}</td>
                    <td data-label="Payments" style="text-align:right;">{{ $s['payments'] }}</td>
                    <td data-label="Amount added" class="money" style="text-align:right; font-weight:700;">@cedis($s['amount'])</td>
                    <td data-label="New giver pledges" style="text-align:right;">{{ $s['newGivers'] }}</td>
                    <td data-label="To check" style="font-size:12px;">
                        @if($s['nameMatch'])<span class="badge badge-warning">{{ $s['nameMatch'] }} by name: the pledge gets the member</span>@endif
                        @if($s['overpay'])<span class="badge badge-danger">{{ $s['overpay'] }} over the pledged amount</span>@endif
                        @if(! $s['nameMatch'] && ! $s['overpay'])<span style="color:#94a3b8;">—</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($skipped)
        <div style="padding:12px 24px; font-size:13px; color:#64748b;">{{ $skipped }} ticked {{ $skipped === 1 ? 'row is' : 'rows are' }} set to "Don't link" and will be left as they are.</div>
    @endif
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-receipt" style="color:#16a34a; margin-right:8px;"></i>Each payment</div></div>
    <div class="table-wrap">
        <table class="table table-cards">
            <thead><tr><th>Transaction</th><th>Payer</th><th style="text-align:right;">Amount</th><th>Campaign</th><th>Pledge</th></tr></thead>
            <tbody>
            @foreach($plan as $row)
                <tr>
                    <td data-label="Transaction" style="font-family:monospace;">{{ $row['tx']->reference }}</td>
                    <td data-label="Payer">{{ $row['tx']->member?->full_name ?? $row['tx']->payer_name }}</td>
                    <td data-label="Amount" class="money" style="text-align:right;">@cedis($row['tx']->amount)</td>
                    <td data-label="Campaign">{{ $row['harvest']->name }}</td>
                    <td data-label="Pledge">
                        @if($row['pledge'])
                            <span style="font-family:monospace;">{{ $row['pledge']->reference }}</span>
                            @if($row['nameMatch'])<span class="badge badge-warning">name match</span>@endif
                            @if($row['overpay'])<span class="badge badge-danger">over pledge</span>@endif
                        @else
                            {{ $row['option']['label'] }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<form method="POST" action="{{ route('harvests.link-payments.store') }}">
    @csrf
    <input type="hidden" name="confirmed" value="1">
    @foreach($input['selected'] as $id)
        <input type="hidden" name="selected[]" value="{{ $id }}">
    @endforeach
    @foreach($input['rows'] as $id => $r)
        <input type="hidden" name="rows[{{ $id }}][harvest_id]" value="{{ $r['harvest_id'] ?? '' }}">
        <input type="hidden" name="rows[{{ $id }}][pledge]" value="{{ $r['pledge'] ?? '' }}">
    @endforeach
    <div class="toolbar" style="justify-content:flex-start;">
        <button type="submit" class="btn-primary toolbar-main"><i class="fas fa-link"></i> Confirm and link {{ count($plan) }}</button>
        <a href="{{ route('harvests.link-payments') }}" class="btn-outline">Cancel</a>
    </div>
</form>

@endsection
