@extends('layouts.app')

@section('title', $week->exists ? 'Edit Day-born Offering' : 'Enter Day-born Offering')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ $week->exists ? route('day-born.show', $week) : route('day-born.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> {{ $week->exists ? 'Back to the week' : 'Back to Day-born Offering' }}
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">
        {{ $week->exists ? 'Edit day-born offering' : 'Enter day-born offering' }}
        @if($week->exists) @include('day-born._status', ['week' => $week]) @endif
    </h2>
    <div style="font-size:13px; color:#64748b;">One total per day-born group. Enter 0 for a group that gave nothing.</div>
</div>

@if($week->isPublished())
    <div class="alert alert-warning" style="max-width:760px; margin-bottom:16px; padding:12px 16px; border-radius:8px; background:#fffbeb; color:#92400e; font-size:14px;">
        This week is published{{ $week->transaction_id ? ' and posted to finance' : '' }}. Changes are recorded in its history with your reason{{ $week->transaction_id ? ', and its finance transaction is updated to match' : '' }}.
    </div>
@endif

<form method="POST" action="{{ $week->exists ? route('day-born.update', $week) : route('day-born.store') }}">
    @csrf
    @if($week->exists) @method('PUT') @endif

    <div class="card" style="margin-bottom:20px; max-width:760px;">
        <div class="card-body">
            <div class="grid-2" style="margin-bottom:16px;">
                <div>
                    <label class="form-label">Service date <span style="color:red;">*</span></label>
                    <input type="date" name="service_date" value="{{ old('service_date', $week->service_date?->format('Y-m-d')) }}"
                           class="form-control {{ $errors->has('service_date') ? 'is-invalid' : '' }}">
                    @error('service_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div style="display:flex; align-items:flex-end;">
                    <label style="font-size:14px; color:#1e293b; margin-bottom:10px;">
                        <input type="hidden" name="special_service" value="0">
                        <input type="checkbox" name="special_service" value="1" {{ old('special_service', $week->special_service) ? 'checked' : '' }}>
                        Special service (not a Sunday)
                    </label>
                </div>
            </div>

            <table class="table" style="margin-bottom:8px;">
                <thead>
                    <tr><th>Day-born group</th><th style="width:200px; text-align:right;">Amount (GH₵)</th></tr>
                </thead>
                <tbody>
                    @foreach(\App\Support\DayBorn\Weekdays::ALL as $weekday => $group)
                    <tr>
                        <td>
                            <div style="font-weight:600; color:#1e293b;">{{ $group['day'] }}</div>
                            <div style="font-size:12px; color:#94a3b8;">{{ $group['male'] }} / {{ $group['female'] }}</div>
                        </td>
                        <td style="text-align:right;">
                            <input type="text" inputmode="decimal" name="amounts[{{ $weekday }}]" value="{{ old("amounts.$weekday", $amounts[$weekday] ?? '') }}"
                                   placeholder="0.00" data-amount
                                   class="form-control {{ $errors->has("amounts.$weekday") ? 'is-invalid' : '' }}" style="text-align:right;">
                            @error("amounts.$weekday")<div class="invalid-feedback" style="text-align:left;">{{ $message }}</div>@enderror
                        </td>
                    </tr>
                    @endforeach
                    <tr>
                        <td style="font-weight:700;">Total</td>
                        <td style="text-align:right; font-weight:700;" data-total>—</td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-bottom:16px;">
                <label class="form-label">Notes (optional)</label>
                <textarea name="notes" rows="2" maxlength="1000" class="form-control {{ $errors->has('notes') ? 'is-invalid' : '' }}">{{ old('notes', $week->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if($week->isPublished())
                <div>
                    <label class="form-label">Reason for the change <span style="color:red;">*</span></label>
                    <input type="text" name="reason" maxlength="500" value="{{ old('reason') }}" placeholder="e.g. Recount of the Friday group"
                           class="form-control {{ $errors->has('reason') ? 'is-invalid' : '' }}">
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>
    </div>

    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> {{ $week->isPublished() ? 'Save changes' : 'Save draft' }}</button>
</form>

<script>
    (function () {
        const inputs = document.querySelectorAll('[data-amount]');
        const total = document.querySelector('[data-total]');
        function refresh() {
            let cents = 0;
            inputs.forEach(function (input) {
                const value = parseFloat((input.value || '0').replace(/,/g, ''));
                if (!isNaN(value)) cents += Math.round(value * 100);
            });
            total.textContent = (cents / 100).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        inputs.forEach(function (input) { input.addEventListener('input', refresh); });
        refresh();
    })();
</script>

@endsection
