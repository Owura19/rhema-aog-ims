@extends('layouts.app')
@section('title', 'Pledge Summary by Service')
@section('content')
<div style="margin-bottom:20px;">
    <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Pledge Summary by Service</h2>
    <div style="font-size:13px; color:#64748b;">Pledges, collections, and outstanding balances broken down by service</div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('pledges.summary') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label class="form-label">Campaign</label>
                <select name="harvest" class="form-control" style="min-width:220px;" onchange="this.form.submit()">
                    <option value="">All campaigns</option>
                    @foreach($harvests as $harvest)
                    <option value="{{ $harvest->id }}" {{ $harvestId==$harvest->id ? 'selected' : '' }}>{{ $harvest->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th style="text-align:right;">Total Pledged (#)</th>
                    <th style="text-align:right;">Pledged Amount</th>
                    <th style="text-align:right;">Collected</th>
                    <th style="text-align:right;">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td style="font-weight:600; color:#1e293b;">
                        @if($row->service)
                            {{ $row->service->name }} <span style="color:#94a3b8; font-size:12px;">({{ \Carbon\Carbon::parse($row->service->service_date)->format('M d, Y') }})</span>
                        @else
                            <span style="color:#94a3b8;">— No service —</span>
                        @endif
                    </td>
                    <td style="text-align:right;">{{ $row->count }}</td>
                    <td style="text-align:right; font-weight:600;">GHS {{ number_format($row->pledged, 2) }}</td>
                    <td style="text-align:right; color:#16a34a;">GHS {{ number_format($row->collected, 2) }}</td>
                    <td style="text-align:right; color:#dc2626;">GHS {{ number_format($row->outstanding, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:30px;">No pledges found.</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot>
                <tr style="background:#f8fafc; font-weight:700; border-top:2px solid #e2e8f0;">
                    <td>Total</td>
                    <td style="text-align:right;">{{ $grand->count }}</td>
                    <td style="text-align:right;">GHS {{ number_format($grand->pledged, 2) }}</td>
                    <td style="text-align:right; color:#16a34a;">GHS {{ number_format($grand->collected, 2) }}</td>
                    <td style="text-align:right; color:#dc2626;">GHS {{ number_format($grand->outstanding, 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
