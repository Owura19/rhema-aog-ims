@extends('layouts.app')

@section('title', 'Finance Analytics')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <a href="{{ route('finance.index') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Finance</a>
        <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-top:4px;">Finance Analytics</h2>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <label style="font-size:13px;color:#64748b;">Year</label>
        <select name="year" class="form-control" style="width:auto;" onchange="this.form.submit()">
            @foreach($years as $y)
            <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </form>
</div>

<!-- Key figures -->
<div class="grid-3" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;"><i class="fas fa-arrow-up" style="color:#16a34a;"></i></div>
        <div>
            <div class="stat-value">GHS {{ number_format($incomeYear, 2) }}</div>
            <div class="stat-label">Total Income ({{ $year }})</div>
            <div style="font-size:11.5px;color:#94a3b8;margin-top:2px;">This month: GHS {{ number_format($incomeMonth, 2) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;"><i class="fas fa-arrow-down" style="color:#dc2626;"></i></div>
        <div>
            <div class="stat-value">GHS {{ number_format($expenseYear, 2) }}</div>
            <div class="stat-label">Total Expenditure ({{ $year }})</div>
            <div style="font-size:11.5px;color:#94a3b8;margin-top:2px;">This month: GHS {{ number_format($expenseMonth, 2) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:{{ $netYear >= 0 ? '#dbeafe' : '#fef3c7' }};"><i class="fas fa-scale-balanced" style="color:{{ $netYear >= 0 ? '#2563eb' : '#d97706' }};"></i></div>
        <div>
            <div class="stat-value" style="color:{{ $netYear >= 0 ? '#16a34a' : '#dc2626' }};">GHS {{ number_format($netYear, 2) }}</div>
            <div class="stat-label">Net Balance ({{ $year }})</div>
            <div style="font-size:11.5px;color:#94a3b8;margin-top:2px;">{{ $netYear >= 0 ? 'Surplus' : 'Deficit' }}</div>
        </div>
    </div>
</div>

<!-- Monthly income vs expense -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-chart-column" style="color:#2563eb;margin-right:8px;"></i>Monthly Income vs Expenditure — {{ $year }}</div></div>
    <div class="card-body"><div style="height:320px;"><canvas id="monthlyChart"></canvas></div></div>
</div>

<!-- Two pies -->
<div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie" style="color:#16a34a;margin-right:8px;"></i>Income by Type</div></div>
        <div class="card-body"><div style="height:300px;"><canvas id="incomeChart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie" style="color:#dc2626;margin-right:8px;"></i>Expenditure by Category</div></div>
        <div class="card-body"><div style="height:300px;"><canvas id="expenseChart"></canvas></div></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const palette = ['#2563eb','#16a34a','#dc2626','#d97706','#7c3aed','#0891b2','#db2777','#65a30d','#ea580c','#4f46e5','#0d9488','#be123c'];

// Monthly bar chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: @json($months),
        datasets: [
            { label: 'Income', data: @json($monthlyIncome), backgroundColor: '#16a34a', borderRadius: 5 },
            { label: 'Expenditure', data: @json($monthlyExpense), backgroundColor: '#dc2626', borderRadius: 5 },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => 'GHS ' + v.toLocaleString() } } }
    }
});

// Income pie
const incomeData = @json($incomeByType);
new Chart(document.getElementById('incomeChart'), {
    type: 'doughnut',
    data: {
        labels: incomeData.map(d => d.type),
        datasets: [{ data: incomeData.map(d => parseFloat(d.total)), backgroundColor: palette }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
    }
});

// Expense pie
const expenseData = @json($expenseByType);
new Chart(document.getElementById('expenseChart'), {
    type: 'doughnut',
    data: {
        labels: expenseData.map(d => d.label),
        datasets: [{ data: expenseData.map(d => parseFloat(d.total)), backgroundColor: palette }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
    }
});
</script>

@endsection