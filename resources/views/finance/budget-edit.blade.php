@extends('layouts.app')

@section('title', 'Set Budget')

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('finance.budget.report', ['year' => $year]) }}" style="color:#64748b; text-decoration:none; font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Budget vs Actual</a>
</div>

<!-- Year selector -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('finance.budget.edit') }}" style="display:flex; gap:16px; align-items:flex-end;">
            <div>
                <label class="form-label">Budget Year</label>
                <select name="year" class="form-control" onchange="this.form.submit()" style="min-width:140px;">
                    @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('finance.budget.update', ['year' => $year]) }}">
    @csrf

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-bullseye" style="color:#ca8a04; margin-right:8px;"></i>Set Budget Figures — {{ $year }}</div>
            <button type="submit" class="btn-primary btn-sm"><i class="fas fa-save"></i> Save Budget</button>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:90px;">Ref</th>
                        <th>Account Group</th>
                        <th style="text-align:right; width:220px;">Budget Amount (GHS)</th>
                    </tr>
                </thead>
                <tbody>
                    @php $lastType = null; @endphp
                    @foreach($groups as $group)
                        @if($group->type !== $lastType)
                        <tr style="background:{{ $group->type === 'Income' ? '#dcfce7' : '#fee2e2' }};">
                            <td colspan="3" style="font-weight:800; color:{{ $group->type === 'Income' ? '#15803d' : '#b91c1c' }};">
                                {{ $group->type === 'Income' ? 'INCOME — INFLOWS' : 'EXPENDITURE — OUTFLOWS' }}
                            </td>
                        </tr>
                        @php $lastType = $group->type; @endphp
                        @endif
                        <tr>
                            <td><span class="badge badge-gray">{{ $group->ref }}</span></td>
                            <td style="color:#374151;">{{ $group->name }}</td>
                            <td style="text-align:right;">
                                <input type="number" step="0.01" min="0"
                                    name="amounts[{{ $group->id }}]"
                                    value="{{ old('amounts.'.$group->id, $budgets[$group->id] ?? '') }}"
                                    class="form-control" placeholder="0.00" style="text-align:right;">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body" style="border-top:1px solid #f1f5f9;">
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Budget for {{ $year }}</button>
        </div>
    </div>
</form>

@endsection