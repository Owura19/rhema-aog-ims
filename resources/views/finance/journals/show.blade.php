@extends('layouts.app')

@section('title', 'Journal Entry ' . $journal->reference)

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('finance.journals.index') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Journal Entries</a>
</div>

@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;"><i class="fas fa-triangle-exclamation"></i> {{ session('error') }}</div>
@endif

<div class="card" style="max-width:760px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-book" style="color:#7c3aed;margin-right:8px;"></i>{{ $journal->reference }}</div>
        @if($journal->source_type === 'manual')
        <form method="POST" action="{{ route('finance.journals.destroy', $journal) }}" onsubmit="return confirm('Delete this journal entry? This cannot be undone.');">
            @csrf @method('DELETE')
            <button type="submit" class="btn-outline btn-sm" style="border-color:#fecaca;color:#dc2626;"><i class="fas fa-trash"></i> Delete</button>
        </form>
        @endif
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
            <div>
                <div style="font-size:12px;color:#94a3b8;">Date</div>
                <div style="font-weight:600;">{{ $journal->entry_date->format('M d, Y') }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:#94a3b8;">Source</div>
                <div style="font-weight:600;">{{ ucfirst($journal->source_type ?? 'system') }}</div>
            </div>
            <div style="grid-column:span 2;">
                <div style="font-size:12px;color:#94a3b8;">Description</div>
                <div style="font-weight:600;">{{ $journal->description }}</div>
            </div>
            @if($journal->createdBy)
            <div>
                <div style="font-size:12px;color:#94a3b8;">Posted by</div>
                <div style="font-weight:600;">{{ $journal->createdBy->name }}</div>
            </div>
            @endif
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th style="text-align:right;">Debit (GHS)</th>
                    <th style="text-align:right;">Credit (GHS)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($journal->lines as $line)
                <tr>
                    <td>
                        <span style="font-family:monospace;font-size:12px;color:#94a3b8;">{{ optional($line->account)->code }}</span>
                        {{ optional($line->account)->name }}
                    </td>
                    <td style="text-align:right;">{{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}</td>
                    <td style="text-align:right;">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #e2e8f0;font-weight:800;">
                    <td style="text-align:right;">TOTALS</td>
                    <td style="text-align:right;">{{ number_format($journal->total_debit, 2) }}</td>
                    <td style="text-align:right;">{{ number_format($journal->total_credit, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top:14px;text-align:center;">
            @if($journal->is_balanced)
                <span class="badge badge-success" style="font-size:13px;padding:5px 14px;"><i class="fas fa-circle-check"></i> Balanced</span>
            @else
                <span class="badge badge-danger" style="font-size:13px;padding:5px 14px;"><i class="fas fa-triangle-exclamation"></i> Not balanced</span>
            @endif
        </div>
    </div>
</div>

@endsection