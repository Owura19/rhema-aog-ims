@extends('layouts.app')
@section('title', 'Tithe Report')
@section('content')
<div style="margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Tithe Report</h2>
        <div style="font-size:13px; color:#64748b;">
            {{ $month ? \Carbon\Carbon::create()->month($month)->format('F') . ' ' . $year : 'Full Year ' . $year }}
        </div>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <form method="GET" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <select name="year" class="form-control" onchange="this.form.submit()" style="width:auto;">
                @foreach($years as $y)
                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <span style="color:#94a3b8; font-size:13px;">or range:</span>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control" style="width:auto;">
            <span style="color:#94a3b8;">-</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control" style="width:auto;">
            <button type="submit" class="btn-primary" style="background:#1a3c5e;"><i class="fas fa-filter"></i> Filter</button>
            @if($dateFrom || $dateTo)
                <a href="{{ route('finance.tithe-report') }}" style="color:#64748b; font-size:13px;">Clear</a>
            @endif
        </form>
        <button onclick="window.print()" class="btn-primary" style="background:#1a3c5e;">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="display:flex; gap:32px; flex-wrap:wrap;">
        <div>
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#94a3b8;">Total Tithe</div>
            <div style="font-size:24px; font-weight:900; color:#16a34a;">GHS {{ number_format($grandTotal, 2) }}</div>
        </div>
        <div>
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#94a3b8;">Members Who Paid</div>
            <div style="font-size:24px; font-weight:900; color:#1a3c5e;">{{ $members->count() }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-hand-holding-usd" style="color:#16a34a; margin-right:8px;"></i>Tithe by Member</div>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Member</th>
                    <th>Member ID</th>
                    <th style="text-align:center;">Payments</th>
                    <th style="text-align:right;">Total Tithe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight:600;">@if($row->member)<a href="{{ route('member-ledger.show', $row->member_id) }}" style="color:#2563eb; text-decoration:none;">{{ trim($row->member->first_name . ' ' . $row->member->last_name) }}</a>@else Unknown @endif</td>
                    <td style="color:#64748b;">{{ optional($row->member)->member_id ?? '—' }}</td>
                    <td style="text-align:center;">{{ $row->payments }}</td>
                    <td style="text-align:right; font-weight:700; color:#16a34a;">GHS {{ number_format($row->total, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:24px;">No tithe records for this period.</td></tr>
                @endforelse

                @if($walkIn && $walkIn->total > 0)
                <tr style="background:#f8fafc;">
                    <td>—</td>
                    <td style="font-weight:600; font-style:italic; color:#64748b;">Walk-in / Anonymous</td>
                    <td>—</td>
                    <td style="text-align:center;">{{ $walkIn->payments }}</td>
                    <td style="text-align:right; font-weight:700; color:#64748b;">GHS {{ number_format($walkIn->total, 2) }}</td>
                </tr>
                @endif
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #1a3c5e;">
                    <td colspan="4" style="font-weight:700; color:#1a3c5e; font-size:14px;">GRAND TOTAL</td>
                    <td style="text-align:right; font-weight:900; color:#1a3c5e; font-size:15px;">GHS {{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
