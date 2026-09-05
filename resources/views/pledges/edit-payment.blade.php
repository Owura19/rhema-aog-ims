@extends('layouts.app')
@section('title', 'Edit Payment')
@section('content')
<div style="max-width:600px;">
    <a href="{{ route('pledges.show', $pledge) }}" style="color:#64748b; text-decoration:none; font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Pledge</a>
    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-pen" style="color:#16a34a; margin-right:8px;"></i>Edit Payment — {{ $pledge->reference }}</div>
        </div>
        <div class="card-body">
            @if($errors->any())
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            </div>
            @endif
            <form method="POST" action="{{ route('pledges.payments.update', [$pledge, $payment]) }}">
                @csrf
                @method('PATCH')
                <div class="grid-2" style="margin-bottom:16px;">
                    <div>
                        <label class="form-label">Amount (GHS)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', $payment->amount) }}" required>
                    </div>
                    <div>
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d')) }}" required>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-control" required>
                        @foreach(['Cash','Mobile Money','Bank Transfer','Cheque','Other'] as $m)
                        <option value="{{ $m }}" {{ old('payment_method', $payment->payment_method)==$m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:20px;">
                    <label class="form-label">Notes <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $payment->notes) }}</textarea>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Payment</button>
                    <a href="{{ route('pledges.show', $pledge) }}" class="btn-outline" style="border-color:#e2e8f0; color:#64748b;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
