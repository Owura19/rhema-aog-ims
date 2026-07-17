@php
    // Simple number-to-words for the amount (whole cedis) — defined first so it's available below
    if (!function_exists('numberToWords')) {
        function numberToWords($num) {
            $num = (int) floor($num);
            if ($num == 0) return 'Zero';
            $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
            $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
            $words = '';
            if ($num >= 1000000) { $words .= numberToWords(intdiv($num,1000000)).' Million '; $num %= 1000000; }
            if ($num >= 1000) { $words .= numberToWords(intdiv($num,1000)).' Thousand '; $num %= 1000; }
            if ($num >= 100) { $words .= $ones[intdiv($num,100)].' Hundred '; $num %= 100; }
            if ($num >= 20) { $words .= $tens[intdiv($num,10)].' '; $num %= 10; }
            if ($num > 0) { $words .= $ones[$num].' '; }
            return trim($words);
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payment Voucher {{ $voucher->voucher_no }}</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Arial,sans-serif;}
    body{background:#fff;color:#1a1a1a;}
    .sheet{max-width:800px;margin:0 auto;padding:40px;}
    .head{text-align:center;border-bottom:3px double #1a3c5e;padding-bottom:16px;}
    .head h1{font-size:22px;color:#1a3c5e;letter-spacing:.5px;}
    .head .sub{font-size:12px;color:#555;margin-top:3px;}
    .title-bar{text-align:center;margin:18px 0 24px;}
    .title-bar h2{display:inline-block;font-size:16px;letter-spacing:3px;border:2px solid #1a3c5e;color:#1a3c5e;padding:6px 26px;border-radius:4px;}
    .meta{display:flex;justify-content:space-between;margin-bottom:20px;font-size:13px;}
    .meta .no{font-weight:700;color:#c0392b;font-size:15px;}
    table.body{width:100%;border-collapse:collapse;margin-bottom:22px;font-size:13.5px;}
    table.body td{border:1px solid #cbd5e1;padding:10px 12px;vertical-align:top;}
    table.body td.label{background:#f1f5f9;font-weight:600;width:32%;color:#334155;}
    .amount-row td{font-size:16px;font-weight:800;color:#1a3c5e;background:#eef4fa;}
    .words{font-style:italic;color:#475569;font-size:12.5px;padding-top:4px;font-weight:500;}
    .sigs{margin-top:40px;}
    .sigs h3{font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#64748b;border-bottom:1px solid #e2e8f0;padding-bottom:6px;margin-bottom:26px;}
    .sig-grid{display:grid;grid-template-columns:1fr 1fr;gap:34px 50px;}
    .sig .line{border-bottom:1.5px solid #333;height:38px;margin-bottom:6px;}
    .sig .role{font-size:12px;font-weight:700;color:#1a1a1a;}
    .sig .hint{font-size:10.5px;color:#94a3b8;margin-top:2px;}
    .foot{margin-top:36px;text-align:center;font-size:10.5px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:10px;}
    .print-btn{text-align:center;margin:20px 0;}
    .print-btn button{background:#1a3c5e;color:#fff;border:none;padding:10px 24px;border-radius:6px;font-size:14px;cursor:pointer;}
    @media print{.print-btn{display:none;}.sheet{padding:20px;}}
</style>
</head>
<body>
<div class="print-btn"><button onclick="window.print()">Print this voucher</button></div>

<div class="sheet">
    <div class="head">
        <h1>{{ config('app.name', 'Church') }}</h1>
        <div class="sub">Payment Voucher</div>
    </div>

    <div class="title-bar"><h2>PAYMENT VOUCHER</h2></div>

    <div class="meta">
        <div>Voucher No: <span class="no">{{ $voucher->voucher_no }}</span></div>
        <div>Date: <strong>{{ $voucher->voucher_date->format('F d, Y') }}</strong></div>
    </div>

    <table class="body">
        <tr><td class="label">Pay To (Payee)</td><td>{{ $voucher->payee }}</td></tr>
        <tr><td class="label">Being Payment For</td><td>{{ $voucher->description }}</td></tr>
        <tr><td class="label">Category</td><td>{{ $voucher->category }}@if($voucher->account) — {{ $voucher->account->code }} {{ $voucher->account->name }}@endif</td></tr>
        <tr><td class="label">Paid From</td><td>{{ optional($voucher->cashAccount)->name }} · {{ $voucher->payment_method }}@if($voucher->cheque_number) (Cheque {{ $voucher->cheque_number }})@endif</td></tr>
        <tr class="amount-row">
            <td class="label">Amount</td>
            <td>GHS {{ number_format($voucher->amount, 2) }}
                <div class="words">{{ numberToWords($voucher->amount) }} Ghana Cedis Only</div>
            </td>
        </tr>
    </table>

    <div class="sigs">
        <h3>Authorization &amp; Signatures</h3>
        <div class="sig-grid">
            <div class="sig"><div class="line"></div><div class="role">Prepared By</div><div class="hint">{{ optional($voucher->preparedBy)->name ?? 'Name &amp; Signature' }} · Date</div></div>
            <div class="sig"><div class="line"></div><div class="role">Checked By</div><div class="hint">Name &amp; Signature · Date</div></div>
            <div class="sig"><div class="line"></div><div class="role">Approved By (Authority 1)</div><div class="hint">Name &amp; Signature · Date</div></div>
            <div class="sig"><div class="line"></div><div class="role">Approved By (Authority 2)</div><div class="hint">Name &amp; Signature · Date</div></div>
            <div class="sig"><div class="line"></div><div class="role">Received By (Payee)</div><div class="hint">Name &amp; Signature · Date</div></div>
        </div>
    </div>

    <div class="foot">
        This voucher must be authorized by at least two signatories before payment is released.<br>
        Generated {{ now()->format('M d, Y g:i A') }} · Current status: {{ $voucher->status }}
    </div>
</div>
</body>
</html>