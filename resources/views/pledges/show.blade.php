@extends('layouts.app')

@section('title', 'Pledge ' . $pledge->reference)

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('pledges.index') }}" style="color:#64748b; text-decoration:none; font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Pledges</a>
</div>

<div class="grid-main">

    <!-- LEFT: pledge details + payment history -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Overview card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-hand-holding-heart" style="color:#7c3aed; margin-right:8px;"></i>{{ $pledge->reference }}
                    @if($pledge->status === 'Fulfilled')
                        <span class="badge badge-success" style="margin-left:8px;">Fulfilled</span>
                    @elseif($pledge->status === 'Active')
                        <span class="badge badge-info" style="margin-left:8px;">Active</span>
                    @else
                        <span class="badge badge-danger" style="margin-left:8px;">Cancelled</span>
                    @endif
                </div>
                @if($pledge->status !== 'Cancelled')
                @can('create finance')
                <form method="POST" action="{{ route('pledges.cancel', $pledge) }}" onsubmit="return confirm('Cancel this pledge?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-outline btn-sm" style="border-color:#fecaca; color:#dc2626;">Cancel Pledge</button>
                </form>
                @endcan
                @endif
            </div>
            <div class="card-body">
                <div class="grid-2" style="margin-bottom:20px;">
                    <div>
                        <div class="stat-label">Pledger</div>
                        <div style="font-weight:600; color:#1e293b;">{{ $pledge->pledger_label }}</div>
                    </div>
                    <div>
                        <div class="stat-label">Purpose</div>
                        <div style="font-weight:600; color:#1e293b;">{{ optional($pledge->purpose)->name }}</div>
                    </div>
                    <div>
                        <div class="stat-label">Date Pledged</div>
                        <div style="font-weight:600; color:#1e293b;">{{ $pledge->date_pledged->format('M d, Y') }}</div>
                    </div>
                    <div>
                        <div class="stat-label">Target Date</div>
                        <div style="font-weight:600; color:#1e293b;">{{ $pledge->target_date ? $pledge->target_date->format('M d, Y') : '—' }}</div>
                    </div>
                </div>

                <!-- Progress -->
                <div style="margin-bottom:8px; display:flex; justify-content:space-between; font-size:13px;">
                    <span style="color:#64748b;">Progress</span>
                    <span style="font-weight:700;">{{ $pledge->progress_percent }}%</span>
                </div>
                <div style="background:#f1f5f9; border-radius:20px; height:12px; overflow:hidden; margin-bottom:16px;">
                    <div style="background:#16a34a; height:12px; width:{{ $pledge->progress_percent }}%;"></div>
                </div>

                <div class="grid-3">
                    <div>
                        <div class="stat-label">Pledged</div>
                        <div class="stat-value" style="font-size:18px;">GHS {{ number_format($pledge->amount_pledged, 2) }}</div>
                    </div>
                    <div>
                        <div class="stat-label">Paid</div>
                        <div class="stat-value" style="font-size:18px; color:#16a34a;">GHS {{ number_format($pledge->total_paid, 2) }}</div>
                    </div>
                    <div>
                        <div class="stat-label">Balance</div>
                        <div class="stat-value" style="font-size:18px; color:{{ $pledge->balance > 0 ? '#dc2626' : '#64748b' }};">GHS {{ number_format($pledge->balance, 2) }}</div>
                    </div>
                </div>

                @if($pledge->notes)
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9;">
                    <div class="stat-label">Notes</div>
                    <div style="color:#374151; font-size:14px;">{{ $pledge->notes }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Payment history -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-receipt" style="color:#16a34a; margin-right:8px;"></i>Payment History</div>
            </div>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pledge->payments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date->format('M d, Y') }}</td>
                            <td style="font-weight:600; color:#16a34a;">GHS {{ number_format($payment->amount, 2) }}</td>
                            <td><span class="badge badge-gray">{{ $payment->payment_method }}</span></td>
                            <td style="font-size:13px; color:#64748b;">{{ $payment->notes ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="text-align:center; color:#94a3b8; padding:24px;">No payments recorded yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT: record payment -->
    <div>
        @if($pledge->status !== 'Cancelled' && $pledge->balance > 0)
        @can('create finance')
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle" style="color:#2563eb; margin-right:8px;"></i>Record Payment</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pledges.payments.store', $pledge) }}">
                    @csrf
                    <div style="margin-bottom:14px;">
                        <label class="form-label">Amount (GHS)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        <div style="font-size:12px; color:#94a3b8; margin-top:4px;">Balance: GHS {{ number_format($pledge->balance, 2) }}</div>
                    </div>
                    <div style="margin-bottom:14px;">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div style="margin-bottom:14px;">
                        <label class="form-label">Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="Cash">Cash</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div style="margin-bottom:18px;">
                        <label class="form-label">Notes <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-check"></i> Record Payment</button>
                </form>
                <div style="font-size:12px; color:#94a3b8; margin-top:12px; text-align:center;">
                    This payment posts to Finance as income automatically.
                </div>
            </div>
        </div>
        @endcan
        @elseif($pledge->status === 'Fulfilled')
        <div class="card">
            <div class="card-body" style="text-align:center; padding:30px;">
                <i class="fas fa-check-circle" style="color:#16a34a; font-size:36px;"></i>
                <div style="margin-top:12px; font-weight:600; color:#1e293b;">Pledge Fulfilled</div>
                <div style="font-size:13px; color:#64748b;">This pledge has been fully paid.</div>
            </div>
        </div>
        @endif
    </div>

</div>

@endsection