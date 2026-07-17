@extends('layouts.app')

@section('title', 'Budget vs Actual')

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('finance.reports-hub') }}" style="color:#64748b; text-decoration:none; font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Financial Reports</a>
</div>

<!-- Year selector + set budget -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('finance.budget.report') }}" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label class="form-label">Year</label>
                <select name="year" class="form-control" onchange="this.form.submit()" style="min-width:140px;">
                    @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div style="margin-left:auto;">
                <a href="{{ route('finance.budget.edit', ['year' => $year]) }}" class="btn-primary"><i class="fas fa-bullseye"></i> Set / Edit Budget</a>
            </div>
        </form>
    </div>
</div>

@php
    // Helper for variance colour: for income, above budget is good (green);
    // for expense, below budget is good (green).
@endphp

<!-- INCOME -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-arrow-up" style="color:#16a34a; margin-right:8px;"></i>Income — Budget vs Actual ({{ $year }})</div>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:70px;">Ref</th>
                    <th>Account Group</th>
                    <th style="text-align:right;">Budget</th>
                    <th style="text-align:right;">Actual</th>
                    <th style="text-align:right;">Variance</th>
                    <th style="text-align:right; width:90px;">% Achieved</th>
                </tr>
            </thead>
            <tbody>
                @foreach($income as $row)
                <tr>
                    <td><span class="badge badge-gray">{{ $row->ref }}</span></td>
                    <td style="color:#374151;">{{ $row->name }}</td>
                    <td style="text-align:right;">{{ number_format($row->budget, 2) }}</td>
                    <td style="text-align:right; font-weight:600;">{{ number_format($row->actual, 2) }}</td>
                    <td style="text-align:right; color:{{ $row->variance >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $row->variance >= 0 ? '+' : '' }}{{ number_format($row->variance, 2) }}
                    </td>
                    <td style="text-align:right; font-weight:600;">{{ $row->percent }}%</td>
                </tr>
                @endforeach
                <tr style="border-top:2px solid #16a34a; background:#f8fafc;">
                    <td colspan="2" style="font-weight:800; color:#15803d;">TOTAL INCOME</td>
                    <td style="text-align:right; font-weight:800;">{{ number_format($totals['income_budget'], 2) }}</td>
                    <td style="text-align:right; font-weight:800;">{{ number_format($totals['income_actual'], 2) }}</td>
                    <td style="text-align:right; font-weight:800; color:{{ ($totals['income_actual'] - $totals['income_budget']) >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ ($totals['income_actual'] - $totals['income_budget']) >= 0 ? '+' : '' }}{{ number_format($totals['income_actual'] - $totals['income_budget'], 2) }}
                    </td>
                    <td style="text-align:right; font-weight:800;">
                        {{ $totals['income_budget'] > 0 ? round(($totals['income_actual'] / $totals['income_budget']) * 100, 1) : 0 }}%
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- EXPENDITURE -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-arrow-down" style="color:#dc2626; margin-right:8px;"></i>Expenditure — Budget vs Actual ({{ $year }})</div>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:70px;">Ref</th>
                    <th>Account Group</th>
                    <th style="text-align:right;">Budget</th>
                    <th style="text-align:right;">Actual</th>
                    <th style="text-align:right;">Variance</th>
                    <th style="text-align:right; width:90px;">% Used</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expense as $row)
                <tr>
                    <td><span class="badge badge-gray">{{ $row->ref }}</span></td>
                    <td style="color:#374151;">{{ $row->name }}</td>
                    <td style="text-align:right;">{{ number_format($row->budget, 2) }}</td>
                    <td style="text-align:right; font-weight:600;">{{ number_format($row->actual, 2) }}</td>
                    {{-- For expense: under budget (negative variance) is good = green --}}
                    <td style="text-align:right; color:{{ $row->variance <= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $row->variance >= 0 ? '+' : '' }}{{ number_format($row->variance, 2) }}
                    </td>
                    <td style="text-align:right; font-weight:600;">{{ $row->percent }}%</td>
                </tr>
                @endforeach
                <tr style="border-top:2px solid #dc2626; background:#f8fafc;">
                    <td colspan="2" style="font-weight:800; color:#b91c1c;">TOTAL EXPENDITURE</td>
                    <td style="text-align:right; font-weight:800;">{{ number_format($totals['expense_budget'], 2) }}</td>
                    <td style="text-align:right; font-weight:800;">{{ number_format($totals['expense_actual'], 2) }}</td>
                    <td style="text-align:right; font-weight:800; color:{{ ($totals['expense_actual'] - $totals['expense_budget']) <= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ ($totals['expense_actual'] - $totals['expense_budget']) >= 0 ? '+' : '' }}{{ number_format($totals['expense_actual'] - $totals['expense_budget'], 2) }}
                    </td>
                    <td style="text-align:right; font-weight:800;">
                        {{ $totals['expense_budget'] > 0 ? round(($totals['expense_actual'] / $totals['expense_budget']) * 100, 1) : 0 }}%
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endsection