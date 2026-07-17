@extends('layouts.app')

@section('title', 'Balance Sheet')

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('finance.reports-hub') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Financial Reports</a>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-building-columns" style="color:#7c3aed;margin-right:8px;"></i>Balance Sheet</div>
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <label style="font-size:13px;color:#64748b;">As of</label>
            <input type="date" name="as_of" value="{{ $asOf }}" class="form-control" style="width:auto;padding:6px 10px;">
            <button type="submit" class="btn-primary btn-sm">Apply</button>
        </form>
    </div>

    <div class="card-body">
        <div style="text-align:center;margin-bottom:20px;">
            <div style="font-size:12.5px;color:#94a3b8;">Statement of Financial Position as of {{ \Carbon\Carbon::parse($asOf)->format('M d, Y') }}</div>
        </div>

        <!-- ASSETS -->
        <div style="margin-bottom:24px;">
            <div style="font-weight:800;font-size:14px;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:8px;margin-bottom:8px;">
                <i class="fas fa-coins" style="color:#16a34a;"></i> ASSETS
            </div>
            <table style="width:100%;">
                @forelse($assets as $row)
                <tr>
                    <td style="padding:7px 0;font-size:13.5px;color:#475569;">{{ $row->account->name }}</td>
                    <td style="padding:7px 0;text-align:right;font-size:13.5px;font-weight:600;">GHS {{ number_format($row->balance, 2) }}</td>
                </tr>
                @empty
                <tr><td style="padding:10px 0;font-size:13px;color:#94a3b8;">No asset balances yet.</td></tr>
                @endforelse
                <tr style="border-top:1.5px solid #e2e8f0;">
                    <td style="padding:9px 0;font-weight:800;color:#1e293b;">Total Assets</td>
                    <td style="padding:9px 0;text-align:right;font-weight:800;color:#16a34a;">GHS {{ number_format($totalAssets, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- LIABILITIES -->
        <div style="margin-bottom:24px;">
            <div style="font-weight:800;font-size:14px;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:8px;margin-bottom:8px;">
                <i class="fas fa-file-invoice" style="color:#dc2626;"></i> LIABILITIES
            </div>
            <table style="width:100%;">
                @forelse($liabilities as $row)
                <tr>
                    <td style="padding:7px 0;font-size:13.5px;color:#475569;">{{ $row->account->name }}</td>
                    <td style="padding:7px 0;text-align:right;font-size:13.5px;font-weight:600;">GHS {{ number_format($row->balance, 2) }}</td>
                </tr>
                @empty
                <tr><td style="padding:10px 0;font-size:13px;color:#94a3b8;">No liabilities.</td></tr>
                @endforelse
                <tr style="border-top:1.5px solid #e2e8f0;">
                    <td style="padding:9px 0;font-weight:800;color:#1e293b;">Total Liabilities</td>
                    <td style="padding:9px 0;text-align:right;font-weight:800;color:#dc2626;">GHS {{ number_format($totalLiabilities, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- EQUITY -->
        <div style="margin-bottom:24px;">
            <div style="font-weight:800;font-size:14px;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:8px;margin-bottom:8px;">
                <i class="fas fa-scale-balanced" style="color:#2563eb;"></i> EQUITY / FUNDS
            </div>
            <table style="width:100%;">
                @foreach($equity as $row)
                <tr>
                    <td style="padding:7px 0;font-size:13.5px;color:#475569;">{{ $row->account->name }}</td>
                    <td style="padding:7px 0;text-align:right;font-size:13.5px;font-weight:600;">GHS {{ number_format($row->balance, 2) }}</td>
                </tr>
                @endforeach
                <tr>
                    <td style="padding:7px 0;font-size:13.5px;color:#475569;">Current Period Surplus / (Deficit)</td>
                    <td style="padding:7px 0;text-align:right;font-size:13.5px;font-weight:600;color:{{ $surplus >= 0 ? '#16a34a' : '#dc2626' }};">GHS {{ number_format($surplus, 2) }}</td>
                </tr>
                <tr style="border-top:1.5px solid #e2e8f0;">
                    <td style="padding:9px 0;font-weight:800;color:#1e293b;">Total Equity / Funds</td>
                    <td style="padding:9px 0;text-align:right;font-weight:800;color:#2563eb;">GHS {{ number_format($totalEquity, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- BALANCE CHECK -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;">
            <table style="width:100%;">
                <tr>
                    <td style="font-weight:800;font-size:15px;color:#1e293b;">Total Assets</td>
                    <td style="text-align:right;font-weight:800;font-size:15px;color:#1e293b;">GHS {{ number_format($totalAssets, 2) }}</td>
                </tr>
                <tr>
                    <td style="font-weight:800;font-size:15px;color:#1e293b;">Liabilities + Equity</td>
                    <td style="text-align:right;font-weight:800;font-size:15px;color:#1e293b;">GHS {{ number_format($totalLiabilities + $totalEquity, 2) }}</td>
                </tr>
            </table>
            <div style="margin-top:12px;text-align:center;">
                @if($balanced)
                    <span class="badge badge-success" style="font-size:13px;padding:6px 16px;"><i class="fas fa-circle-check"></i> Balanced — Assets = Liabilities + Equity</span>
                @else
                    <span class="badge badge-danger" style="font-size:13px;padding:6px 16px;"><i class="fas fa-triangle-exclamation"></i> Out of balance — review opening balances</span>
                @endif
            </div>
        </div>

        <div style="margin-top:14px;font-size:11.5px;color:#94a3b8;text-align:center;">
            Note: this statement reflects double-entry journal activity. Opening balances (existing cash, assets, and liabilities) should be entered for a complete picture.
        </div>
    </div>
</div>

@endsection