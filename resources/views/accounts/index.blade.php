@extends('layouts.app')

@section('title', 'Chart of Accounts')

@section('content')

@php
    // Helper to render action buttons for an account row (defined first so it's available below)
    $actionButtons = function($acct) {
        $toggle = route('accounts.toggle', $acct);
        $delete = route('accounts.destroy', $acct);
        $label  = $acct->is_active ? 'Deactivate' : 'Activate';
        $icon   = $acct->is_active ? 'fa-eye-slash' : 'fa-eye';
        $csrf   = csrf_field();
        $patch  = method_field('PATCH');
        $del    = method_field('DELETE');
        return <<<HTML
        <form method="POST" action="{$toggle}" style="display:inline;">{$csrf}{$patch}
            <button type="submit" class="btn-outline btn-sm" title="{$label}" style="padding:4px 8px;"><i class="fas {$icon}"></i></button>
        </form>
        <form method="POST" action="{$delete}" style="display:inline;" onsubmit="return confirm('Delete this account? Only works if it has no posted activity.');">{$csrf}{$del}
            <button type="submit" class="btn-outline btn-sm" title="Delete" style="padding:4px 8px;border-color:#fecaca;color:#dc2626;"><i class="fas fa-trash"></i></button>
        </form>
        HTML;
    };
@endphp

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;"><i class="fas fa-triangle-exclamation"></i> {{ session('error') }}</div>
@endif

<!-- PDF export + Add Account -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;">
        <form method="GET" action="{{ route('finance.master-report.pdf') }}" target="_blank" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ now()->startOfYear()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ now()->toDateString() }}" class="form-control">
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-file-pdf"></i> Download Full Report (PDF)</button>
        </form>
        <button type="button" class="btn-primary" style="background:#16a34a;" onclick="document.getElementById('addAccountModal').style.display='flex'">
            <i class="fas fa-plus"></i> Add Account
        </button>
    </div>
</div>

<!-- Summary cards -->
<div class="grid-3" style="margin-bottom:22px;">
    <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;"><i class="fas fa-sitemap" style="color:#2563eb;"></i></div><div><div class="stat-value">{{ $counts['total'] }}</div><div class="stat-label">Total Accounts</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;"><i class="fas fa-arrow-up" style="color:#16a34a;"></i></div><div><div class="stat-value">{{ $counts['income'] }}</div><div class="stat-label">Income Accounts</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fee2e2;"><i class="fas fa-arrow-down" style="color:#dc2626;"></i></div><div><div class="stat-value">{{ $counts['expense'] }}</div><div class="stat-label">Expense Accounts</div></div></div>
</div>
<div class="grid-3" style="margin-bottom:22px;">
    <div class="stat-card"><div class="stat-icon" style="background:#e0f2fe;"><i class="fas fa-coins" style="color:#0284c7;"></i></div><div><div class="stat-value">{{ $counts['asset'] }}</div><div class="stat-label">Asset Accounts</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;"><i class="fas fa-file-invoice" style="color:#d97706;"></i></div><div><div class="stat-value">{{ $counts['liability'] }}</div><div class="stat-label">Liability Accounts</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#f3e8ff;"><i class="fas fa-scale-balanced" style="color:#7c3aed;"></i></div><div><div class="stat-value">{{ $counts['equity'] }}</div><div class="stat-label">Equity Accounts</div></div></div>
</div>

@php
    $sections = [
        ['title' => 'Income — Inflows',   'icon' => 'fa-arrow-up',      'color' => '#16a34a', 'data' => $income],
        ['title' => 'Expenditure — Outflows', 'icon' => 'fa-arrow-down','color' => '#dc2626', 'data' => $expense],
        ['title' => 'Assets',             'icon' => 'fa-coins',         'color' => '#0284c7', 'data' => $asset],
        ['title' => 'Liabilities',        'icon' => 'fa-file-invoice',  'color' => '#d97706', 'data' => $liability],
        ['title' => 'Equity / Funds',     'icon' => 'fa-scale-balanced','color' => '#7c3aed', 'data' => $equity],
    ];
@endphp

@foreach($sections as $section)
<div class="card" style="margin-bottom:22px;">
    <div class="card-header">
        <div class="card-title"><i class="fas {{ $section['icon'] }}" style="color:{{ $section['color'] }}; margin-right:8px;"></i>{{ $section['title'] }}</div>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr><th style="width:90px;">Code</th><th style="width:70px;">Ref</th><th>Account Name</th><th style="width:90px;">Status</th><th style="width:120px;text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @forelse($section['data'] as $group)
                <tr style="background:#f8fafc;">
                    <td style="font-weight:700;">{{ $group->code }}</td>
                    <td><span class="badge badge-gray">{{ $group->ref }}</span></td>
                    <td style="font-weight:700; color:#1e293b;">{{ $group->name }}</td>
                    <td>@if($group->is_active)<span class="badge badge-success">Active</span>@else<span class="badge badge-gray">Inactive</span>@endif</td>
                    <td style="text-align:right;">{!! $actionButtons($group) !!}</td>
                </tr>
                    @foreach($group->children as $child)
                    <tr>
                        <td>{{ $child->code }}</td>
                        <td style="color:#94a3b8; font-size:12px;">{{ $child->ref }}</td>
                        <td style="padding-left:24px; color:#475569;">{{ $child->name }}</td>
                        <td>@if($child->is_active)<span class="badge badge-success">Active</span>@else<span class="badge badge-gray">Inactive</span>@endif</td>
                        <td style="text-align:right;">{!! $actionButtons($child) !!}</td>
                    </tr>
                    @endforeach
                @empty
                <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">No accounts in this section.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endforeach

<!-- ADD ACCOUNT MODAL -->
<div id="addAccountModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:1000;align-items:center;justify-content:center;padding:20px;">
    <div class="card" style="max-width:460px;width:100%;margin:0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plus" style="color:#16a34a;margin-right:8px;"></i>Add New Account</div>
            <button type="button" onclick="document.getElementById('addAccountModal').style.display='none'" style="background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('accounts.store') }}">
                @csrf
                <div style="margin-bottom:14px;">
                    <label class="form-label">Account Name <span style="color:red;">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Youth Ministry Offering" required>
                </div>
                <div style="margin-bottom:14px;">
                    <label class="form-label">Type <span style="color:red;">*</span></label>
                    <select name="type" id="add_type" class="form-control" required onchange="filterGroups(this.value)">
                        <option value="Income">Income</option>
                        <option value="Expense">Expense</option>
                        <option value="Asset">Asset</option>
                        <option value="Liability">Liability</option>
                        <option value="Equity">Equity</option>
                    </select>
                </div>
                <div style="margin-bottom:14px;">
                    <label class="form-label">Group / Parent</label>
                    <select name="parent_id" id="add_parent" class="form-control">
                        <option value="">— None (top-level) —</option>
                        @foreach($groups as $g)
                        <option value="{{ $g->id }}" data-type="{{ $g->type }}">{{ $g->code }} — {{ $g->name }}</option>
                        @endforeach
                    </select>
                    <small style="color:#94a3b8;font-size:12px;">Which heading this account sits under. The code is generated automatically.</small>
                </div>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Ref (optional)</label>
                    <input type="text" name="ref" class="form-control" placeholder="e.g. A1-vii">
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Create Account</button>
                    <button type="button" class="btn-outline" onclick="document.getElementById('addAccountModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterGroups(type) {
    const sel = document.getElementById('add_parent');
    Array.from(sel.options).forEach(opt => {
        if (!opt.dataset.type) return;
        opt.hidden = (opt.dataset.type !== type);
    });
    sel.value = '';
}
filterGroups(document.getElementById('add_type').value);
</script>

@endsection