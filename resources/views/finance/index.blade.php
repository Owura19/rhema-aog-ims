@extends('layouts.app')

@section('title', 'Finance')

@section('content')

<!-- Stats Row -->
<div style="display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-arrow-up" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($stats['total_income'], 2) }}</div>
            <div class="stat-label">Income This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">
            <i class="fas fa-arrow-down" style="color:#dc2626;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($stats['total_expense'], 2) }}</div>
            <div class="stat-label">Expenses This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-hand-holding-heart" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($stats['total_tithes'], 2) }}</div>
            <div class="stat-label">Tithes This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-church" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS {{ number_format($stats['total_offerings'], 2) }}</div>
            <div class="stat-label">Offerings This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:{{ $stats['net_balance'] >= 0 ? '#dcfce7' : '#fee2e2' }};">
            <i class="fas fa-balance-scale" style="color:{{ $stats['net_balance'] >= 0 ? '#16a34a' : '#dc2626' }};"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px; color:{{ $stats['net_balance'] >= 0 ? '#16a34a' : '#dc2626' }};">GHS {{ number_format($stats['net_balance'], 2) }}</div>
            <div class="stat-label">Net Balance</div>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-money-bill-wave" style="color:#16a34a; margin-right:8px;"></i>Transactions</div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('finance.report') }}" class="btn-outline btn-sm"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="{{ route('finance.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Record Transaction</a>
        </div>
    </div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <form method="GET" action="{{ route('finance.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search reference, name..." class="form-control" style="width:220px;">
            <select name="type" class="form-control" style="width:150px;">
                <option value="">All Types</option>
                @foreach(['Tithe','Offering','First Fruit','Seed','Pledge','Donation','Expense','Other'] as $type)
                    <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
            <select name="category" class="form-control" style="width:130px;">
                <option value="">All Categories</option>
                <option value="Income" {{ request('category') == 'Income' ? 'selected' : '' }}>Income</option>
                <option value="Expense" {{ request('category') == 'Expense' ? 'selected' : '' }}>Expense</option>
            </select>
            <select name="payment_method" class="form-control" style="width:160px;">
                <option value="">All Methods</option>
                @foreach(['Cash','Mobile Money','Bank Transfer','Cheque','Other'] as $method)
                    <option value="{{ $method }}" {{ request('payment_method') == $method ? 'selected' : '' }}>{{ $method }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="width:150px;">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" style="width:150px;">
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filter</button>
            @if(request()->hasAny(['search','type','category','payment_method','date_from','date_to']))
                <a href="{{ route('finance.index') }}" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Payer</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                <tr>
                    <td>
                        <span style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:3px 8px; border-radius:4px;">{{ $transaction->reference }}</span>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:13px;">{{ $transaction->payer_label }}</div>
                        @if($transaction->churchService)
                            <div style="font-size:11px; color:#94a3b8;">{{ $transaction->churchService->name }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $transaction->category === 'Income' ? 'badge-success' : 'badge-danger' }}">
                            {{ $transaction->type }}
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:700; font-size:15px; color:{{ $transaction->category === 'Income' ? '#16a34a' : '#dc2626' }};">
                            {{ $transaction->category === 'Expense' ? '-' : '+' }} GHS {{ number_format($transaction->amount, 2) }}
                        </span>
                    </td>
                    <td style="font-size:13px; color:#64748b;">{{ $transaction->payment_method }}</td>
                    <td style="font-size:13px; color:#64748b;">{{ $transaction->transaction_date->format('M d, Y') }}</td>
                    <td>
                        @if($transaction->status === 'Confirmed')
                            <span class="badge badge-success">Confirmed</span>
                        @elseif($transaction->status === 'Pending')
                            <span class="badge badge-warning">Pending</span>
                        @else
                            <span class="badge badge-danger">Cancelled</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('finance.show', $transaction) }}" class="btn-outline btn-sm"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('finance.edit', $transaction) }}" class="btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="{{ route('finance.destroy', $transaction) }}" onsubmit="return confirm('Delete this transaction?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-money-bill-wave" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No transactions yet</div>
                        <a href="{{ route('finance.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Record First Transaction</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($transactions->hasPages())
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; color:#64748b;">
            Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }} of {{ $transactions->total() }} transactions
        </div>
        {{ $transactions->links() }}
    </div>
    @endif
</div>

@endsection