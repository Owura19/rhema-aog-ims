@extends('layouts.app')
@section('title', 'Edit Payment Voucher')
@section('content')
<div style="margin-bottom:20px;">
    <a href="{{ route('vouchers.show', $voucher) }}" style="color:#64748b; font-size:13px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Voucher</a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Voucher — {{ $voucher->voucher_no }}</h2>
</div>
@if(session('error'))<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" style="margin-bottom:16px;">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@if($voucher->status === 'Paid')
<div class="alert" style="background:#fef9c3; color:#854d0e; margin-bottom:16px; padding:12px; border-radius:8px;"><i class="fas fa-exclamation-triangle"></i> This voucher is <strong>Paid</strong>. Editing it will also update its posted transaction and re-post the ledger entry.</div>
@endif
<form method="POST" action="{{ route('vouchers.update', $voucher) }}">
@csrf
@method('PATCH')
<div class="card" style="margin-bottom:20px;max-width:820px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-file-invoice-dollar" style="color:#16a34a;margin-right:8px;"></i>Voucher Details</div></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
            <div>
                <label class="form-label">Voucher Date <span style="color:red;">*</span></label>
                <input type="date" name="voucher_date" value="{{ old('voucher_date', \Carbon\Carbon::parse($voucher->voucher_date)->toDateString()) }}" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Payee (Pay To) <span style="color:red;">*</span></label>
                <input type="text" name="payee" value="{{ old('payee', $voucher->payee) }}" class="form-control" required>
            </div>
            <div style="grid-column:span 2;">
                <label class="form-label">Description / Purpose <span style="color:red;">*</span></label>
                <textarea name="description" class="form-control" rows="2" required>{{ old('description', $voucher->description) }}</textarea>
            </div>
            <div>
                <label class="form-label">Category <span style="color:red;">*</span></label>
                <select name="category" class="form-control" required>
                    <option value="Expense" {{ old('category', $voucher->category)=='Expense'?'selected':'' }}>Expense</option>
                    <option value="Asset" {{ old('category', $voucher->category)=='Asset'?'selected':'' }}>Asset (newly acquired)</option>
                </select>
            </div>
            <div>
                <label class="form-label">Amount (GHS) <span style="color:red;">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $voucher->amount) }}" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Expense / Asset Account <span style="color:red;">*</span></label>
                <select name="account_id" class="form-control" required>
                    @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" {{ old('account_id', $voucher->account_id)==$acc->id?'selected':'' }}>{{ $acc->code }} — {{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Paid From (Cash/Bank) <span style="color:red;">*</span></label>
                <select name="cash_account_id" class="form-control" required>
                    @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" {{ old('cash_account_id', $voucher->cash_account_id)==$acc->id?'selected':'' }}>{{ $acc->code }} — {{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Payment Method <span style="color:red;">*</span></label>
                <select name="payment_method" class="form-control" required>
                    @foreach(['Cash','Cheque','Bank Transfer','Mobile Money'] as $m)
                    <option value="{{ $m }}" {{ old('payment_method', $voucher->payment_method)==$m?'selected':'' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Cheque Number <span style="color:#94a3b8;font-weight:400;">(if cheque)</span></label>
                <input type="text" name="cheque_number" value="{{ old('cheque_number', $voucher->cheque_number) }}" class="form-control">
            </div>
            <div style="grid-column:span 2;">
                <label class="form-label">Notes <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $voucher->notes) }}</textarea>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Voucher</button>
            <a href="{{ route('vouchers.show', $voucher) }}" class="btn-outline" style="border-color:#e2e8f0;color:#64748b;">Cancel</a>
        </div>
    </div>
</div>
</form>
@endsection
