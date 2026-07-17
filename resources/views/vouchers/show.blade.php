@extends('layouts.app')

@section('title', 'Voucher ' . $voucher->voucher_no)

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('vouchers.index') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Vouchers</a>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" style="margin-bottom:16px;"><i class="fas fa-triangle-exclamation"></i> {{ session('error') }}</div>@endif

<div class="card" style="max-width:760px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-invoice-dollar" style="color:#16a34a;margin-right:8px;"></i>{{ $voucher->voucher_no }}</div>
        <span class="badge" style="background:{{ $voucher->status_color }}20;color:{{ $voucher->status_color }};font-size:13px;padding:5px 14px;">{{ $voucher->status }}</span>
    </div>
    <div class="card-body">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:22px;">
            <div><div style="font-size:12px;color:#94a3b8;">Date</div><div style="font-weight:600;">{{ $voucher->voucher_date->format('M d, Y') }}</div></div>
            <div><div style="font-size:12px;color:#94a3b8;">Payee</div><div style="font-weight:600;">{{ $voucher->payee }}</div></div>
            <div style="grid-column:span 2;"><div style="font-size:12px;color:#94a3b8;">Purpose</div><div style="font-weight:600;">{{ $voucher->description }}</div></div>
            <div><div style="font-size:12px;color:#94a3b8;">Category</div><div style="font-weight:600;">{{ $voucher->category }}</div></div>
            <div><div style="font-size:12px;color:#94a3b8;">Amount</div><div style="font-weight:800;font-size:18px;color:#16a34a;">GHS {{ number_format($voucher->amount, 2) }}</div></div>
            <div><div style="font-size:12px;color:#94a3b8;">Charge to</div><div style="font-weight:600;">{{ optional($voucher->account)->name }}</div></div>
            <div><div style="font-size:12px;color:#94a3b8;">Pay from</div><div style="font-weight:600;">{{ optional($voucher->cashAccount)->name }}</div></div>
            <div><div style="font-size:12px;color:#94a3b8;">Payment Method</div><div style="font-weight:600;">{{ $voucher->payment_method }}{{ $voucher->cheque_number ? ' (Chq '.$voucher->cheque_number.')' : '' }}</div></div>
            <div><div style="font-size:12px;color:#94a3b8;">Prepared by</div><div style="font-weight:600;">{{ optional($voucher->preparedBy)->name ?? '—' }}</div></div>
        </div>

        <!-- Approval trail -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin-bottom:20px;">
            <div style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:8px;">APPROVAL TRAIL</div>
            <div style="font-size:13px;color:#475569;line-height:1.9;">
                <div><i class="fas fa-circle-check" style="color:#16a34a;"></i> Prepared — {{ optional($voucher->preparedBy)->name ?? '—' }}</div>
                <div><i class="fas {{ $voucher->approved_at ? 'fa-circle-check' : 'fa-circle' }}" style="color:{{ $voucher->approved_at ? '#16a34a' : '#cbd5e1' }};"></i> Approved — {{ $voucher->approved_at ? optional($voucher->approvedBy)->name.' on '.$voucher->approved_at->format('M d, Y g:i A') : 'Pending' }}</div>
                <div><i class="fas {{ $voucher->paid_at ? 'fa-circle-check' : 'fa-circle' }}" style="color:{{ $voucher->paid_at ? '#16a34a' : '#cbd5e1' }};"></i> Paid — {{ $voucher->paid_at ? optional($voucher->paidBy)->name.' on '.$voucher->paid_at->format('M d, Y g:i A') : 'Not yet' }}</div>
            </div>
        </div>

        @if($voucher->notes)
        <div style="margin-bottom:20px;"><div style="font-size:12px;color:#94a3b8;">Notes</div><div style="color:#475569;">{{ $voucher->notes }}</div></div>
        @endif

        <!-- Actions -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('vouchers.print', $voucher) }}" target="_blank" class="btn-outline"><i class="fas fa-print"></i> Print Voucher</a>

            @if($voucher->can_approve)
            <form method="POST" action="{{ route('vouchers.approve', $voucher) }}" onsubmit="return confirm('Confirm the voucher has been signed by the authorities and approve it?');">
                @csrf @method('PATCH')
                <button type="submit" class="btn-primary" style="background:#2563eb;"><i class="fas fa-check"></i> Mark Approved</button>
            </form>
            @endif

            @if($voucher->can_pay)
            <form method="POST" action="{{ route('vouchers.pay', $voucher) }}" onsubmit="return confirm('Mark as paid? This will post the payment to the accounts.');">
                @csrf @method('PATCH')
                <button type="submit" class="btn-primary" style="background:#16a34a;"><i class="fas fa-money-bill-wave"></i> Mark Paid &amp; Post</button>
            </form>
            @endif

            @if(in_array($voucher->status, ['Pending','Approved']))
            <form method="POST" action="{{ route('vouchers.reject', $voucher) }}" onsubmit="return confirm('Reject this voucher?');">
                @csrf @method('PATCH')
                <button type="submit" class="btn-outline" style="border-color:#fecaca;color:#dc2626;"><i class="fas fa-times"></i> Reject</button>
            </form>
            @endif
        </div>

        @if($voucher->status === 'Paid' && $voucher->transaction)
        <div style="margin-top:16px;font-size:13px;color:#16a34a;"><i class="fas fa-link"></i> Posted to accounts as transaction {{ optional($voucher->transaction)->reference }}.</div>
        @endif

    </div>
</div>

@endsection