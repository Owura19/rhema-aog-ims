@extends('layouts.app')

@section('title', 'New Bank Transaction')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('bank.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Bank Transactions</a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Bank Deposit / Withdrawal</h2>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:16px;">
    @foreach($errors->all() as $e)<div><i class="fas fa-triangle-exclamation"></i> {{ $e }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ route('bank.store') }}">
@csrf
<div class="card" style="margin-bottom:20px; max-width:640px;">
    <div class="card-body">

        <!-- Deposit / Withdrawal toggle -->
        <div style="margin-bottom:20px;">
            <label class="form-label">Transaction Type <span style="color:red;">*</span></label>
            <div style="display:flex; gap:12px;">
                <label style="flex:1; cursor:pointer;">
                    <input type="radio" name="kind" value="deposit" checked onchange="setDirection('deposit')" style="display:none;" class="kind-radio">
                    <div id="opt-deposit" class="kind-opt" style="border:2px solid #16a34a; background:#f0fdf4; border-radius:10px; padding:14px; text-align:center;">
                        <i class="fas fa-arrow-down" style="color:#16a34a; font-size:18px;"></i>
                        <div style="font-weight:700; color:#16a34a; margin-top:4px;">Deposit</div>
                        <div style="font-size:11.5px; color:#64748b;">Cash → Bank</div>
                    </div>
                </label>
                <label style="flex:1; cursor:pointer;">
                    <input type="radio" name="kind" value="withdrawal" onchange="setDirection('withdrawal')" style="display:none;" class="kind-radio">
                    <div id="opt-withdrawal" class="kind-opt" style="border:2px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;">
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
                    <option value="{{ $a->id }}" {{ $cash && $a->id == $cash->id ? 'selected' : '' }}>{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">To (money in) <span style="color:red;">*</span></label>
                <select name="to_account_id" id="to_account_id" class="form-control" required>
                    @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ $bank && $a->id == $bank->id ? 'selected' : '' }}>{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Amount (GHS) <span style="color:red;">*</span></label>
                <input type="number" name="amount" step="0.01" min="0.01" class="form-control" placeholder="0.00" value="{{ old('amount') }}" required>
            </div>
            <div>
                <label class="form-label">Date <span style="color:red;">*</span></label>
                <input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', now()->toDateString()) }}" required>
            </div>

            <div>
                <label class="form-label">Reference (optional)</label>
                <input type="text" name="reference" class="form-control" placeholder="e.g. Slip no. / Cheque no." value="{{ old('reference') }}">
            </div>
            <div>
                <label class="form-label">Description (optional)</label>
                <input type="text" name="description" class="form-control" placeholder="Note" value="{{ old('description') }}">
            </div>
        </div>

    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Record Transaction</button>
    <a href="{{ route('bank.index') }}" class="btn-outline">Cancel</a>
</div>
</form>

<script>
const cashId = "{{ $cash->id ?? '' }}";
const bankId = "{{ $bank->id ?? '' }}";

function setDirection(kind) {
    // Visual toggle
    document.getElementById('opt-deposit').style.cssText = kind==='deposit'
        ? 'border:2px solid #16a34a; background:#f0fdf4; border-radius:10px; padding:14px; text-align:center;'
        : 'border:2px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;';
    document.getElementById('opt-withdrawal').style.cssText = kind==='withdrawal'
        ? 'border:2px solid #16a34a; background:#f0fdf4; border-radius:10px; padding:14px; text-align:center;'
        : 'border:2px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;';

    // Swap from/to defaults
    if (cashId && bankId) {
        if (kind === 'deposit') {   // Cash -> Bank
            document.getElementById('from_account_id').value = cashId;
            document.getElementById('to_account_id').value = bankId;
        } else {                    // Bank -> Cash
            document.getElementById('from_account_id').value = bankId;
            document.getElementById('to_account_id').value = cashId;
        }
    }
}
</script>

@endsection