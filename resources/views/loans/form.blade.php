@extends('layouts.app')

@section('title', $loan ? 'Edit Loan' : 'New Loan')

@php
    $val = fn (string $field, $default = null) => old($field, $default);
    $chosen = (array) old('counted_types', $defaults['types']);
    $source = old('repayment_source', $defaults['source']);
@endphp

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ $loan ? route('loans.show', $loan) : route('loans.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> {{ $loan ? $loan->lender : 'Loans' }}</a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $loan ? 'Edit loan' : 'New loan' }}</h2>
    @if(! $loan)
        <div style="font-size:13px; color:#64748b; max-width:760px;">Enter what the church owes. Before anything is saved you'll see the ledger entry and any payments that already count.</div>
    @endif
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:16px;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $loan ? route('loans.update', $loan) : route('loans.preview') }}">
@csrf
@if($loan) @method('PUT') @endif

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-landmark" style="color:#1a3c5e; margin-right:8px;"></i>The loan</div></div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <label class="form-label">Lender <span style="color:red;">*</span></label>
                <input type="text" name="lender" value="{{ $val('lender', $loan?->lender) }}" class="form-control" placeholder="e.g. the credit union's name" required>
            </div>
            <div>
                <label class="form-label">Liability account <span style="color:red;">*</span></label>
                <select name="liability_account_id" class="form-control" required>
                    @foreach($liabilities as $a)
                        <option value="{{ $a->id }}" {{ (string) $val('liability_account_id', $defaults['liability']) === (string) $a->id ? 'selected' : '' }}>{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
                <small style="color:#94a3b8; font-size:12px;">Each loan has its own liability account.</small>
            </div>
            <div style="grid-column:1 / -1;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="What the loan was for, the agreement reference…">{{ $val('description', $loan?->description) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-flag-checkered" style="color:#16a34a; margin-right:8px;"></i>Opening balance</div></div>
    <div class="card-body">
        <div class="grid-3">
            <div>
                <label class="form-label">Amount owed (GH₵) <span style="color:red;">*</span></label>
                <input type="number" name="opening_balance" value="{{ $val('opening_balance', $loan?->opening_balance) }}" step="0.01" min="0.01" class="form-control" required>
            </div>
            <div>
                <label class="form-label">As at <span style="color:red;">*</span></label>
                <input type="date" name="opening_date" value="{{ $val('opening_date', $defaults['opening']) }}" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Set against <span style="color:red;">*</span></label>
                <select name="opening_counter_account_id" class="form-control" required>
                    @foreach($counters as $value => $label)
                        <option value="{{ $value }}" {{ (string) $val('opening_counter_account_id', $defaults['counter']) === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <small style="color:#94a3b8; font-size:12px;">Keep Opening Balance Equity unless the loan money is recorded elsewhere (a bank account it was paid into, or what it bought).</small>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-rotate" style="color:#2563eb; margin-right:8px;"></i>Automatic repayments</div></div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <label class="form-label">Counted from <span style="color:red;">*</span></label>
                <input type="date" name="repayment_start_date" value="{{ $val('repayment_start_date', $defaults['start']) }}" class="form-control" required>
                <small style="color:#94a3b8; font-size:12px;">Every Confirmed transaction of the types below, dated on or after this day, repays the loan.</small>
            </div>
            <div>
                <label class="form-label">Transaction types that repay it <span style="color:red;">*</span></label>
                <div style="display:flex; flex-wrap:wrap; gap:8px 16px;">
                    @foreach($types as $type)
                        @php($taken = in_array($type, $claimed, true))
                        <label style="display:flex; gap:6px; align-items:center; font-size:14px; {{ $taken ? 'color:#94a3b8;' : '' }}">
                            <input type="checkbox" name="counted_types[]" value="{{ $type }}" {{ in_array($type, $chosen, true) ? 'checked' : '' }} {{ $taken ? 'disabled' : '' }}>
                            {{ $type }}@if($taken) <small>(another loan)</small>@endif
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div style="margin-top:18px;">
            <label class="form-label">Repayments are paid from <span style="color:red;">*</span></label>
            <div style="font-size:13px; color:#475569; margin-bottom:8px;">
                <i class="fas fa-circle-info" style="color:#2563eb;"></i>
                Choose a fixed account only if Finance records moving the money into it (e.g. Cash → C/U Harvest as a Bank Transaction); otherwise keep the first option.
            </div>
            <label style="display:flex; gap:8px; align-items:flex-start; margin-bottom:8px;">
                <input type="radio" name="repayment_source" value="received" {{ $source === 'received' ? 'checked' : '' }} style="margin-top:3px;">
                <span><strong>The account each payment was received into</strong> <span style="color:#64748b;">(usually Cash on Hand)</span></span>
            </label>
            <label style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
                <input type="radio" name="repayment_source" value="fixed" {{ $source === 'fixed' ? 'checked' : '' }} style="margin-top:3px;">
                <span><strong>A fixed account:</strong></span>
                <select name="repayment_account_id" class="form-control" style="width:auto; min-width:260px;">
                    <option value="">Choose…</option>
                    @foreach($cashAccounts as $a)
                        <option value="{{ $a->id }}" {{ (string) $val('repayment_account_id', $defaults['fixed']) === (string) $a->id ? 'selected' : '' }}>{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
</div>

<div class="toolbar" style="justify-content:flex-start;">
    <button type="submit" class="btn-primary toolbar-main"><i class="fas fa-{{ $loan ? 'save' : 'arrow-right' }}"></i> {{ $loan ? 'Save changes' : 'Check and continue' }}</button>
    <a href="{{ $loan ? route('loans.show', $loan) : route('loans.index') }}" class="btn-outline">Cancel</a>
</div>
</form>

@endsection
