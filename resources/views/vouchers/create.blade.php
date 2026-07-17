@extends('layouts.app')

@section('title', 'New Payment Voucher')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('vouchers.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Vouchers</a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Raise Payment Voucher</h2>
</div>

@if(session('error'))<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

<form method="POST" action="{{ route('vouchers.store') }}">
@csrf
<div class="card" style="margin-bottom:20px;max-width:820px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-file-invoice-dollar" style="color:#16a34a;margin-right:8px;"></i>Voucher Details</div></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">

            <div>
                <label class="form-label">Voucher Date <span style="color:red;">*</span></label>
                <input type="date" name="voucher_date" value="{{ old('voucher_date', now()->toDateString()) }}" class="form-control" required>
            </div>

            <div>
                <label class="form-label">Payee (Pay To) <span style="color:red;">*</span></label>
                <input type="text" name="payee" value="{{ old('payee') }}" class="form-control" placeholder="Who is being paid" required>
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Description / Purpose <span style="color:red;">*</span></label>
                <textarea name="description" class="form-control" rows="2" placeholder="What is this payment for?" required>{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="form-label">Category <span style="color:red;">*</span></label>
                <select name="category" id="category" class="form-control" required onchange="filterAccounts(this.value)">
                    <option value="Expense">Expense</option>
                    <option value="Asset">Asset (newly acquired)</option>
                </select>
            </div>

            <div>
                <label class="form-label">Charge to Account <span style="color:red;">*</span></label>
                <select name="account_id" id="account_id" class="form-control" required>
                    <option value="">Select account</option>
                    @foreach($accounts as $a)
                    <option value="{{ $a->id }}" data-type="{{ $a->type }}">{{ $a->code }} — {{ $a->name }} ({{ $a->type }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Pay From (Cash / Bank) <span style="color:red;">*</span></label>
                <select name="cash_account_id" class="form-control" required>
                    <option value="">Select cash / bank</option>
                    @foreach($cashAccounts as $ca)
                    <option value="{{ $ca->id }}">{{ $ca->code }} — {{ $ca->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Amount (GHS) <span style="color:red;">*</span></label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" class="form-control" placeholder="0.00" required>
            </div>

            <div>
                <label class="form-label">Payment Method <span style="color:red;">*</span></label>
                <select name="payment_method" class="form-control" onchange="toggleCheque(this.value)">
                    @foreach(['Cash','Cheque','Bank Transfer','Mobile Money'] as $m)
                    <option value="{{ $m }}">{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div id="cheque-field" style="display:none;">
                <label class="form-label">Cheque Number</label>
                <input type="text" name="cheque_number" value="{{ old('cheque_number') }}" class="form-control" placeholder="Cheque no.">
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes...">{{ old('notes') }}</textarea>
            </div>

        </div>
    </div>
</div>

<div style="display:flex;gap:12px;">
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Voucher</button>
    <a href="{{ route('vouchers.index') }}" class="btn-outline">Cancel</a>
</div>
</form>

<script>
function filterAccounts(cat) {
    const sel = document.getElementById('account_id');
    Array.from(sel.options).forEach(o => {
        if (!o.dataset.type) return;
        o.hidden = (o.dataset.type !== cat);
    });
    sel.value = '';
}
function toggleCheque(m) {
    document.getElementById('cheque-field').style.display = (m === 'Cheque') ? 'block' : 'none';
}
filterAccounts(document.getElementById('category').value);
</script>

@endsection