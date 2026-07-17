@extends('layouts.app')

@section('title', 'Harvest Campaigns')

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-wheat-awn" style="color:#ca8a04; margin-right:8px;"></i>Harvest Campaigns</div>
        @can('create finance')
        <a href="{{ route('harvests.create') }}" class="btn-primary btn-sm"><i class="fas fa-plus"></i> New Harvest</a>
        @endcan
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Year</th>
                    <th style="text-align:right;">Target (GHS)</th>
                    <th>Harvest Date</th>
                    <th style="text-align:center;">Pledgers</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($harvests as $harvest)
                <tr>
                    <td>
                        <a href="{{ route('harvests.show', $harvest) }}" style="font-weight:600; color:#2563eb; text-decoration:none;">{{ $harvest->name }}</a>
                    </td>
                    <td>{{ $harvest->year }}</td>
                    <td style="text-align:right; font-weight:600;">{{ number_format($harvest->target_amount, 2) }}</td>
                    <td style="color:#64748b;">{{ $harvest->harvest_date ? $harvest->harvest_date->format('M d, Y') : '—' }}</td>
                    <td style="text-align:center;">{{ $harvest->pledges_count }}</td>
                    <td>
                        @if($harvest->status === 'Active')
                            <span class="badge badge-info">Active</span>
                        @elseif($harvest->status === 'Completed')
                            <span class="badge badge-success">Completed</span>
                        @else
                            <span class="badge badge-danger">Cancelled</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:#94a3b8; padding:30px;">
                        No harvest campaigns yet.
                        @can('create finance')
                        <a href="{{ route('harvests.create') }}" style="color:#2563eb;">Create the first one</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection