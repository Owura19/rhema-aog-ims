{{-- Active loans at a glance (Pastor, Super Admin and Finance Officer dashboards; needs 'view finance'). --}}
@can('view finance')
@foreach(\App\Models\Loan::where('status', 'Active')->orderBy('opening_date')->get() as $loan)
    @php($progress = $loan->progressPercent())
    <div class="card loan-card" style="margin-bottom:20px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-landmark" style="color:#1a3c5e; margin-right:8px;"></i>Loan: {{ $loan->lender }}</div>
            <a href="{{ route('loans.show', $loan) }}" class="btn-outline btn-sm">View</a>
        </div>
        <div class="card-body">
            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px 20px; margin-bottom:10px;">
                <div><div style="font-size:12px; color:#64748b;">Balance remaining</div><div class="money" style="font-size:20px; font-weight:800; color:#1e293b;">@cedis($loan->balance())</div></div>
                <div><div style="font-size:12px; color:#64748b;">Repaid</div><div class="money" style="font-size:20px; font-weight:800; color:#16a34a;">@cedis($loan->repaidTotal())</div></div>
                <div style="align-self:flex-end; font-weight:700;">{{ $progress }}%</div>
            </div>
            <div style="background:#e2e8f0; border-radius:8px; overflow:hidden; height:10px;" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                <div style="background:#16a34a; height:10px; width:{{ $progress }}%;"></div>
            </div>
            <div style="font-size:12px; color:#94a3b8; margin-top:6px;">Repaid by {{ implode(' and ', $loan->counted_types) }} from {{ $loan->repayment_start_date->format('d M Y') }}</div>
        </div>
    </div>
@endforeach
@endcan
