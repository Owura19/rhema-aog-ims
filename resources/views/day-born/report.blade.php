@extends('layouts.app')

@section('title', 'Day-born Offering Report')

@section('content')

@php($ordinals = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th', 6 => '6th', 7 => '7th'])
@php($months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'])

<div style="margin-bottom:20px;">
    <a href="{{ route('day-born.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Day-born Offering
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">
        Day-born offering · {{ $report->month ? $months[$report->month] . ' ' : '' }}{{ $report->year }}
    </h2>
    <div style="font-size:13px; color:#64748b;">Published weeks only. A shared 1st place counts as a win for each group in the tie.</div>
</div>

<div class="card" style="margin-bottom:20px;">
    <form method="GET" action="{{ route('day-born.report') }}" style="padding:12px 24px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <select name="year" class="form-control" style="width:auto;">
            @foreach($years as $y)
                <option value="{{ $y }}" {{ $report->year === $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
        <select name="month" class="form-control" style="width:auto;">
            <option value="">Whole year</option>
            @foreach($months as $number => $name)
                <option value="{{ $number }}" {{ $report->month === $number ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-outline btn-sm"><i class="fas fa-filter"></i> Show</button>
        <a href="{{ route('day-born.export', ['year' => $report->year]) }}" class="btn-outline btn-sm" style="margin-left:auto;"><i class="fas fa-file-excel"></i> Excel ({{ $report->year }})</a>
    </form>
</div>

<div class="grid-main">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-trophy" style="color:#d97706; margin-right:8px;"></i>Day-born groups</div></div>
        <table class="table">
            <thead>
                <tr><th style="width:70px;">Place</th><th>Group</th><th style="text-align:right;">Total (GH₵)</th><th style="text-align:right;">Weeks won</th></tr>
            </thead>
            <tbody>
                @foreach($report->groups as $group)
                <tr>
                    <td style="font-weight:700;">{{ $report->weeks->isEmpty() ? '—' : $ordinals[$group['position']] }}</td>
                    <td>
                        <div style="font-weight:600; color:#1e293b;">{{ \App\Support\DayBorn\Weekdays::day($group['weekday']) }}</div>
                        <div style="font-size:12px; color:#94a3b8;">{{ \App\Support\DayBorn\Weekdays::akan($group['weekday']) }}</div>
                    </td>
                    <td style="text-align:right; font-weight:600;">{{ number_format((float) $group['total'], 2) }}</td>
                    <td style="text-align:right;">{{ $group['weeks_won'] }}</td>
                </tr>
                @endforeach
                <tr>
                    <td></td>
                    <td style="font-weight:700;">Total ({{ $report->weeks->count() }} {{ $report->weeks->count() === 1 ? 'week' : 'weeks' }})</td>
                    <td style="text-align:right; font-weight:700;">{{ number_format((float) $report->total, 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header"><div class="card-title">Weeks</div></div>
        <table class="table">
            <thead><tr><th>Date</th><th>Winner</th><th style="text-align:right;">Total</th></tr></thead>
            <tbody>
                @forelse($report->weeks as $week)
                <tr style="font-size:13px;">
                    <td style="white-space:nowrap;"><a href="{{ route('day-born.show', $week) }}">{{ $week->service_date->format('j M') }}</a></td>
                    <td>{{ $week->total() === '0.00' ? '—' : collect($week->ranking())->where('position', 1)->map(fn ($row) => \App\Support\DayBorn\Weekdays::day($row['weekday']))->implode(', ') }}</td>
                    <td style="text-align:right;">{{ number_format((float) $week->total(), 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center; padding:24px; color:#94a3b8;">No published weeks in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
