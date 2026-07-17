@extends('layouts.app')

@section('title', 'Journal Entries')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <a href="{{ route('finance.reports-hub') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Financial Reports</a>
    <a href="{{ route('finance.journals.create') }}" class="btn-primary btn-sm"><i class="fas fa-plus"></i> New Journal Entry</a>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;"><i class="fas fa-triangle-exclamation"></i> {{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-book" style="color:#7c3aed;margin-right:8px;"></i>Journal Entries</div>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Source</th>
                    <th style="text-align:right;">Amount (GHS)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                <tr>
                    <td style="font-family:monospace;font-size:12.5px;font-weight:600;">{{ $entry->reference }}</td>
                    <td style="font-size:13px;color:#64748b;">{{ $entry->entry_date->format('M d, Y') }}</td>
                    <td style="font-weight:600;color:#1e293b;">{{ $entry->description }}</td>
                    <td>
                        @if($entry->source_type === 'manual')
                            <span class="badge badge-info">Manual</span>
                        @elseif($entry->source_type === 'transaction')
                            <span class="badge badge-gray">Transaction</span>
                        @else
                            <span class="badge badge-gray">{{ ucfirst($entry->source_type ?? 'system') }}</span>
                        @endif
                    </td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($entry->total_debit, 2) }}</td>
                    <td style="text-align:right;">
                        <a href="{{ route('finance.journals.show', $entry) }}" style="color:#2563eb;text-decoration:none;font-size:13px;font-weight:600;">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:36px;">
                    <i class="fas fa-book" style="font-size:26px;color:#cbd5e1;"></i>
                    <div style="margin-top:10px;">No journal entries yet.</div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($entries->hasPages())
    <div style="padding:16px 22px;">{{ $entries->links() }}</div>
    @endif
</div>

@endsection