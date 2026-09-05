@extends('layouts.app')
@section('title', 'Pledges Report')
@section('content')

<div style="max-width:1100px; margin:0 auto;">

    <a href="{{ route('pledges.index') }}" style="color:#64748b; text-decoration:none; font-size:14px; display:inline-block; margin-bottom:14px;"><i class="fas fa-arrow-left"></i> Back to Pledges</a>

    <!-- Document header -->
    <div style="display:flex; align-items:flex-start; justify-content:space-between; border-bottom:2px solid #1a3c5e; padding-bottom:16px; margin-bottom:0;">
        <div>
            <h2 style="font-size:22px; font-weight:800; color:#1a3c5e; margin:0;">Pledges Report</h2>
            <div style="font-size:13px; color:#64748b; margin-top:4px;">
                {{ $totals['count'] }} pledges &middot; {{ $totals['fulfilled'] }} fulfilled &middot; as of {{ now()->format('M d, Y') }}
            </div>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('pledges.report.pdf', request()->query()) }}" class="btn-primary btn-sm" style="background:#dc2626;"><i class="fas fa-file-pdf"></i> Download PDF</a>
            <button onclick="window.print()" class="btn-primary btn-sm" style="background:#1a3c5e;"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <!-- Slim summary strip -->
    <div style="display:flex; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 8px 8px; background:#f8fafc; margin-bottom:24px;">
        <div style="flex:1; padding:14px 20px; border-right:1px solid #e2e8f0;">
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Total Pledged</div>
            <div style="font-size:19px; font-weight:800; color:#1a3c5e; margin-top:2px;">GHS {{ number_format($totals['pledged'], 2) }}</div>
        </div>
        <div style="flex:1; padding:14px 20px; border-right:1px solid #e2e8f0;">
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Total Paid</div>
            <div style="font-size:19px; font-weight:800; color:#16a34a; margin-top:2px;">GHS {{ number_format($totals['paid'], 2) }}</div>
        </div>
        <div style="flex:1; padding:14px 20px; border-right:1px solid #e2e8f0;">
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Outstanding</div>
            <div style="font-size:19px; font-weight:800; color:#dc2626; margin-top:2px;">GHS {{ number_format($totals['outstanding'], 2) }}</div>
        </div>
        <div style="flex:1; padding:14px 20px;">
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Fulfilment</div>
            <div style="font-size:19px; font-weight:800; color:#1a3c5e; margin-top:2px;">{{ $totals['pledged'] > 0 ? round($totals['paid'] / $totals['pledged'] * 100) : 0 }}%</div>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="GET" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap; margin-bottom:18px;">
        <div>
            <label style="font-size:12px; color:#64748b; display:block; margin-bottom:3px;">Purpose</label>
            <select name="purpose" class="form-control" style="width:auto;">
                <option value="">All Purposes</option>
                @foreach($purposes as $pp)
                    <option value="{{ $pp->id }}" {{ request('purpose') == $pp->id ? 'selected' : '' }}>{{ $pp->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px; color:#64748b; display:block; margin-bottom:3px;">Status</label>
            <select name="status" class="form-control" style="width:auto;">
                <option value="">All Statuses</option>
                <option value="Active" {{ request('status')=='Active'?'selected':'' }}>Active</option>
                <option value="Fulfilled" {{ request('status')=='Fulfilled'?'selected':'' }}>Fulfilled</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px; color:#64748b; display:block; margin-bottom:3px;">From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="width:auto;">
        </div>
        <div>
            <label style="font-size:12px; color:#64748b; display:block; margin-bottom:3px;">To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" style="width:auto;">
        </div>
        <div>
            <label style="font-size:12px; color:#64748b; display:block; margin-bottom:3px;">Campaign</label>
            <select name="harvest" class="form-control" style="width:auto;">
                <option value="">All Campaigns</option>
                @foreach($harvests as $harvest)
                    <option value="{{ $harvest->id }}" {{ request('harvest') == $harvest->id ? 'selected' : '' }}>{{ $harvest->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px; color:#64748b; display:block; margin-bottom:3px;">Service</label>
            <select name="service" class="form-control" style="width:auto;">
                <option value="">All Services</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" {{ request('service') == $service->id ? 'selected' : '' }}>{{ $service->name }} ({{ \Carbon\Carbon::parse($service->service_date)->format('M d, Y') }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px; color:#64748b; display:block; margin-bottom:3px;">Year</label>
            <select name="year" class="form-control" style="width:auto;">
                <option value="">All Years</option>
                @foreach($years as $y)
                    <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
        @if(request()->hasAny(['purpose','status','date_from','date_to','harvest','service','year']))
            <a href="{{ route('pledges.report') }}" style="color:#64748b; font-size:13px; align-self:center;">Clear</a>
        @endif
    </form>

    <!-- Report table -->
    <div style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">
        <table class="table" style="width:100%; margin:0;">
            <thead>
                <tr style="background:#1a3c5e;">
                    <th style="width:40px; color:#fff;">#</th>
                    <th style="color:#fff;">Pledger</th>
                    <th style="color:#fff;">Purpose</th>
                    <th style="text-align:right; color:#fff;">Pledged</th>
                    <th style="text-align:right; color:#fff;">Paid</th>
                    <th style="text-align:right; color:#fff;">Balance</th>
                    <th style="text-align:center; color:#fff;">%</th>
                    <th style="text-align:center; color:#fff;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pledges as $i => $row)
                <tr>
                    <td style="color:#94a3b8;">{{ $i + 1 }}</td>
                    <td style="font-weight:600;">
                        @if($row->member)
                            <a href="{{ route('member-ledger.show', $row->member->id) }}" style="color:#2563eb; text-decoration:none;">{{ $row->name }}</a>
                        @else
                            {{ $row->name }}
                        @endif
                    </td>
                    <td style="color:#64748b;">{{ $row->purpose }}</td>
                    <td style="text-align:right;">GHS {{ number_format($row->pledged, 2) }}</td>
                    <td style="text-align:right; color:#16a34a;">GHS {{ number_format($row->paid, 2) }}</td>
                    <td style="text-align:right; font-weight:700; color:{{ $row->balance > 0 ? '#dc2626' : '#16a34a' }};">GHS {{ number_format($row->balance, 2) }}</td>
                    <td style="text-align:center; color:#64748b;">{{ $row->percent }}%</td>
                    <td style="text-align:center;">
                        <span style="font-size:11px; padding:2px 8px; border-radius:10px; background:{{ $row->balance <= 0 ? '#dcfce7' : '#fef9c3' }}; color:{{ $row->balance <= 0 ? '#16a34a' : '#ca8a04' }};">{{ $row->balance <= 0 ? 'Fulfilled' : $row->status }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:24px;">No pledges match these filters.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #1a3c5e; background:#f8fafc; font-weight:800;">
                    <td colspan="3" style="color:#1a3c5e;">GRAND TOTAL</td>
                    <td style="text-align:right; color:#1a3c5e;">GHS {{ number_format($totals['pledged'], 2) }}</td>
                    <td style="text-align:right; color:#16a34a;">GHS {{ number_format($totals['paid'], 2) }}</td>
                    <td style="text-align:right; color:#dc2626;">GHS {{ number_format($totals['outstanding'], 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>
@endsection
