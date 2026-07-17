@extends('layouts.app')

@section('title', 'Edit Bank Transaction')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('bank.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Bank Transactions</a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Bank Transaction</h2>
    <div style="font-size:12.5px; color:#94a3b8;">{{ $bank->reference }}</div>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:16px;">
    @foreach($errors->all() as $e)<div><i class="fas fa-triangle-exclamation"></i> {{ $e }}</div>@endforeach
</div>
@endif

@php
    // Determine current kind from the accounts (1100 = cash, 1200 = bank)
    $fromAcc = $accounts->firstWhere('id', $current['from_account_id']);
    $isWithdrawal = $fromAcc && $fromAcc->code === '1200';
@endphp

<form method="POST" action="{{ route('bank.update', $bank) }}">
@csrf
@method('PUT')
<div class="card" style="margin-bottom:20px; max-width:640px;">
    <div class="card-body">

        <div style="margin-bottom:20px;">
            <label class="form-label">Transaction Type <span style="color:red;">*</span></label>
            <div style="display:flex; gap:12px;">
                <label style="flex:1; cursor:pointer;">
                    <input type="radio" name="kind" value="deposit" {{ !$isWithdrawal ? 'checked' : '' }} onchange="setDirection('deposit')" style="display:none;" class="kind-radio">
                    <div id="opt-deposit" class="kind-opt" style="border:2px solid {{ !$isWithdrawal ? '#16a34a' : '#e2e8f0' }}; background:{{ !$isWithdrawal ? '#f0fdf4' : '#fff' }}; border-radius:10px; padding:14px; text-align:center;">
                        <i class="fas fa-arrow-down" style="color:#16a34a; font-size:18px;"></i>
                        <div style="font-weight:700; color:#16a34a; margin-top:4px;">Deposit</div>
                        <div style="font-size:11.5px; color:#64748b;">Cash → Bank</div>
                    </div>
                </label>
                <label style="flex:1; cursor:pointer;">
                    <input type="radio" name="kind" value="withdrawal" {{ $isWithdrawal ? 'checked' : '' }} onchange="setDirection('withdrawal')" style="display:none;" class="kind-radio">
                    <div id="opt-withdrawal" class="kind-opt" style="border:2px solid {{ $isWithdrawal ? '#16a34a' : '#e2e8f0' }}; background:{{ $isWithdrawal ? '#f0fdf4' : '#fff' }}; border-radius:10px; padding:14px; text-align:center;">
                        <i class="fas fa-arrow-up" style="color:#64748b; font-size:18px;"></i>
                        <div style="font-weight:700; color:#475569; margin-top:4px;">Withdrawal</div>
                        <div style="font-size:11.5px; color:#64748b;">Bank → Cash</div>
                    </div>
                </label>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px;">
            <div>
                <label class="form-label">From (money out) <span style="color:red;">*</span></label>
                <select name="from_account_id" id="from_account_id" class="form-control" required>
                    @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ old('from_account_id', $current['from_account_id']) == $a->id ? 'selected' : '' }}>{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">To (money in) <span style="color:red;">*</span></label>
                <select name="to_account_id" id="to_account_id" class="form-control" required>
                    @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ old('to_account_id', $current['to_account_id']) == $a->id ? 'selected' : '' }}>{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Amount (GHS) <span style="color:red;">*</span></label>
                <input type="number" name="amount" step="0.01" min="0.01" class="form-control" value="{{ old('amount', $current['amount']) }}" required>
            </div>
            <div>
                <label class="form-label">Date <span style="color:red;">*</span></label>
                <input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', \Carbon\Carbon::parse($current['entry_date'])->toDateString()) }}" required>
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Description (optional)</label>
                <input type="text" name="description" class="form-control" value="{{ old('description', $current['description']) }}" placeholder="Note">
            </div>
        </div>

    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Transaction</button>
    <a href="{{ route('bank.index') }}" class="btn-outline">Cancel</a>
</div>
</form>

<script>
const cashId = "{{ $accounts->firstWhere('code','1100')->id ?? '' }}";
const bankId = "{{ $accounts->firstWhere('code','1200')->id ?? '' }}";

function setDirection(kind) {
    document.getElementById('opt-deposit').style.cssText = kind==='deposit'
        ? 'border:2px solid #16a34a; background:#f0fdf4; border-radius:10px; padding:14px; text-align:center;'
        : 'border:2px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;';
    document.getElementById('opt-withdrawal').style.cssText = kind==='withdrawal'
        ? 'border:2px solid #16a34a; background:#f0fdf4; border-radius:10px; padding:14px; text-align:center;'
        : 'border:2px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;';
    if (cashId && bankId) {
        if (kind === 'deposit') {
            document.getElementById('from_account_id').value = cashId;
            document.getElementById('to_account_id').value = bankId;
        } else {
            document.getElementById('from_account_id').value = bankId;
            document.getElementById('to_account_id').value = cashId;
        }
    }
}
</script>

@endsection