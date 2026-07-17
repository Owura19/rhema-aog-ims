<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Giving Statement — {{ $member->full_name ?? $member->first_name }}</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Arial,sans-serif;}
    body{background:#fff;color:#1a1a1a;}
    .sheet{max-width:780px;margin:0 auto;padding:40px;}
    .head{text-align:center;border-bottom:3px double #1a3c5e;padding-bottom:14px;margin-bottom:16px;}
    .head h1{font-size:20px;color:#1a3c5e;}
    .head .sub{font-size:12px;color:#555;margin-top:2px;}
    .who{display:flex;justify-content:space-between;margin-bottom:18px;font-size:13px;}
    .who .name{font-size:16px;font-weight:700;color:#1a1a1a;}
    h3{font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#64748b;margin:20px 0 8px;}
    table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:8px;}
    th,td{border:1px solid #cbd5e1;padding:8px 10px;text-align:left;}
    th{background:#f1f5f9;font-weight:600;color:#334155;}
    td.r,th.r{text-align:right;}
    tfoot td{font-weight:800;background:#eef4fa;color:#1a3c5e;}
    .foot{margin-top:30px;font-size:11px;color:#94a3b8;text-align:center;border-top:1px solid #e2e8f0;padding-top:10px;}
    .sign{margin-top:40px;display:flex;justify-content:space-between;}
    .sign .b{width:45%;}
    .sign .line{border-bottom:1.5px solid #333;height:34px;margin-bottom:5px;}
    .sign .role{font-size:11px;font-weight:700;}
    .print-btn{text-align:center;margin:20px 0;}
    .print-btn button{background:#1a3c5e;color:#fff;border:none;padding:10px 24px;border-radius:6px;font-size:14px;cursor:pointer;}
    @media print{.print-btn{display:none;}.sheet{padding:20px;}}
</style>
</head>
<body>
<div class="print-btn"><button onclick="window.print()">Print this statement</button></div>

<div class="sheet">
    <div class="head">
        <h1>{{ config('app.name', 'Church') }}</h1>
        <div class="sub">Member Giving Statement</div>
    </div>

    <div class="who">
        <div>
            <div class="name">{{ $member->full_name ?? ($member->first_name.' '.$member->last_name) }}</div>
            @if($member->member_id)<div style="color:#64748b;">Member ID: {{ $member->member_id }}</div>@endif
        </div>
        <div style="text-align:right;color:#64748b;">
            <div>Period:</div>
            <div><strong>{{ \Carbon\Carbon::parse($from)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}</strong></div>
        </div>
    </div>

    <h3>Summary by Type</h3>
    <table>
        <thead><tr><th>Giving Type</th><th class="r">Gifts</th><th class="r">Total (GHS)</th></tr></thead>
        <tbody>
            @foreach($summary as $s)
            <tr><td>{{ $s->type }}</td><td class="r">{{ $s->gifts }}</td><td class="r">{{ number_format($s->total, 2) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="2" class="r">TOTAL</td><td class="r">GHS {{ number_format($total, 2) }}</td></tr></tfoot>
    </table>

    <h3>All Contributions</h3>
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th class="r">Amount (GHS)</th></tr></thead>
        <tbody>
            @forelse($items as $it)
            <tr>
                <td>{{ \Carbon\Carbon::parse($it->transaction_date)->format('M d, Y') }}</td>
                <td>{{ $it->type }}</td>
                <td style="font-family:monospace;font-size:11px;">{{ $it->reference }}</td>
                <td class="r">{{ number_format($it->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;color:#94a3b8;">No contributions in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <div class="b"><div class="line"></div><div class="role">Finance Officer</div></div>
        <div class="b"><div class="line"></div><div class="role">Date &amp; Church Stamp</div></div>
    </div>

    <div class="foot">
        This is an official record of contributions made to {{ config('app.name', 'the church') }}.<br>
        Generated {{ now()->format('M d, Y g:i A') }} · Thank you for your faithful giving.
    </div>
</div>
</body>
</html>