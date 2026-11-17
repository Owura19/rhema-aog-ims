@extends('layouts.app')

@section('title', 'Link Harvest Payments')

@php
    $money = fn ($n) => \App\Support\Cedis::format($n);
    $optionLabel = fn (array $o) => $o['value'] === 'giver'
        ? $o['label']
        : $o['reference'] . ' · ' . $money($o['balance']) . ' left' . ($o['match'] === 'name' ? ' · by name' : '');
    $flagStyle = ['overpay' => 'badge-danger', 'no_payer' => 'badge-danger', 'no_campaign' => 'badge-danger', 'two_years' => 'badge-danger'];
@endphp

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <a href="{{ route('harvests.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Harvest Campaigns</a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Link harvest payments</h2>
        <div style="font-size:13px; color:#64748b; max-width:760px;">
            Harvest money recorded in Finance that is not yet on a pledge. The campaign and pledge are suggestions:
            check them, change any that are wrong, tick the rows and link them. Nothing changes in Finance:
            each link points at the existing transaction.
        </div>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:16px;">
        <i class="fas fa-exclamation-circle"></i> Nothing was linked:
        <ul style="margin:6px 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<!-- Filters -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:16px 24px;">
        <form method="GET" action="{{ route('harvests.link-payments') }}" class="filter-bar">
            <select name="year" class="form-control" style="width:auto;" aria-label="Year">
                <option value="">All years</option>
                @foreach($years as $y)<option value="{{ $y }}" {{ (string) ($filters['year'] ?? '') === (string) $y ? 'selected' : '' }}>{{ $y }}</option>@endforeach
            </select>
            <select name="harvest" class="form-control" style="width:auto;" aria-label="Suggested campaign">
                <option value="">All suggested campaigns</option>
                @foreach($harvests as $h)<option value="{{ $h->id }}" {{ ($filters['harvest'] ?? '') === (string) $h->id ? 'selected' : '' }}>{{ $h->name }}</option>@endforeach
                <option value="none" {{ ($filters['harvest'] ?? '') === 'none' ? 'selected' : '' }}>No campaign suggested</option>
            </select>
            <select name="flag" class="form-control" style="width:auto;" aria-label="Flag">
                <option value="">All rows</option>
                <option value="clean" {{ ($filters['flag'] ?? '') === 'clean' ? 'selected' : '' }}>No flags</option>
                @foreach($flags as $key => $label)<option value="{{ $key }}" {{ ($filters['flag'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
            @if(array_filter($filters))<a href="{{ route('harvests.link-payments') }}" class="btn-outline">Clear</a>@endif
        </form>
    </div>
</div>

<!-- Waiting to be linked -->
<form method="POST" action="{{ route('harvests.link-payments.preview') }}" id="link-form">
@csrf
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-link" style="color:#2563eb; margin-right:8px;"></i>Waiting to be linked ({{ $total }}, of which {{ $clean }} without flags)</div>
        <div class="toolbar">
            <button type="button" class="btn-outline btn-sm" id="select-clean"><i class="fas fa-check-double"></i> Select all rows without flags</button>
            <button type="submit" class="btn-primary btn-sm toolbar-main"><i class="fas fa-link"></i> Link selected</button>
        </div>
    </div>

    @if($rows->isEmpty())
        <div style="text-align:center; padding:40px; color:#94a3b8;">Nothing waiting to be linked{{ array_filter($filters) ? ' for these filters' : '' }}.</div>
    @else
    <div class="table-wrap">
        <table class="table table-cards review-table">
            <thead>
                <tr>
                    <th style="width:32px;"><input type="checkbox" id="check-all" aria-label="Tick every row on this page"></th>
                    <th>Transaction</th>
                    <th>Payer</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Campaign</th>
                    <th>Pledge</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $s)
                @php
                    $tx = $s->transaction;
                    $selHarvest = old("rows.{$tx->id}.harvest_id", $s->harvest?->id);
                    $selPledge  = old("rows.{$tx->id}.pledge", $s->choice());
                    $opts = collect($s->optionsByHarvest)->map(fn ($list) => collect($list)->map(fn ($o) => ['value' => $o['value'], 'label' => $optionLabel($o)])->values());
                @endphp
                <tr class="review-row" data-options='@json($opts)' data-choices='@json($s->choiceByHarvest)'>
                    <td data-label="Link">
                        <input type="checkbox" name="selected[]" value="{{ $tx->id }}" class="row-check" data-clean="{{ $s->isClean() ? 1 : 0 }}"
                               aria-label="Link {{ $tx->reference }}" {{ in_array((string) $tx->id, array_map('strval', old('selected', [])), true) ? 'checked' : '' }}>
                    </td>
                    <td data-label="Transaction" style="max-width:240px;">
                        <a href="{{ route('finance.show', $tx) }}" style="font-family:monospace; font-weight:600;">{{ $tx->reference }}</a>
                        <span style="font-size:12px; color:#64748b; white-space:nowrap;">· {{ $tx->transaction_date->format('d M Y') }}</span>
                        <div style="font-size:12px; color:#475569; margin-top:2px;" title="Description">{{ $tx->description ?: '—' }}</div>
                        @foreach($s->flags() as $flag)
                            <span class="badge {{ $flagStyle[$flag] ?? 'badge-warning' }} flag-badge">{{ $flags[$flag] }}</span>
                        @endforeach
                    </td>
                    <td data-label="Payer">{{ $tx->member?->full_name ?? $tx->payer_name ?? '—' }}</td>
                    <td data-label="Amount" class="money" style="text-align:right; font-weight:700;">@cedis($tx->amount)</td>
                    <td data-label="Campaign">
                        <select name="rows[{{ $tx->id }}][harvest_id]" class="form-control campaign-select" style="min-width:160px;" aria-label="Campaign for {{ $tx->reference }}">
                            <option value="">Choose…</option>
                            @foreach($harvests as $h)
                                <option value="{{ $h->id }}" {{ (string) $selHarvest === (string) $h->id ? 'selected' : '' }}>{{ $h->name }}</option>
                            @endforeach
                        </select>
                        @if($s->harvest)
                            <div style="font-size:11px; color:#94a3b8; margin-top:2px;">{{ $s->basis === 'description' ? 'from the description' : 'by date: check' }}</div>
                        @endif
                    </td>
                    <td data-label="Pledge">
                        <select name="rows[{{ $tx->id }}][pledge]" class="form-control pledge-select" style="min-width:200px;" aria-label="Pledge for {{ $tx->reference }}">
                            <option value="">Choose…</option>
                            @foreach($selHarvest ? ($s->optionsByHarvest[$selHarvest] ?? []) : [] as $o)
                                <option value="{{ $o['value'] }}" {{ (string) $selPledge === (string) $o['value'] ? 'selected' : '' }}>{{ $optionLabel($o) }}</option>
                            @endforeach
                            <option value="none" {{ $selPledge === 'none' ? 'selected' : '' }}>Don't link</option>
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($rows->hasPages())
        <div style="padding:16px 24px;">{{ $rows->links() }}</div>
    @endif
    @endif
</div>
</form>

<!-- Linked on this page (can be undone) -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-rotate-left" style="color:#16a34a; margin-right:8px;"></i>Linked on this page ({{ $linked->count() }})</div>
    </div>
    @if($linked->isEmpty())
        <div style="padding:20px 24px; color:#94a3b8; font-size:13px;">Links made here appear in this list and can be undone.</div>
    @else
    <div class="table-wrap">
        <table class="table table-cards">
            <thead><tr><th>Transaction</th><th>Payer</th><th style="text-align:right;">Amount</th><th>Pledge</th><th>Campaign</th><th></th></tr></thead>
            <tbody>
            @foreach($linked as $p)
                <tr>
                    <td data-label="Transaction"><a href="{{ route('finance.show', $p->transaction_id) }}" style="font-family:monospace;">{{ $p->transaction?->reference }}</a></td>
                    <td data-label="Payer">{{ $p->transaction?->member?->full_name ?? $p->transaction?->payer_name ?? '—' }}</td>
                    <td data-label="Amount" class="money" style="text-align:right;">@cedis($p->amount)</td>
                    <td data-label="Pledge"><a href="{{ route('pledges.show', $p->pledge_id) }}" style="font-family:monospace;">{{ $p->pledge?->reference }}</a>@if($p->pledge?->isGiver()) <span class="badge badge-info">giver</span>@endif</td>
                    <td data-label="Campaign">{{ $p->pledge?->harvest?->name }}</td>
                    <td data-label="">
                        <form method="POST" action="{{ route('harvests.link-payments.unlink', $p) }}" onsubmit="return confirm('Unlink {{ $p->transaction?->reference }}? The pledge payment is removed; the Finance transaction stays.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-outline btn-sm"><i class="fas fa-link-slash"></i> Unlink</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<!-- Pledge status checks (report only) -->
<details class="card" style="margin-bottom:20px;">
    <summary class="card-header" style="cursor:pointer; list-style:none;">
        <div class="card-title"><i class="fas fa-clipboard-check" style="color:#7c3aed; margin-right:8px;"></i>Checks: pledges whose status doesn't match their payments ({{ collect($checks)->sum->count() }})</div>
    </summary>
    <div class="card-body" style="font-size:13px;">
        <p style="color:#64748b; margin-bottom:12px;">Report only: nothing here is changed automatically. Open a pledge to correct it.</p>
        @foreach(\App\Services\Harvest\PledgeStatusChecks::CHECKS as $key => $label)
            <div style="margin-bottom:10px;">
                <strong>{{ $label }}: {{ $checks[$key]->count() }}</strong>
                @if($checks[$key]->isNotEmpty())
                    <div style="margin-top:4px; line-height:1.9;">
                        @foreach($checks[$key] as $pl)<a href="{{ route('pledges.show', $pl) }}" style="font-family:monospace; margin-right:8px;">{{ $pl->reference }}</a>@endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</details>

<style>
    .review-table td { vertical-align: top; }
    .review-table th, .review-table td { padding-left: 10px; padding-right: 10px; }
    .review-table .flag-badge { display: block; white-space: normal; line-height: 1.3; margin: 4px 0 0; width: max-content; max-width: 100%; }
    @media (max-width: 640px) {
        .review-table td[data-label="Link"] { order: -1; }
        .review-table select { min-width: 0 !important; width: 100%; }
    }
</style>

<script>
(function () {
    document.querySelectorAll('.review-row').forEach(function (row) {
        const options  = JSON.parse(row.dataset.options || '{}');
        const choices  = JSON.parse(row.dataset.choices || '{}');
        const campaign = row.querySelector('.campaign-select');
        const pledge   = row.querySelector('.pledge-select');

        campaign.addEventListener('change', function () {
            const list = options[campaign.value] || [];
            const pick = choices[campaign.value] ?? '';
            pledge.innerHTML = '';
            pledge.add(new Option('Choose…', ''));
            list.forEach(o => pledge.add(new Option(o.label, o.value, false, String(o.value) === String(pick))));
            pledge.add(new Option("Don't link", 'none'));
            // a changed campaign is no longer the suggestion: leave it to the person to tick
            row.querySelector('.row-check').dataset.clean = '0';
        });
        pledge.addEventListener('change', () => { row.querySelector('.row-check').dataset.clean = '0'; });
    });

    const checks = () => document.querySelectorAll('.row-check');
    document.getElementById('select-clean')?.addEventListener('click', function () {
        checks().forEach(c => { if (c.dataset.clean === '1') c.checked = true; });
    });
    document.getElementById('check-all')?.addEventListener('change', function () {
        checks().forEach(c => { c.checked = this.checked; });
    });
})();
</script>

@endsection
