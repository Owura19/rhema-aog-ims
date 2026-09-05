<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 40px 45px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1e293b; font-size: 11px; }
    .letterhead { text-align: center; border-bottom: 3px solid #1a3c5e; padding-bottom: 12px; margin-bottom: 6px; }
    .letterhead h1 { color: #1a3c5e; font-size: 20px; margin: 0; }
    .letterhead .report-title { color: #e8a020; font-size: 14px; font-weight: bold; margin-top: 8px; text-transform: uppercase; letter-spacing: 1px; }
    .meta { text-align: center; color: #64748b; font-size: 10px; margin-bottom: 16px; }
    .summary { width: 100%; margin-bottom: 18px; border-collapse: collapse; }
    .summary td { text-align: center; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .summary .val { font-size: 15px; font-weight: bold; color: #1a3c5e; }
    .summary .val.green { color: #16a34a; }
    .summary .val.red { color: #dc2626; }
    .summary .lbl { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #1a3c5e; color: #fff; text-align: left; padding: 7px 9px; font-size: 9px; text-transform: uppercase; }
    table.data th.amt, table.data td.amt { text-align: right; }
    table.data th.ctr, table.data td.ctr { text-align: center; }
    table.data td { padding: 5px 9px; border-bottom: 1px solid #e2e8f0; }
    table.data tr:nth-child(even) td { background: #f8fafc; }
    .grand td { border-top: 3px double #1a3c5e; background: #f1f5f9; font-weight: bold; color: #1a3c5e; font-size: 12px; }
    .footer { position: fixed; bottom: -20px; left: 0; right: 0; text-align: center; color: #94a3b8; font-size: 9px; }
</style>
</head>
<body>
    <div class="letterhead">
        <h1>{{ $churchName }}</h1>
        <div class="report-title">Pledges Report</div>
    </div>
    <div class="meta">Generated {{ $generatedAt }}</div>

    <table class="summary">
        <tr>
            <td>
                <div class="val">GHS {{ number_format($totals['pledged'], 2) }}</div>
                <div class="lbl">Total Pledged</div>
            </td>
            <td>
                <div class="val green">GHS {{ number_format($totals['paid'], 2) }}</div>
                <div class="lbl">Total Paid</div>
            </td>
            <td>
                <div class="val red">GHS {{ number_format($totals['outstanding'], 2) }}</div>
                <div class="lbl">Outstanding</div>
            </td>
            <td>
                <div class="val">{{ $totals['count'] }}</div>
                <div class="lbl">Pledges ({{ $totals['fulfilled'] }} fulfilled)</div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:24px;">#</th>
                <th>Pledger</th>
                <th>Purpose</th>
                <th class="amt">Pledged</th>
                <th class="amt">Paid</th>
                <th class="amt">Balance</th>
                <th class="ctr">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pledges as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->name }}</td>
                <td>{{ $row->purpose }}</td>
                <td class="amt">{{ number_format($row->pledged, 2) }}</td>
                <td class="amt">{{ number_format($row->paid, 2) }}</td>
                <td class="amt">{{ number_format($row->balance, 2) }}</td>
                <td class="ctr">{{ $row->percent }}%</td>
            </tr>
            @endforeach
            <tr class="grand">
                <td colspan="3">GRAND TOTAL</td>
                <td class="amt">{{ number_format($totals['pledged'], 2) }}</td>
                <td class="amt">{{ number_format($totals['paid'], 2) }}</td>
                <td class="amt">{{ number_format($totals['outstanding'], 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">{{ $churchName }} — Pledges Report — Page <script type="text/php">if(isset($pdf)){$pdf->page_text(520,750,"{PAGE_NUM}/{PAGE_COUNT}",null,8,array(0.6,0.6,0.6));}</script></div>
</body>
</html>
