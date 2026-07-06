<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt — {{ $transaction->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', monospace;
            background: #fff;
            color: #1e293b;
            font-size: 12px;
        }

        .receipt {
    width: 280px;
    margin: 0 auto;
    padding: 12px 14px;
    background: #fff;
}

        /* ── TOP LOGO ── */
        .logo-section {
            text-align: center;
            margin-bottom: 12px;
        }

        .logo-circle {
    width: 44px;
    height: 44px;
    background: #1a3c5e;
    border-radius: 50%;
    margin: 0 auto 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

        .logo-text {
    color: #fff;
    font-size: 13px;
    font-weight: 900;
    letter-spacing: 1px;
}

        .church-name {
            font-size: 13px;
            font-weight: 700;
            color: #1a3c5e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .church-sub {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* ── DIVIDERS ── */
       .dashed { border: none; border-top: 1px dashed #cbd5e1; margin: 6px 0; }
.solid  { border: none; border-top: 2px solid #1a3c5e; margin: 6px 0; }
.dotted { border: none; border-top: 1px dotted #cbd5e1; margin: 4px 0; }

        /* ── RECEIPT TITLE ── */
        .receipt-title {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #64748b;
            margin: 6px 0;
        }

        /* ── AMOUNT ── */
        .amount-section {
    text-align: center;
    background: #1a3c5e;
    color: #fff;
    padding: 10px 8px;
    border-radius: 6px;
    margin: 8px 0;
}

        .amount-label {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
            margin-bottom: 4px;
        }

        .amount-value {
    font-size: 22px;
    font-weight: 900;
    color: #e8a020;
    letter-spacing: 1px;
}

        .amount-words {
            font-size: 9px;
            color: rgba(255,255,255,0.5);
            margin-top: 4px;
            font-style: italic;
        }

        .amount-type {
            font-size: 10px;
            color: rgba(255,255,255,0.7);
            margin-top: 6px;
            background: rgba(255,255,255,0.1);
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        /* ── DETAILS ── */
        .detail-row {
    display: table;
    width: 100%;
    padding: 3px 0;
}

        .detail-label {
            display: table-cell;
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 45%;
        }

        .detail-value {
            display: table-cell;
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            text-align: right;
        }

        /* ── REFERENCE ── */
        .ref-section {
            text-align: center;
            margin: 10px 0;
        }

        .ref-number {
            font-size: 14px;
            font-weight: 900;
            color: #1a3c5e;
            letter-spacing: 2px;
            font-family: 'DejaVu Sans Mono', monospace;
        }

        .ref-label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ── STATUS ── */
        .status-section {
            text-align: center;
            margin: 10px 0;
        }

        .status-confirmed {
            display: inline-block;
            border: 2px solid #16a34a;
            color: #16a34a;
            font-size: 11px;
            font-weight: 900;
            padding: 4px 16px;
            letter-spacing: 3px;
            text-transform: uppercase;
            transform: rotate(-3deg);
        }

        .status-pending {
            display: inline-block;
            border: 2px solid #ca8a04;
            color: #ca8a04;
            font-size: 11px;
            font-weight: 900;
            padding: 4px 16px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .status-cancelled {
            display: inline-block;
            border: 2px solid #dc2626;
            color: #dc2626;
            font-size: 11px;
            font-weight: 900;
            padding: 4px 16px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        /* ── BARCODE STYLE ── */
        .barcode-section {
            text-align: center;
            margin: 10px 0;
        }

        .barcode-lines {
            font-size: 32px;
            letter-spacing: -2px;
            color: #1e293b;
            line-height: 1;
        }

        .barcode-text {
            font-size: 9px;
            color: #94a3b8;
            letter-spacing: 3px;
            margin-top: 2px;
        }

        /* ── FOOTER ── */
        .footer {
            text-align: center;
            margin-top: 10px;
        }

        .footer-msg {
            font-size: 10px;
            color: #64748b;
            line-height: 1.8;
        }

        .footer-brand {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .thank-you {
            font-size: 13px;
            font-weight: 700;
            color: #1a3c5e;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 6px 0;
        }

        /* ── WATERMARK ── */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            font-weight: 900;
            color: rgba(26,60,94,0.03);
            text-transform: uppercase;
            letter-spacing: 6px;
            pointer-events: none;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="watermark">Rhema</div>

<div class="receipt">

    <!-- Logo & Church Name -->
    <div class="logo-section">
        <div class="logo-circle">
            <div class="logo-text">GW</div>
        </div>
        <div class="church-name">Rhema Assembly of God</div>
        <div class="church-sub">Kumasi, Ghana</div>
    </div>

    <hr class="solid">

    <!-- Receipt Title -->
    <div class="receipt-title">*** Official Receipt ***</div>

    <!-- Ref & Date -->
    <div class="ref-section">
        <div class="ref-label">Receipt Number</div>
        <div class="ref-number">{{ $transaction->reference }}</div>
    </div>

    <hr class="dashed">

    <!-- Amount -->
    <div class="amount-section">
        <div class="amount-label">Amount Received</div>
        <div class="amount-value">GHS {{ number_format($transaction->amount, 2) }}</div>
        <div class="amount-type">{{ $transaction->type }} &nbsp;·&nbsp; {{ $transaction->payment_method }}</div>
    </div>

    <hr class="dashed">

    <!-- Details -->
    <div class="detail-row">
        <div class="detail-label">Date</div>
        <div class="detail-value">{{ $transaction->transaction_date->format('d M Y') }}</div>
    </div>
    <hr class="dotted">

    <div class="detail-row">
        <div class="detail-label">Received From</div>
        <div class="detail-value">{{ $transaction->payer_label }}</div>
    </div>
    <hr class="dotted">

    @if($transaction->member)
    <div class="detail-row">
        <div class="detail-label">Member ID</div>
        <div class="detail-value">{{ $transaction->member->member_id }}</div>
    </div>
    <hr class="dotted">
    @endif

    <div class="detail-row">
        <div class="detail-label">Type</div>
        <div class="detail-value">{{ $transaction->type }}</div>
    </div>
    <hr class="dotted">

    <div class="detail-row">
        <div class="detail-label">Category</div>
        <div class="detail-value">{{ $transaction->category }}</div>
    </div>
    <hr class="dotted">

    <div class="detail-row">
        <div class="detail-label">Method</div>
        <div class="detail-value">{{ $transaction->payment_method }}</div>
    </div>
    <hr class="dotted">

    @if($transaction->mobile_money_number)
    <div class="detail-row">
        <div class="detail-label">MoMo No.</div>
        <div class="detail-value">{{ $transaction->mobile_money_number }}</div>
    </div>
    <hr class="dotted">
    @endif

    @if($transaction->cheque_number)
    <div class="detail-row">
        <div class="detail-label">Cheque No.</div>
        <div class="detail-value">{{ $transaction->cheque_number }}</div>
    </div>
    <hr class="dotted">
    @endif

    @if($transaction->bank_name)
    <div class="detail-row">
        <div class="detail-label">Bank</div>
        <div class="detail-value">{{ $transaction->bank_name }}</div>
    </div>
    <hr class="dotted">
    @endif

    @if($transaction->churchService)
    <div class="detail-row">
        <div class="detail-label">Service</div>
        <div class="detail-value">{{ $transaction->churchService->name }}</div>
    </div>
    <hr class="dotted">
    @endif

    <div class="detail-row">
        <div class="detail-label">Recorded By</div>
        <div class="detail-value">{{ $transaction->recordedBy?->name ?? 'System' }}</div>
    </div>

    @if($transaction->description)
    <hr class="dotted">
    <div class="detail-row">
        <div class="detail-label">Notes</div>
        <div class="detail-value">{{ Str::limit($transaction->description, 30) }}</div>
    </div>
    @endif

    <hr class="dashed">

    <!-- Total Line -->
    <div class="detail-row">
        <div class="detail-label" style="font-weight:700; color:#1a3c5e; font-size:12px;">TOTAL PAID</div>
        <div class="detail-value" style="font-size:14px; color:#1a3c5e;">GHS {{ number_format($transaction->amount, 2) }}</div>
    </div>

    <hr class="solid">

    <!-- Status Stamp -->
    <div class="status-section">
        @if($transaction->status === 'Confirmed')
            <div class="status-confirmed">✓ Confirmed</div>
        @elseif($transaction->status === 'Pending')
            <div class="status-pending">⏳ Pending</div>
        @else
            <div class="status-cancelled">✗ Cancelled</div>
        @endif
    </div>

    <hr class="dashed">

    <!-- Barcode Style -->
    <div class="barcode-section">
        <div class="barcode-lines">||| || ||| | || ||| || | |||</div>
        <div class="barcode-text">{{ $transaction->reference }}</div>
    </div>

    <hr class="dashed">

    <!-- Footer -->
    <div class="footer">
        <div class="thank-you">Thank You!</div>
        <div class="footer-msg">
            God bless you for your giving.<br>
            Please keep this receipt for your records.<br>
            {{ now()->format('d M Y, g:i A') }}
        </div>
        <div class="footer-brand">
            ── Rhema AoG IMS ──
        </div>
    </div>

</div>

</body>
</html>