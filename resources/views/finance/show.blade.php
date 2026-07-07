@extends('layouts.app')

@section('title', $transaction->reference)

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <a href="{{ route('finance.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Finance
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Transaction {{ $transaction->reference }}</h2>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="/receipts/{{ $transaction->id }}" class="btn-primary" style="background:#16a34a;" target="_blank">
            <i class="fas fa-file-pdf"></i> View Receipt
        </a>
        <a href="/receipts/{{ $transaction->id }}/download" class="btn-outline">
            <i class="fas fa-download"></i> Download
        </a>
        <a href="{{ route('finance.edit', $transaction) }}" class="btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
        <form method="POST" action="{{ route('finance.destroy', $transaction) }}" onsubmit="return confirm('Delete this transaction?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div class="grid-2">

    <!-- Transaction Details -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-receipt" style="color:#16a34a; margin-right:8px;"></i>Transaction Details</div>
        </div>
        <div class="card-body" style="padding:0;">
            <table style="width:100%;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; width:40%;">Reference</td>
                    <td style="padding:14px 20px;"><span style="font-family:monospace; background:#f1f5f9; padding:4px 10px; border-radius:4px; font-size:13px;">{{ $transaction->reference }}</span></td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Type</td>
                    <td style="padding:14px 20px;">
                        <span class="badge {{ $transaction->category === 'Income' ? 'badge-success' : 'badge-danger' }}">{{ $transaction->type }}</span>
                        @if($transaction->subcategory)
                            <span class="badge badge-gray" style="margin-left:4px;">{{ $transaction->subcategory }}</span>
                        @endif
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Amount</td>
                    <td style="padding:14px 20px; font-size:24px; font-weight:800; color:{{ $transaction->category === 'Income' ? '#16a34a' : '#dc2626' }};">
                        {{ $transaction->category === 'Expense' ? '-' : '+' }} GHS {{ number_format($transaction->amount, 2) }}
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Date</td>
                    <td style="padding:14px 20px; font-size:14px; color:#1e293b;">{{ $transaction->transaction_date->format('l, F d, Y') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Payment Method</td>
                    <td style="padding:14px 20px; font-size:14px; color:#1e293b;">{{ $transaction->payment_method }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Status</td>
                    <td style="padding:14px 20px;">
                        @if($transaction->status === 'Confirmed')
                            <span class="badge badge-success">Confirmed</span>
                        @elseif($transaction->status === 'Pending')
                            <span class="badge badge-warning">Pending</span>
                        @else
                            <span class="badge badge-danger">Cancelled</span>
                        @endif
                    </td>
                </tr>
                @if($transaction->mobile_money_number)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">MoMo Number</td>
                    <td style="padding:14px 20px; font-size:14px; color:#1e293b;">{{ $transaction->mobile_money_number }}</td>
                </tr>
                @endif
                @if($transaction->cheque_number)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Cheque Number</td>
                    <td style="padding:14px 20px; font-size:14px; color:#1e293b;">{{ $transaction->cheque_number }}</td>
                </tr>
                @endif
                @if($transaction->bank_name)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Bank</td>
                    <td style="padding:14px 20px; font-size:14px; color:#1e293b;">{{ $transaction->bank_name }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:14px 20px; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Recorded By</td>
                    <td style="padding:14px 20px; font-size:14px; color:#1e293b;">{{ $transaction->recordedBy?->name ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Payer & Service Info -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Payer -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user" style="color:#2563eb; margin-right:8px;"></i>Payer Information</div>
            </div>
            <div class="card-body">
                @if($transaction->member)
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                        <div class="member-avatar-placeholder" style="width:48px; height:48px; font-size:18px;">
                            {{ strtoupper(substr($transaction->member->first_name, 0, 1)) }}
                        </div>
                        <div>
                            <div style="font-size:16px; font-weight:700; color:#1e293b;">{{ $transaction->member->full_name }}</div>
                            <div style="font-size:13px; color:#64748b;">{{ $transaction->member->member_id }}</div>
                        </div>
                    </div>
                    <a href="{{ route('members.show', $transaction->member) }}" class="btn-outline btn-sm">
                        <i class="fas fa-user"></i> View Member Profile
                    </a>
                @else
                    <div style="font-size:16px; font-weight:600; color:#1e293b;">{{ $transaction->payer_name ?? 'Anonymous' }}</div>
                    <div style="font-size:13px; color:#94a3b8; margin-top:4px;">Not a registered member</div>
                @endif
            </div>
        </div>

        <!-- Service -->
        @if($transaction->churchService)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-church" style="color:#7c3aed; margin-right:8px;"></i>Related Service</div>
            </div>
            <div class="card-body">
                <div style="font-size:16px; font-weight:700; color:#1e293b; margin-bottom:4px;">{{ $transaction->churchService->name }}</div>
                <div style="font-size:13px; color:#64748b; margin-bottom:12px;">{{ $transaction->churchService->service_date->format('l, F d, Y') }}</div>
                <a href="{{ route('services.show', $transaction->churchService) }}" class="btn-outline btn-sm">
                    <i class="fas fa-church"></i> View Service
                </a>
            </div>
        </div>
        @endif

        <!-- Notes -->
        @if($transaction->description)
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-sticky-note" style="color:#e8a020; margin-right:8px;"></i>Notes</div>
            </div>
            <div class="card-body">
                <p style="font-size:14px; color:#374151; line-height:1.8; margin:0;">{{ $transaction->description }}</p>
            </div>
        </div>
        @endif

        <!-- Receipt Actions -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-file-pdf" style="color:#16a34a; margin-right:8px;"></i>Receipt</div>
            </div>
            <div class="card-body" style="display:flex; flex-direction:column; gap:10px;">
                <a href="/receipts/{{ $transaction->id }}" target="_blank" class="btn-primary" style="background:#16a34a; justify-content:center;">
                    <i class="fas fa-eye"></i> View Receipt (PDF)
                </a>
                <a href="/receipts/{{ $transaction->id }}/download" class="btn-outline" style="justify-content:center;">
                    <i class="fas fa-download"></i> Download Receipt
                </a>
            </div>
        </div>

    </div>
</div>

@endsection