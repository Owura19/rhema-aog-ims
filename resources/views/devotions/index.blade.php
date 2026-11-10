@extends('layouts.app')

@section('title', 'Devotions')

@section('content')

{{-- Next 14 days: every date at a glance, so gaps are obvious --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-calendar-week" style="color:#2563eb; margin-right:8px;"></i>Next 14 days</div>
    </div>
    <div class="card-body" style="display:flex; gap:8px; flex-wrap:wrap;">
        @foreach($strip as $day)
            @php
                $href = $day['devotion']
                    ? route('devotions.show', $day['devotion'])
                    : (auth()->user()->can('manage devotions') ? route('devotions.create', ['date' => $day['date']->toDateString()]) : null);
            @endphp
            <a @if($href) href="{{ $href }}" @endif data-day="{{ $day['date']->toDateString() }}" data-state="{{ $day['state'] }}"
               style="display:block; min-width:84px; padding:8px 10px; border:1px solid #e2e8f0; border-radius:10px; text-align:center; text-decoration:none; color:#1e293b; {{ $day['state'] === 'missing' ? 'border-style:dashed;' : '' }}">
                <div style="font-size:11px; color:#94a3b8;">{{ $day['date']->format('D') }}</div>
                <div style="font-weight:700; font-size:13px; margin-bottom:4px;">{{ $day['date']->format('j M') }}</div>
                @include('devotions._state', ['state' => $day['state']])
            </a>
        @endforeach
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-book-open" style="color:#2563eb; margin-right:8px;"></i>Devotions</div>
        @can('manage devotions')
        <div style="display:flex; gap:8px;">
            <a href="{{ route('devotion-themes.index') }}" class="btn-outline"><i class="fas fa-tags"></i> Themes</a>
            <a href="{{ route('devotions.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New Devotion</a>
        </div>
        @endcan
    </div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <form method="GET" action="{{ route('devotions.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <select name="status" class="form-control" style="width:170px;">
                <option value="">All statuses</option>
                @foreach(['draft' => 'Draft', 'approved' => 'Approved (not yet live)', 'live' => 'Live'] as $value => $label)
                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <input type="month" name="month" value="{{ request('month') }}" class="form-control" style="width:170px;">
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filter</button>
            @if(request()->hasAny(['status', 'month']))
                <a href="{{ route('devotions.index') }}" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th>Theme</th>
                </tr>
            </thead>
            <tbody>
                @forelse($devotions as $devotion)
                <tr>
                    <td style="white-space:nowrap; font-size:13px; color:#64748b;">{{ $devotion->publish_date->format('D j M Y') }}</td>
                    <td>
                        <a href="{{ route('devotions.show', $devotion) }}" style="font-weight:600; color:#1e293b; text-decoration:none;">{{ $devotion->title }}</a>
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $devotion->scripture_reference }}</td>
                    <td>@include('devotions._state', ['state' => $devotion->state()])</td>
                    <td>
                        @if($devotion->source === \App\Models\Devotion::SOURCE_AI)
                            <span class="badge badge-info">AI</span>
                            @if($devotion->ai_edited)
                                <span class="badge badge-gray">edited</span>
                            @endif
                        @else
                            <span class="badge badge-gray">Manual</span>
                        @endif
                    </td>
                    <td style="font-size:13px;">
                        @if($devotion->no_theme)
                            <span class="badge badge-danger">no theme</span>
                        @else
                            {{ $devotion->theme?->title ?? '—' }}
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">No devotions found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($devotions->hasPages())
        <div style="padding:16px 24px;">{{ $devotions->links() }}</div>
    @endif
</div>

@endsection
