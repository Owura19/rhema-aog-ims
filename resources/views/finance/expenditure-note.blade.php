@extends('layouts.app')

@section('title', 'Expenditure Note')

@section('content')

<!-- Date range picker -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('finance.expenditure-note') }}" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>
            <div>
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Generate</button>
            </div>
            <div style="margin-left:auto;">
                <a href="{{ route('finance.expenditure-note.pdf', ['from' => $from, 'to' => $to]) }}" class="btn-outline">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-arrow-down" style="color:#dc2626; margin-right:8px;"></i>Expenditure Note — Detailed</div>
        <div style="font-size:13px; color:#64748b;">{{ \Carbon\Carbon::parse($from)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}</div>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:90px;">Ref</th>
                    <th>Account</th>
                    <th style="text-align:right; width:160px;">Amount (GHS)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groups as $group)
                <tr style="background:#f1f5f9;">
                    <td style="font-weight:800; color:#1e293b;">{{ $group->ref }}</td>
                    <td style="font-weight:800; color:#1e293b;">{{ $group->name }}</td>
                    <td style="text-align:right; font-weight:800; color:#1e293b;">{{ number_format($group->total, 2) }}</td>
                </tr>
                @forelse($group->children as $child)
                <tr>
                    <td style="font-size:12px; color:#94a3b8;">{{ $child->ref }}</td>
                    <td style="padding-left:32px; color:#374151;">{{ $child->name }}</td>
                    <td style="text-align:right; color:{{ $child->total != 0 ? '#dc2626' : '#94a3b8' }};">{{ number_format($child->total, 2) }}</td>
                </tr>
                @empty
                @endforelse
                @endforeach

                <tr style="border-top:3px double #dc2626; background:#fee2e2;">
                    <td colspan="2" style="font-weight:800; color:#b91c1c; font-size:15px;">TOTAL EXPENDITURE</td>
                    <td style="text-align:right; font-weight:800; font-size:15px; color:#b91c1c;">{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endsection