@extends('layouts.app')

@section('title', $loan->lender . ' loan')

@php
    $balance  = $loan->balance();
    $repaid   = $loan->repaidTotal();
    $owed     = $loan->owedTotal();
    $surplus  = $loan->surplus();
    $progress = $loan->progressPercent();
    $flagged  = $repayments->where('flag', \App\Models\LoanRepayment::FLAG_NO_LEDGER)->count();
    $ledgerOk = abs($ledgerBalance - $balance) < 0.005;
    $actions  = ['opened' => 'Loan recorded', 'edited' => 'Loan edited', 'repayment_added' => 'Repayment counted', 'repayment_changed' => 'Repayment changed',
                 'repayment_removed' => 'Repayment removed', 'paid_off' => 'Paid off', 'reopened' => 'Reopened', 'corrected' => 'Correction', 'manual_repayment' => 'Manual repayment'];
@endphp

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <a href="{{ route('loans.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Loans</a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $loan->lender }}
            <span class="badge {{ $loan->status === 'Active' ? 'badge-warning' : 'badge-success' }}" style="font-size:12px; vertical-align:middle;">{{ $loan->status }}{{ $loan->paid_off_at ? ' · ' . $loan->paid_off_at->format('d M Y') : '' }}</span>
        </h2>
        @if($loan->description)<div style="font-size:13px; color:#64748b;">{{ $loan->description }}</div>@endif
    </div>
    <div class="toolbar">
        <a href="{{ route('loans.export', $loan) }}" class="btn-outline btn-sm"><i class="fas fa-file-excel"></i> Export Excel</a>
        @can('edit finance')
            <form method="POST" action="{{ route('loans.recalculate', $loan) }}">@csrf
                <button type="submit" class="btn-outline btn-sm" title="Recount every payment from the start date"><i class="fas fa-rotate"></i> Recalculate</button>
            </form>
            <a href="{{ route('loans.edit', $loan) }}" class="btn-primary btn-sm toolbar-main"><i class="fas fa-pen"></i> Edit</a>
        @endcan
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:16px;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#e0e7ff;"><i class="fas fa-landmark" style="color:#4338ca;"></i></div>
        <div><div class="stat-value stat-money">@cedis($owed)</div><div class="stat-label">Owed{{ abs($owed - (float) $loan->opening_balance) > 0.004 ? ' (incl. corrections)' : '' }}</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;"><i class="fas fa-arrow-down" style="color:#16a34a;"></i></div>
        <div><div class="stat-value stat-money" style="color:#16a34a;">@cedis($repaid)</div><div class="stat-label">Repaid to date</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;"><i class="fas fa-scale-balanced" style="color:#dc2626;"></i></div>
        <div><div class="stat-value stat-money">@cedis($balance)</div><div class="stat-label">Balance remaining</div></div>
    </div>
    @if($surplus > 0)
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;"><i class="fas fa-coins" style="color:#2563eb;"></i></div>
        <div><div class="stat-value stat-money">@cedis($surplus)</div><div class="stat-label">Surplus (not needed, kept)</div></div>
    </div>
    @endif
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;"><span>Repaid</span><strong>{{ $progress }}%</strong></div>
        <div style="background:#e2e8f0; border-radius:8px; overflow:hidden; height:14px;" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
            <div style="background:#16a34a; height:14px; width:{{ $progress }}%;"></div>
        </div>
    </div>
</div>

@if(! $ledgerOk || $flagged)
<div class="alert alert-danger" style="margin-bottom:20px;">
    <i class="fas fa-triangle-exclamation"></i>
    @if($flagged)
        {{ $flagged }} counted {{ $flagged === 1 ? 'payment is' : 'payments are' }} not in the ledger (no "Paid To / From" account): open the transaction, Edit, and choose the account.
    @endif
    @if(! $ledgerOk)
        The ledger shows @cedis($ledgerBalance) owed on {{ $loan->liabilityAccount?->code }}, not @cedis($balance).
        @if(! $flagged) Use Recalculate; if it stays, check for other entries on that account. @endif
    @endif
</div>
@endif

<div class="grid-2" style="margin-bottom:20px;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-circle-info" style="color:#2563eb; margin-right:8px;"></i>How it works</div></div>
        <div class="card-body" style="font-size:14px; line-height:1.7;">
            <div><strong>Opening:</strong> @cedis($loan->opening_balance) owed as at {{ $loan->opening_date->format('d M Y') }}, set against {{ $loan->counterAccount?->code }} {{ $loan->counterAccount?->name }}</div>
            <div><strong>Counted:</strong> every Confirmed {{ implode(' and ', $loan->counted_types) }} transaction from {{ $loan->repayment_start_date->format('d M Y') }}</div>
            <div><strong>Paid from:</strong>
                @if($loan->repayment_source === \App\Models\Loan::SOURCE_FIXED)
                    {{ $loan->repaymentAccount?->code }} {{ $loan->repaymentAccount?->name }} (Finance records moving the money into it)
                @else
                    the account each payment was received into
                @endif
            </div>
            <div><strong>Liability account:</strong> {{ $loan->liabilityAccount?->code }} {{ $loan->liabilityAccount?->name }}
                · ledger {{ $ledgerOk ? 'matches' : 'differs' }} <i class="fas fa-{{ $ledgerOk ? 'check' : 'xmark' }}" style="color:{{ $ledgerOk ? '#16a34a' : '#dc2626' }};"></i></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-calendar" style="color:#7c3aed; margin-right:8px;"></i>Repaid per month</div></div>
        @if($monthly->isEmpty())
            <div style="padding:20px 24px; color:#94a3b8; font-size:13px;">No repayments yet.</div>
        @else
        <div class="table-wrap">
            <table class="table table-cards">
                <thead><tr><th>Month</th><th style="text-align:right;">Payments</th><th style="text-align:right;">Applied</th></tr></thead>
                <tbody>
                @foreach($monthly as $m)
                    <tr><td data-label="Month">{{ \Carbon\Carbon::parse($m['month'] . '-01')->format('F Y') }}</td><td data-label="Payments" style="text-align:right;">{{ $m['count'] }}</td><td data-label="Applied" class="money" style="text-align:right;">@cedis($m['applied'])</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-list" style="color:#16a34a; margin-right:8px;"></i>Counted payments ({{ $repayments->count() }})</div></div>
    @if($repayments->isEmpty())
        <div style="padding:20px 24px; color:#94a3b8; font-size:13px;">None yet. Confirmed {{ implode(' and ', $loan->counted_types) }} transactions from {{ $loan->repayment_start_date->format('d M Y') }} appear here.</div>
    @else
    <div class="table-wrap">
        <table class="table table-cards">
            <thead><tr><th>Transaction</th><th>Date</th><th>Type</th><th style="text-align:right;">Amount</th><th style="text-align:right;">Applied</th><th>Note</th></tr></thead>
            <tbody>
            @foreach($repayments as $r)
                <tr>
                    <td data-label="Transaction"><a href="{{ route('finance.show', $r->transaction_id) }}" style="font-family:monospace;">{{ $r->transaction?->reference }}</a></td>
                    <td data-label="Date">{{ $r->transaction?->transaction_date?->format('d M Y') }}</td>
                    <td data-label="Type">{{ $r->transaction?->type }}</td>
                    <td data-label="Amount" class="money" style="text-align:right;">@cedis($r->transaction_amount)</td>
                    <td data-label="Applied" class="money" style="text-align:right; font-weight:700;">@cedis($r->amount_applied)</td>
                    <td data-label="Note" style="font-size:12px;">
                        @if((float) $r->surplus > 0)<span class="badge badge-info">@cedis($r->surplus) surplus</span>@endif
                        @if($r->flag)<span class="badge badge-warning">not in the ledger</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-sliders" style="color:#b45309; margin-right:8px;"></i>Adjustments ({{ $adjustments->count() }})</div></div>
    @if($adjustments->isNotEmpty())
    <div class="table-wrap">
        <table class="table table-cards">
            <thead><tr><th>Date</th><th>Kind</th><th style="text-align:right;">Amount</th><th>Reason</th><th>By</th></tr></thead>
            <tbody>
            @foreach($adjustments as $a)
                <tr>
                    <td data-label="Date">{{ $a->adjustment_date->format('d M Y') }}</td>
                    <td data-label="Kind">{{ $a->kind === 'repayment' ? 'Manual repayment' : ((float) $a->amount > 0 ? 'Correction: owed more' : 'Correction: owed less') }}</td>
                    <td data-label="Amount" class="money" style="text-align:right;">@cedis(abs((float) $a->amount))</td>
                    <td data-label="Reason">{{ $a->reason }}</td>
                    <td data-label="By" style="font-size:12px; color:#64748b;">{{ $a->createdBy?->name }} · {{ $a->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @can('edit finance')
    <details style="padding:16px 24px;" {{ $errors->hasAny(['kind', 'amount', 'reason', 'adjustment_date', 'counter_account_id']) ? 'open' : '' }}>
        <summary style="cursor:pointer; font-weight:600; color:#1a3c5e;">Add an adjustment (no interest; a reason is required)</summary>
        <form method="POST" action="{{ route('loans.adjust', $loan) }}" style="margin-top:12px;">
            @csrf
            <div class="grid-2">
                <div>
                    <label class="form-label">Kind</label>
                    <select name="kind" class="form-control" id="adj-kind">
                        <option value="owed_more" {{ old('kind') === 'owed_more' ? 'selected' : '' }}>Correction: more is owed</option>
                        <option value="owed_less" {{ old('kind') === 'owed_less' ? 'selected' : '' }}>Correction: less is owed</option>
                        <option value="repayment" {{ old('kind') === 'repayment' ? 'selected' : '' }}>Repayment made some other way</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Amount (GH₵)</label>
                    <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Date</label>
                    <input type="date" name="adjustment_date" value="{{ old('adjustment_date', now()->toDateString()) }}" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Account <small style="color:#94a3b8;">(corrections: Opening Balance Equity; a repayment: the account it was paid from)</small></label>
                    <select name="counter_account_id" class="form-control" required>
                        <optgroup label="Equity">
                            @foreach($equityAccounts as $value => $label)
                                <option value="{{ $value }}" {{ (string) old('counter_account_id', $loan->opening_counter_account_id) === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Cash and bank">
                            @foreach($cashAccounts as $c)
                                <option value="{{ $c->id }}" {{ (string) old('counter_account_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->code }} — {{ $c->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <div style="grid-column:1 / -1;">
                    <label class="form-label">Reason <span style="color:red;">*</span></label>
                    <input type="text" name="reason" value="{{ old('reason') }}" class="form-control" required minlength="5" maxlength="500" placeholder="e.g. Credit union statement of 30 Nov shows GH₵ … owed">
                </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:12px;"><i class="fas fa-check"></i> Record adjustment</button>
        </form>
    </details>
    @endcan
</div>

<details class="card" style="margin-bottom:20px;">
    <summary class="card-header" style="cursor:pointer; list-style:none;"><div class="card-title"><i class="fas fa-clock-rotate-left" style="color:#64748b; margin-right:8px;"></i>History (latest {{ $events->count() }})</div></summary>
    <div class="table-wrap">
        <table class="table table-cards">
            <thead><tr><th>When</th><th>What</th><th>Transaction</th><th style="text-align:right;">Amount</th><th>Who</th></tr></thead>
            <tbody>
            @foreach($events as $e)
                <tr>
                    <td data-label="When" style="white-space:nowrap;">{{ $e->created_at?->format('d M Y H:i') }}</td>
                    <td data-label="What">{{ $actions[$e->action] ?? $e->action }}@if($e->note) <span style="color:#64748b; font-size:12px;">· {{ $e->note }}</span>@endif</td>
                    <td data-label="Transaction" style="font-family:monospace;">{{ $e->transaction?->reference }}</td>
                    <td data-label="Amount" class="money" style="text-align:right;">@if($e->amount !== null)@cedis($e->amount)@endif</td>
                    <td data-label="Who">{{ $e->user?->name ?? 'System' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</details>

@endsection
