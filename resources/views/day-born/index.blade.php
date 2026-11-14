@extends('layouts.app')

@section('title', 'Day-born Offering')

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-calendar-week" style="color:#2563eb; margin-right:8px;"></i>Day-born Offering</div>
        <div style="display:flex; gap:8px;">
            @if(Route::has('day-born.report'))
                <a href="{{ route('day-born.report') }}" class="btn-outline"><i class="fas fa-chart-bar"></i> Monthly / yearly</a>
            @endif
            @can('record day-born offering')
                <a href="{{ route('day-born.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Enter a week</a>
            @endcan
        </div>
    </div>
    <div style="padding:10px 24px; font-size:13px; color:#64748b; border-bottom:1px solid #f1f5f9;">
        One total per day-born group per service. This is a separate tally: it is not part of the finance totals unless a week is posted to finance when it is published.
    </div>

    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Service date</th>
                    <th>Winner</th>
                    <th style="text-align:right;">Total (GH₵)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($weeks as $week)
                @php($ranking = $week->ranking())
                @php($winners = array_filter($ranking, fn ($row) => $row['position'] === 1 && $row['amount'] !== '0.00'))
                <tr>
                    <td style="white-space:nowrap;">
                        <a href="{{ route('day-born.show', $week) }}" style="font-weight:600; color:#1e293b; text-decoration:none;">{{ $week->service_date->format('D j M Y') }}</a>
                    </td>
                    <td style="font-size:13px;">
                        {{ $winners ? collect($winners)->map(fn ($row) => \App\Support\DayBorn\Weekdays::label($row['weekday']))->implode(', ') : '—' }}
                    </td>
                    <td style="text-align:right; font-weight:600;">{{ number_format((float) $week->total(), 2) }}</td>
                    <td>@include('day-born._status', ['week' => $week])</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center; padding:40px; color:#94a3b8;">No weeks recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($weeks->hasPages())
        <div style="padding:16px 24px;">{{ $weeks->links() }}</div>
    @endif
</div>

@endsection
