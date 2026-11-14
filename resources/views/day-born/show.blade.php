@extends('layouts.app')

@section('title', 'Day-born Offering ' . $week->service_date->format('j M Y'))

@section('content')

@php($ordinals = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th', 6 => '6th', 7 => '7th'])

<div style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
        <a href="{{ route('day-born.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Day-born Offering
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $week->service_date->format('l j F Y') }}</h2>
        <div>@include('day-born._status', ['week' => $week])</div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        @if(! $week->isPublished())
            @can('record day-born offering')
                <a href="{{ route('day-born.edit', $week) }}" class="btn-outline"><i class="fas fa-edit"></i> Edit</a>
                <form method="POST" action="{{ route('day-born.destroy', $week) }}" onsubmit="return confirm(@js('Delete this draft?'))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete draft</button>
                </form>
            @endcan
        @else
            @can('publish day-born offering')
                <a href="{{ route('day-born.edit', $week) }}" class="btn-outline"><i class="fas fa-edit"></i> Edit</a>
            @endcan
        @endif
    </div>
</div>

<div class="grid-main">
    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><div class="card-title"><i class="fas fa-trophy" style="color:#d97706; margin-right:8px;"></i>Results</div></div>
            <table class="table">
                <thead>
                    <tr><th style="width:70px;">Place</th><th>Day-born group</th><th style="text-align:right;">Amount (GH₵)</th></tr>
                </thead>
                <tbody>
                    @foreach($ranking as $row)
                    <tr>
                        <td style="font-weight:700; color:{{ $row['position'] === 1 ? '#d97706' : '#1e293b' }};">{{ $ordinals[$row['position']] }}</td>
                        <td>
                            <div style="font-weight:600; color:#1e293b;">{{ \App\Support\DayBorn\Weekdays::day($row['weekday']) }}</div>
                            <div style="font-size:12px; color:#94a3b8;">{{ \App\Support\DayBorn\Weekdays::akan($row['weekday']) }}</div>
                        </td>
                        <td style="text-align:right; font-weight:600;">{{ number_format((float) $row['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                    <tr>
                        <td></td>
                        <td style="font-weight:700;">Total</td>
                        <td style="text-align:right; font-weight:700;">{{ number_format((float) $week->total(), 2) }}</td>
                    </tr>
                </tbody>
            </table>
            @if($week->notes)
                <div style="padding:12px 24px; font-size:13px; color:#64748b; border-top:1px solid #f1f5f9;">{{ $week->notes }}</div>
            @endif
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-clock-rotate-left" style="color:#7c3aed; margin-right:8px;"></i>History</div></div>
            <table class="table">
                <thead>
                    <tr><th>When</th><th>Who</th><th>What</th><th style="text-align:right;">From</th><th style="text-align:right;">To</th><th>Reason</th></tr>
                </thead>
                <tbody>
                    @foreach($week->changes as $change)
                    <tr style="font-size:13px;">
                        <td style="white-space:nowrap;">{{ $change->created_at?->format('j M Y, g:i A') }}</td>
                        <td>{{ $change->changedBy?->name ?? '—' }}</td>
                        <td>
                            {{ str_replace('_', ' ', ucfirst($change->action)) }}
                            @if($change->weekday !== null)<span style="color:#94a3b8;">· {{ \App\Support\DayBorn\Weekdays::day($change->weekday) }}</span>@endif
                        </td>
                        <td style="text-align:right;">{{ $change->old_amount !== null ? number_format((float) $change->old_amount, 2) : '' }}</td>
                        <td style="text-align:right;">{{ $change->new_amount !== null ? number_format((float) $change->new_amount, 2) : '' }}</td>
                        <td>{{ $change->reason }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><div class="card-title">Details</div></div>
            <div class="card-body" style="font-size:13px; line-height:1.9;">
                <div><span style="color:#94a3b8;">Recorded by:</span> {{ $week->recordedBy?->name ?? '—' }}</div>
                @if($week->isPublished())
                    <div><span style="color:#94a3b8;">Published:</span> {{ $week->published_at?->format('j M Y, g:i A') }} by {{ $week->publishedBy?->name ?? '—' }}</div>
                @endif
                @if($week->transaction)
                    <div><span style="color:#94a3b8;">Finance:</span>
                        @can('view finance')
                            <a href="{{ route('finance.show', $week->transaction) }}">{{ $week->transaction->reference }}</a>
                        @else
                            {{ $week->transaction->reference }}
                        @endcan
                    </div>
                @else
                    <div><span style="color:#94a3b8;">Finance:</span> separate tally (not in finance totals)</div>
                @endif
            </div>
        </div>

        @can('publish day-born offering')
        <div class="card">
            <div class="card-header"><div class="card-title">{{ $week->isPublished() ? 'Unpublish' : 'Publish' }}</div></div>
            <div class="card-body">
                @if(! $week->isPublished())
                    <form method="POST" action="{{ route('day-born.publish', $week) }}">
                        @csrf
                        <label style="font-size:14px; color:#1e293b; display:block; margin-bottom:10px;">
                            <input type="hidden" name="post_to_finance" value="0">
                            <input type="checkbox" name="post_to_finance" value="1" {{ old('post_to_finance') ? 'checked' : '' }}>
                            Also post the total to Finance
                        </label>
                        <div style="font-size:12px; color:#94a3b8; margin-bottom:10px;">
                            Off by default. Only tick it if this money is NOT already in the Sunday offering recorded in Finance, otherwise it would be counted twice.
                        </div>
                        <label class="form-label">Income account</label>
                        <select name="finance_account_id" class="form-control {{ $errors->has('finance_account_id') ? 'is-invalid' : '' }}" style="margin-bottom:8px;">
                            <option value="">—</option>
                            @foreach($incomeAccounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('finance_account_id', $defaultIncomeAccountId) === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} · {{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('finance_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <label class="form-label">Received into</label>
                        <select name="cash_account_id" class="form-control {{ $errors->has('cash_account_id') ? 'is-invalid' : '' }}" style="margin-bottom:12px;">
                            <option value="">—</option>
                            @foreach($cashAccounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('cash_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} · {{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('cash_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Publish</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('day-born.unpublish', $week) }}"
                          onsubmit="return confirm(@js($week->transaction_id ? 'Unpublish? Its finance transaction will be voided.' : 'Unpublish this week?'))">
                        @csrf
                        <label class="form-label">Reason (optional)</label>
                        <input type="text" name="reason" maxlength="500" class="form-control" style="margin-bottom:12px;">
                        <button type="submit" class="btn-outline"><i class="fas fa-eye-slash"></i> Unpublish</button>
                    </form>
                @endif
            </div>
        </div>
        @endcan
    </div>
</div>

@endsection
